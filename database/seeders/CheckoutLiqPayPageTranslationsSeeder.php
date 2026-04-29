<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class CheckoutLiqPayPageTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        $translations = [
            [
                'group' => 'checkout',
                'slug' => 'checkout.liqpay.order_title',
                'value' => [
                    'uk' => 'Оплата замовлення №',
                    'ru' => 'Оплата заказа №',
                    'en' => 'Order payment #',
                ],
                'description' => 'Заголовок страницы LiqPay оплаты',
            ],
            [
                'group' => 'checkout',
                'slug' => 'checkout.liqpay.amount_to_pay',
                'value' => [
                    'uk' => 'До сплати',
                    'ru' => 'К оплате',
                    'en' => 'Amount to pay',
                ],
                'description' => 'Подпись суммы на странице LiqPay',
            ],
            [
                'group' => 'checkout',
                'slug' => 'checkout.liqpay.return_after_success',
                'value' => [
                    'uk' => 'Після успішної оплати ви будете автоматично повернуті на сторінку замовлення.',
                    'ru' => 'После успешной оплаты вы будете автоматически возвращены на страницу заказа.',
                    'en' => 'After successful payment, you will be automatically redirected back to the order page.',
                ],
                'description' => 'Подсказка о возврате после успешной оплаты',
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

        $this->command?->info('LiqPay checkout page translations added/updated.');
    }
}
