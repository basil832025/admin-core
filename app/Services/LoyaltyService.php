<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Shop\LoyaltyAccount;
use App\Models\Shop\LoyaltyRule;
use App\Models\Shop\LoyaltyTransaction;
use App\Models\Shop\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    public const EARN_BASE_GROSS = 'gross';
    public const EARN_BASE_NET_AFTER_DISCOUNTS = 'net_after_discounts';

    // App\Services\LoyaltyService.php



// ...

    /**
     * Теоретические бонусы за сумму (для чекаута).
     *
     * НЕ создаёт транзакции, только считает по тем же правилам,
     * что и accrueOnPaidOrder().
     */
    public function previewEarnForSum(float $sum, ?\DateTimeInterface $date = null): float
    {
        $sum = (float) $sum;
        if ($sum <= 0) {
            return 0.0;
        }

        $date = $date
            ? Carbon::parse($date)
            : Carbon::now();

        $rule = $this->findRuleForDate($date);
        if (! $rule || ! $rule->is_enabled) {
            return 0.0;
        }

        // минимальная сумма для начисления
        if ($rule->min_order_sum_for_earn && $sum < (float) $rule->min_order_sum_for_earn) {
            return 0.0;
        }

        $percent = (int) $rule->earn_percent;
        if ($percent <= 0) {
            return 0.0;
        }

        // Округляем до 2 знаков, как в accrueOnPaidOrder
        return round($sum * $percent / 100, 2);
    }

    /**
     * Специально для корзины: сумма после скидок.
     */
    public function previewEarnForCart(float $itemsTotal, float $discount = 0.0, float $bonusSpent = 0.0): float
    {
        $base = $this->resolveEarnBaseForCart($itemsTotal, $discount, $bonusSpent);

        return $this->previewEarnForSum($base);
    }

    public function resolveEarnBaseForCart(float $itemsTotal, float $discount = 0.0, float $bonusSpent = 0.0): float
    {
        return match ($this->earnBaseMode()) {
            self::EARN_BASE_NET_AFTER_DISCOUNTS => max($itemsTotal - $discount - $bonusSpent, 0),
            default => max($itemsTotal, 0),
        };
    }

    /**
     * Получить или создать бонусный счёт по клиенту/телефону.
     */
    public function getOrCreateAccount(?int $clientId, ?string $phone): LoyaltyAccount
    {
        // 1) пробуем по client_id
        if ($clientId) {
            $account = LoyaltyAccount::firstOrCreate(
                ['client_id' => $clientId],
                ['phone' => $phone, 'balance' => 0],
            );

            return $account;
        }

        // 2) fallback — по телефону
        if ($phone) {
            return LoyaltyAccount::firstOrCreate(
                ['phone' => $phone],
                ['balance' => 0],
            );
        }

        // 3) крайний случай — "анонимный" счёт (лучше не попадать сюда)
        return LoyaltyAccount::create([
            'balance' => 0,
        ]);
    }

    /**
     * Поиск счёта без создания.
     */
    public function findAccount(?int $clientId, ?string $phone): ?LoyaltyAccount
    {
        if ($clientId) {
            $acc = LoyaltyAccount::where('client_id', $clientId)->first();
            if ($acc) {
                return $acc;
            }
        }

        if ($phone) {
            return LoyaltyAccount::where('phone', $phone)->first();
        }

        return null;
    }

    /**
     * Баланс клиента по client_id/телефону.
     */
    public function getBalance(?int $clientId, ?string $phone): float
    {
        $account = $this->findAccount($clientId, $phone);

        return $account?->balance ?? 0.0;
    }

    /**
     * Правило, действующее на указанную дату (обычно дата оплаты).
     */
    public function findRuleForDate(\DateTimeInterface $date): ?LoyaltyRule
    {
        return LoyaltyRule::findForDate($date);
    }

    /**
     * Максимально возможное списание бонусов для заказа.
     *
     * Сейчас: min(баланс, сумма товаров - скидка).
     */
    public function getBonusLimitForOrder(float $itemsTotal, float $discount, float $balance): float
    {
        $base = max($itemsTotal - $discount, 0);

        return (float) min($balance, $base);
    }

    /**
     * Начисление обычных бонусов X% от чека после оплаты заказа.
     */
    public function accrueOnPaidOrder(Order $order): void
    {
        // Защита от повторного начисления
        $already = LoyaltyTransaction::query()
            ->where('order_id', $order->id)
            ->where('type', LoyaltyTransaction::TYPE_ACCRUAL)
            ->where('source', 'order')
            ->exists();

        if ($already) {
            return;
        }

        // Дата для выбора правила: paid_at -> date_order -> created_at
        $date = $order->paid_at
            ? Carbon::parse($order->paid_at)
            : ($order->date_order
                ? Carbon::parse($order->date_order)
                : ($order->created_at ?? Carbon::now()));

        $rule = $this->findRuleForDate($date);
        if (!$rule || !$rule->is_enabled) {
            return;
        }

        $sumForBonus = $this->resolveEarnBaseForOrder($order);

        if ($rule->min_order_sum_for_earn && $sumForBonus < (float) $rule->min_order_sum_for_earn) {
            return;
        }

        $percent = (int) $rule->earn_percent;
        if ($percent <= 0) {
            return;
        }

        $bonusAmount = round($sumForBonus * $percent / 100, 2);
        if ($bonusAmount <= 0) {
            return;
        }

        // Клиент и телефон — из заказа
        $clientId = $order->clients_id ?? null;
        $phone    = $order->clients?->phone ?? null; // связь clients() в модели Order

        DB::transaction(function () use ($clientId, $phone, $bonusAmount, $rule, $order) {
            $account  = $this->getOrCreateAccount($clientId, $phone);
            $expiresAt = Carbon::now()->addDays($rule->earn_expire_days);

            $tx = new LoyaltyTransaction();
            $tx->account_id       = $account->id;
            $tx->order_id         = $order->id;
            $tx->type             = LoyaltyTransaction::TYPE_ACCRUAL;
            $tx->source           = 'order';
            $tx->amount           = $bonusAmount;
            $tx->remaining_amount = $bonusAmount;
            $tx->expires_at       = $expiresAt;
            $tx->meta             = ['percent' => $rule->earn_percent];
            $tx->balance_after    = $account->balance + $bonusAmount;
            $tx->save();

            $account->balance = $tx->balance_after;
            $account->save();
        });
    }

    public function resolveEarnBaseForOrder(Order $order): float
    {
        $subtotal = (float) ($order->subtotal ?? 0);
        $discountTotal = (float) ($order->discount_total ?? 0);

        if (abs($discountTotal) < 0.0001) {
            $discountTotal = (float) $order->adjustments()
                ->whereNull('shop_order_item_id')
                ->whereNotIn('type', ['loyalty', 'loyalty_spent', 'bonus_spent'])
                ->sum('amount');
        }

        if ($subtotal <= 0) {
            $subtotal = (float) ($order->total_price ?? $order->grand_total ?? 0);
        }

        $bonusSpent = $order->resolveSpentBonuses();

        return match ($this->earnBaseMode()) {
            self::EARN_BASE_NET_AFTER_DISCOUNTS => max($subtotal + $discountTotal - $bonusSpent, 0),
            default => max($subtotal, 0),
        };
    }

    public function earnBaseMode(): string
    {
        $mode = (string) Setting::admin('loyalty.earn_base_mode', self::EARN_BASE_GROSS);

        return in_array($mode, [self::EARN_BASE_GROSS, self::EARN_BASE_NET_AFTER_DISCOUNTS], true)
            ? $mode
            : self::EARN_BASE_GROSS;
    }

    public static function earnBaseModeOptions(): array
    {
        return [
            self::EARN_BASE_GROSS => 'Від суми без знижок',
            self::EARN_BASE_NET_AFTER_DISCOUNTS => 'Від суми зі всіма знижками',
        ];
    }

    /**
     * Приветственный бонус для первого оплаченного заказа авторизованного клиента.
     */
    public function grantWelcomeBonusIfNeeded(Order $order): void
    {
        $clientId = $order->clients_id ?? null;
        if (!$clientId) {
            return;
        }

        $date = $order->paid_at
            ? Carbon::parse($order->paid_at)
            : ($order->created_at ?? Carbon::now());

        $rule = $this->findRuleForDate($date);
        if (!$rule || !$rule->is_enabled) {
            return;
        }

        $amount = (int) $rule->welcome_bonus_amount;
        if ($amount <= 0) {
            return;
        }

        // Счёт клиента
        $account = $this->getOrCreateAccount($clientId, $order->clients?->phone ?? null);

        // Уже выдавали приветственный?
        $already = LoyaltyTransaction::query()
            ->where('account_id', $account->id)
            ->where('type', LoyaltyTransaction::TYPE_ACCRUAL)
            ->where('source', 'welcome_bonus')
            ->exists();

        if ($already) {
            return;
        }

        // Это первый НЕ-корзина, НЕ-отменённый заказ?
        $hasOldOrders = Order::query()
            ->where('clients_id', $clientId)
            ->where('id', '!=', $order->id)
            ->whereNotIn('status', ['cart', 'cancelled'])
            ->exists();

        if ($hasOldOrders) {
            return;
        }

        DB::transaction(function () use ($account, $amount, $rule, $order) {
            $expiresAt = Carbon::now()->addDays($rule->welcome_bonus_expire_days);

            $tx = new LoyaltyTransaction();
            $tx->account_id       = $account->id;
            $tx->order_id         = $order->id;
            $tx->type             = LoyaltyTransaction::TYPE_ACCRUAL;
            $tx->source           = 'welcome_bonus';
            $tx->amount           = $amount;
            $tx->remaining_amount = $amount;
            $tx->expires_at       = $expiresAt;
            $tx->meta             = ['note' => 'Приветственный бонус'];
            $tx->balance_after    = $account->balance + $amount;
            $tx->save();

            $account->balance = $tx->balance_after;
            $account->save();
        });
    }

    /**
     * Списать бонусы при оформлении заказа.
     *
     * Возвращает реально списанную сумму.
     */
    public function spendOnOrder(Order $order, float $requestedAmount): float
    {
        $requestedAmount = max(0, round($requestedAmount, 2));
        if ($requestedAmount <= 0) {
            return 0.0;
        }

        $alreadySpent = (float) abs(LoyaltyTransaction::query()
            ->where('order_id', $order->id)
            ->where('type', LoyaltyTransaction::TYPE_SPEND)
            ->where('source', 'order')
            ->sum('amount'));

        if ($alreadySpent > 0) {
            return round($alreadySpent, 2);
        }

        $clientId = $order->clients_id ?? null;
        $phone    = $order->clients?->phone ?? null;

        $account = $this->findAccount($clientId, $phone);
        if (!$account || $account->balance <= 0) {
            return 0.0;
        }

        $amountToSpend = min($requestedAmount, $account->balance);
        if ($amountToSpend <= 0) {
            return 0.0;
        }

        DB::transaction(function () use ($order, $account, &$amountToSpend) {
            $left = $amountToSpend;

            // FIFO: сначала те начисления, что сгорят раньше
            $accruals = LoyaltyTransaction::query()
                ->where('account_id', $account->id)
                ->where('type', LoyaltyTransaction::TYPE_ACCRUAL)
                ->where('remaining_amount', '>', 0)
                ->orderBy('expires_at')
                ->lockForUpdate()
                ->get();

            foreach ($accruals as $accrual) {
                if ($left <= 0) {
                    break;
                }

                $available = (float) $accrual->remaining_amount;
                if ($available <= 0) {
                    continue;
                }

                $use = min($available, $left);

                $tx = new LoyaltyTransaction();
                $tx->account_id       = $account->id;
                $tx->order_id         = $order->id;
                $tx->type             = LoyaltyTransaction::TYPE_SPEND;
                $tx->source           = 'order';
                $tx->amount           = -$use;
                $tx->remaining_amount = null;
                $tx->expires_at       = null;
                $tx->meta             = ['accrual_id' => $accrual->id];
                $tx->balance_after    = $account->balance - $use;
                $tx->save();

                $accrual->remaining_amount = $available - $use;
                $accrual->save();

                $account->balance = $tx->balance_after;
                $account->save();

                $left -= $use;
            }

            // реально списано
            $amountToSpend -= $left;
        });

        return $amountToSpend;
    }

    /**
     * Cron: сгорание просроченных бонусов.
     */
    public function expireBonuses(): array
    {
        $now = Carbon::now();
        $expiredCount = 0;
        $expiredAmount = 0.0;

        LoyaltyTransaction::query()
            ->where('type', LoyaltyTransaction::TYPE_ACCRUAL)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->where('remaining_amount', '>', 0)
            ->chunkById(100, function ($accruals) use (&$expiredCount, &$expiredAmount) {
                foreach ($accruals as $accrual) {
                    DB::transaction(function () use ($accrual, &$expiredCount, &$expiredAmount) {
                        $account = $accrual->account()->lockForUpdate()->first();
                        if (!$account) {
                            return;
                        }

                        $expireAmount = (float) $accrual->remaining_amount;
                        if ($expireAmount <= 0) {
                            return;
                        }

                        $tx = new LoyaltyTransaction();
                        $tx->account_id       = $account->id;
                        $tx->order_id         = null;
                        $tx->type             = LoyaltyTransaction::TYPE_EXPIRE;
                        $tx->source           = 'system_expire';
                        $tx->amount           = -$expireAmount;
                        $tx->remaining_amount = null;
                        $tx->expires_at       = null;
                        $tx->meta             = ['accrual_id' => $accrual->id];
                        $tx->balance_after    = $account->balance - $expireAmount;
                        $tx->save();

                        $accrual->remaining_amount = 0;
                        $accrual->save();

                        $account->balance = $tx->balance_after;
                        $account->save();

                        $expiredCount++;
                        $expiredAmount += $expireAmount;
                    });
                }
            });

        return [
            'expired_count' => $expiredCount,
            'expired_amount' => round($expiredAmount, 2),
        ];
    }
}
