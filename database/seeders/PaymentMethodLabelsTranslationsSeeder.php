<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class PaymentMethodLabelsTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        $translations = [
            [
                'group' => 'cart',
                'slug' => 'cart.payment.liqpay',
                'value' => [
                    'uk' => 'Онлайн-оплата карткою',
                    'ru' => 'Онлайн-оплата картой',
                    'en' => 'Online card payment',
                ],
                'description' => 'Метод оплаты: онлайн-оплата картой',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.card_on_delivery',
                'value' => [
                    'uk' => 'Оплата через POS-термінал при отриманні',
                    'ru' => 'Оплата через POS-терминал при получении',
                    'en' => 'Payment via POS terminal upon receipt',
                ],
                'description' => 'Метод оплаты: POS-терминал при получении',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.cash',
                'value' => [
                    'uk' => 'Готівкою при отриманні',
                    'ru' => 'Наличными при получении',
                    'en' => 'Cash upon receipt',
                ],
                'description' => 'Метод оплаты: наличные при получении',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.invoice',
                'value' => [
                    'uk' => 'Безготівковий розрахунок за рахунком для юридичних осіб',
                    'ru' => 'Безналичный расчет по счету для юридических лиц',
                    'en' => 'Bank transfer by invoice for legal entities',
                ],
                'description' => 'Метод оплаты: безналичный расчет по счету для юридических лиц',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.payment.online',
                'value' => [
                    'uk' => 'Онлайн-оплата карткою',
                    'ru' => 'Онлайн-оплата картой',
                    'en' => 'Online card payment',
                ],
                'description' => 'Профиль: онлайн-оплата картой',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.payment.card',
                'value' => [
                    'uk' => 'Оплата через POS-термінал при отриманні',
                    'ru' => 'Оплата через POS-терминал при получении',
                    'en' => 'Payment via POS terminal upon receipt',
                ],
                'description' => 'Профиль: оплата через POS-терминал при получении',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.payment.cash',
                'value' => [
                    'uk' => 'Готівкою при отриманні',
                    'ru' => 'Наличными при получении',
                    'en' => 'Cash upon receipt',
                ],
                'description' => 'Профиль: наличные при получении',
            ],
        ];

        foreach ($translations as $data) {
            SiteText::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'group' => $data['group'],
                    'value' => $data['value'],
                    'description' => $data['description'],
                ]
            );
        }

        $this->command?->info('Payment method labels translations added/updated.');
    }
}
