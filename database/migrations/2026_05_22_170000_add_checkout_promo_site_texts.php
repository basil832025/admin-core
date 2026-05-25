<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            'checkout.promo.not_found_or_inactive' => [
                'uk' => 'Промокод не знайдено або він неактивний',
                'ru' => 'Промокод не найден или не активен',
                'en' => 'Promo code was not found or is inactive',
                'description' => 'Checkout promo error when promo code is missing or inactive',
            ],
            'checkout.promo.unavailable_now' => [
                'uk' => 'Цей промокод зараз не можна застосувати',
                'ru' => 'Этот промокод сейчас нельзя применить',
                'en' => 'This promo code cannot be applied right now',
                'description' => 'Checkout promo error when promo code cannot be applied for current client or limits',
            ],
            'checkout.promo.cart_empty' => [
                'uk' => 'Кошик порожній',
                'ru' => 'Корзина пуста',
                'en' => 'Cart is empty',
                'description' => 'Checkout promo error when trying to validate promo with empty cart',
            ],
            'checkout.promo.no_discount_for_items' => [
                'uk' => 'Промокод не дає знижки для поточних товарів',
                'ru' => 'Промокод не даёт скидки для текущих товаров',
                'en' => 'This promo code does not discount the current items',
                'description' => 'Checkout promo error when promo code scope does not match current cart items',
            ],
            'checkout.promo.applied' => [
                'uk' => 'Промокод застосовано',
                'ru' => 'Промокод применён',
                'en' => 'Promo code applied',
                'description' => 'Checkout promo success message',
            ],
        ];

        foreach ($rows as $slug => $payload) {
            $record = DB::table('bs_site_texts')->where('slug', $slug)->first();

            $value = [
                'uk' => $payload['uk'],
                'ru' => $payload['ru'],
                'en' => $payload['en'],
            ];

            if ($record) {
                $existing = json_decode((string) $record->value, true);
                $merged = is_array($existing) ? array_merge($existing, $value) : $value;

                DB::table('bs_site_texts')
                    ->where('id', $record->id)
                    ->update([
                        'group' => $record->group ?: 'checkout',
                        'value' => json_encode($merged, JSON_UNESCAPED_UNICODE),
                        'description' => $payload['description'],
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('bs_site_texts')->insert([
                'group' => 'checkout',
                'slug' => $slug,
                'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
                'description' => $payload['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('bs_site_texts')->whereIn('slug', [
            'checkout.promo.not_found_or_inactive',
            'checkout.promo.unavailable_now',
            'checkout.promo.cart_empty',
            'checkout.promo.no_discount_for_items',
            'checkout.promo.applied',
        ])->delete();
    }
};
