<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class CheckoutButtonTranslationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $translations = [
            [
                'group' => 'cart',
                'slug' => 'cart.actions.checkout',
                'value' => [
                    'uk' => 'Оформити замовлення',
                    'ru' => 'Оформить заказ',
                    'en' => 'Place order',
                ],
                'description' => 'Кнопка оформления заказа',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.actions.pay',
                'value' => [
                    'uk' => 'Перейти до оплати',
                    'ru' => 'Перейти к оплате',
                    'en' => 'Proceed to payment',
                ],
                'description' => 'Кнопка перехода к оплате',
            ],
        ];

        $this->command->info('Добавление переводов для кнопок оформления заказа...');

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

        $this->command->info('Все переводы успешно добавлены!');
    }
}

