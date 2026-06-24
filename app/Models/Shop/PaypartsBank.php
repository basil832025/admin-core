<?php

namespace App\Models\Shop;

use App\Enums\PaypartsBankTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaypartsBank extends Model
{
    protected $table = 'bs_payparts_banks';

    protected $fillable = [
        'bank_type',
        'name',
        'description',
        'terms',
        'is_active',
        'audience_mode',
        'audience_client_ids',
        'store_id',
        'account_password',
        'rules',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'terms' => 'array',
        'rules' => 'array',
        'audience_client_ids' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeVisibleForClient(Builder $query, ?Client $client): Builder
    {
        if (! $client) {
            return $query->where(function (Builder $inner): void {
                $inner->whereNull('audience_mode')
                    ->orWhere('audience_mode', 'all');
            });
        }

        return $query->where(function (Builder $inner) use ($client): void {
            $inner->whereNull('audience_mode')
                ->orWhere('audience_mode', 'all')
                ->orWhere(function (Builder $q) use ($client): void {
                    $q->where('audience_mode', 'specific')
                        ->where(function (Builder $nested) use ($client): void {
                            $nested->whereJsonContains('audience_client_ids', (int) $client->id)
                                ->orWhereJsonContains('audience_client_ids', (string) $client->phone);
                        });
                });
        });
    }

    public function bankType(): ?PaypartsBankTypeEnum
    {
        return $this->bank_type ? PaypartsBankTypeEnum::tryFrom((string) $this->bank_type) : null;
    }

    public function localizedText(string $field, ?string $locale = null, ?string $fallback = null): ?string
    {
        $locale ??= app()->getLocale();
        $value = data_get($this->{$field} ?? [], $locale);

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        $defaultLocale = \App\Models\Setting::value('default_language_code') ?: config('app.locale');
        $value = data_get($this->{$field} ?? [], $defaultLocale);

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        if ($fallback !== null && trim($fallback) !== '') {
            return $fallback;
        }

        return null;
    }

    public function plansForAmount(float $amount): array
    {
        $amount = max(0, round($amount, 2));

        $rules = collect($this->rules ?? [])
            ->filter(fn ($rule): bool => (bool) ($rule['is_active'] ?? true))
            ->filter(fn ($rule): bool => (float) ($rule['min_amount'] ?? 0) <= $amount)
            ->sortByDesc(fn ($rule): float => (float) ($rule['min_amount'] ?? 0))
            ->values();

        $plans = [];

        foreach ($rules as $index => $rule) {
            $merchantTypesSource = (array) ($rule['merchant_types'] ?? []);
            $hasMerchantType = static function (string $type) use ($merchantTypesSource): bool {
                return in_array($type, $merchantTypesSource, true)
                    || ! empty($merchantTypesSource[$type] ?? false);
            };

            $merchantTypes = array_values(array_filter([
                $hasMerchantType('pp') ? 'PP' : null,
                $hasMerchantType('ii') ? 'II' : null,
            ]));

            foreach ($merchantTypes as $merchantType) {
                $maxPartsCount = (int) ($rule['parts_count'] ?? 0);
                $merchantTypeLabel = $merchantType === 'II'
                    ? st('cart.payment.payparts_type_ii', 'Миттєва розстрочка')
                    : st('cart.payment.payparts_type_pp', 'Оплата частинами');

                for ($partsCount = $maxPartsCount; $partsCount >= 3; $partsCount--) {
                    $duplicateExists = collect($plans)->contains(
                        fn (array $plan): bool => $plan['merchant_type'] === $merchantType
                            && (int) $plan['parts_count'] === $partsCount
                    );

                    if ($duplicateExists) {
                        continue;
                    }

                    $monthlyAmount = $this->calculateMonthlyAmount($amount, $partsCount, $merchantType);
                    $interestAmount = $merchantType === 'II' && $partsCount > 0
                        ? round($amount * 0.019 * $partsCount, 2)
                        : 0;
                    $totalAmount = round($amount + $interestAmount, 2);

                    $plans[] = [
                        'key' => $index . ':' . $merchantType . ':' . $partsCount,
                        'min_amount' => (float) ($rule['min_amount'] ?? 0),
                        'parts_count' => $partsCount,
                        'merchant_type' => $merchantType,
                        'merchant_type_label' => $merchantTypeLabel,
                        'amount' => $amount,
                        'monthly_amount' => $monthlyAmount,
                        'total_amount' => $totalAmount,
                        'interest_amount' => $interestAmount,
                        'formatted_amount' => number_format($totalAmount, 2, ',', ' '),
                    'formatted_monthly_amount' => number_format($monthlyAmount, 2, ',', ' '),
                    'formatted_interest_amount' => number_format($interestAmount, 2, ',', ' '),
                    'label' => trim(sprintf(
                        '%s %s %s — %s %s (%s)',
                        st('cart.payment.from_amount', 'від'),
                        number_format((float) ($rule['min_amount'] ?? 0), 0, ',', ' '),
                        st('cart.summary.currency_short', 'грн'),
                        $partsCount,
                        st('cart.payment.payments_count', 'платежів'),
                        $merchantTypeLabel
                    )),
                ];
                }
            }
        }

        return $plans;
    }

    private function calculateMonthlyAmount(float $amount, int $partsCount, string $merchantType): float
    {
        if ($partsCount <= 0) {
            return 0;
        }

        $basePayment = $amount / $partsCount;

        if ($merchantType === 'II') {
            return round($basePayment + ($amount * 0.019), 2);
        }

        return round($basePayment, 2);
    }
}
