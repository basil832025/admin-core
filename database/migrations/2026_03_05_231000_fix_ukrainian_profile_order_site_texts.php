<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $uk = [
            'profile.orders.details_title' => 'Деталі замовлення',
            'profile.orders.back_to_history' => 'Назад до історії замовлень',
            'profile.orders.thank_you' => 'Дякуємо за замовлення!',
            'profile.orders.delivered' => 'Доставлено',
            'profile.orders.recipient' => 'Отримувач',
            'profile.orders.address' => 'Адреса',
            'profile.orders.delivery_method' => 'Спосіб отримання',
            'profile.orders.delivery.pickup' => 'Самовивіз',
            'profile.orders.delivery.courier' => 'Доставка кур\'єром',
            'profile.orders.payment_method' => 'Спосіб оплати',
            'profile.orders.payment.card' => 'Оплата карткою',
            'profile.orders.payment.cash' => 'Оплата готівкою',
            'profile.orders.payment.online' => 'Онлайн-оплата',
            'profile.orders.goods' => 'Товари',
            'profile.orders.total_to_pay' => 'Разом до оплати',
            'profile.orders.repeat' => 'Повторити замовлення',

            'profile.bonuses.jan' => 'Січ',
            'profile.bonuses.feb' => 'Лют',
            'profile.bonuses.mar' => 'Бер',
            'profile.bonuses.apr' => 'Кві',
            'profile.bonuses.may' => 'Тра',
            'profile.bonuses.jun' => 'Чер',
            'profile.bonuses.jul' => 'Лип',
            'profile.bonuses.aug' => 'Сер',
            'profile.bonuses.sep' => 'Вер',
            'profile.bonuses.oct' => 'Жов',
            'profile.bonuses.nov' => 'Лис',
            'profile.bonuses.dec' => 'Гру',

            'address.parts.house_short' => 'буд.',
            'address.parts.apartment_short' => 'кв.',
        ];

        foreach ($uk as $slug => $ukText) {
            $record = DB::table('bs_site_texts')->where('slug', $slug)->first();
            if (! $record) {
                continue;
            }

            $value = [];
            if ($record->value) {
                $decoded = json_decode((string) $record->value, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                }
            }

            $value['uk'] = $ukText;

            DB::table('bs_site_texts')
                ->where('id', $record->id)
                ->update([
                    'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
    }
};
