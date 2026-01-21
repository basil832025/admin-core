<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class OrderSuccessTranslationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $translations = [
            [
                'group' => 'order',
                'slug' => 'order.success.thank_you',
                'value' => [
                    'uk' => 'Дякуємо!',
                    'ru' => 'Спасибо!',
                    'en' => 'Thank you!',
                ],
                'description' => 'Заголовок на странице успешного оформления заказа',
            ],
            [
                'group' => 'order',
                'slug' => 'order.success.order_sent',
                'value' => [
                    'uk' => 'Ваше замовлення відправлено',
                    'ru' => 'Ваш заказ отправлен',
                    'en' => 'Your order has been sent',
                ],
                'description' => 'Подзаголовок на странице успешного оформления заказа',
            ],
            [
                'group' => 'order',
                'slug' => 'order.success.working_hours.thank_you',
                'value' => [
                    'uk' => 'Дякуємо Вам за замовлення.',
                    'ru' => 'Благодарим Вас за заказ.',
                    'en' => 'Thank you for your order.',
                ],
                'description' => 'Благодарность в рабочее время',
            ],
            [
                'group' => 'order',
                'slug' => 'order.success.working_hours.order_number',
                'value' => [
                    'uk' => 'Номер замовлення',
                    'ru' => 'Номер заказа',
                    'en' => 'Order number',
                ],
                'description' => 'Текст перед номером заказа (рабочее время)',
            ],
            [
                'group' => 'order',
                'slug' => 'order.success.working_hours.call_center',
                'value' => [
                    'uk' => 'Протягом 15 хвилин з Вами зв\'яжеться оператор колл-центру для підтвердження замовлення.',
                    'ru' => 'В течение 15 минут с Вами свяжется оператор колл-центра для подтверждения заказа.',
                    'en' => 'Within 15 minutes, a call center operator will contact you to confirm your order.',
                ],
                'description' => 'Текст о звонке оператора (рабочее время)',
            ],
            [
                'group' => 'order',
                'slug' => 'order.success.non_working_hours.thank_you',
                'value' => [
                    'uk' => 'Дякуємо Вам за замовлення.',
                    'ru' => 'Благодарим Вас за заказ.',
                    'en' => 'Thank you for your order.',
                ],
                'description' => 'Благодарность в нерабочее время',
            ],
            [
                'group' => 'order',
                'slug' => 'order.success.non_working_hours.order_number',
                'value' => [
                    'uk' => 'Номер замовлення',
                    'ru' => 'Номер заказа',
                    'en' => 'Order number',
                ],
                'description' => 'Текст перед номером заказа (нерабочее время)',
            ],
            [
                'group' => 'order',
                'slug' => 'order.success.non_working_hours.call_center',
                'value' => [
                    'uk' => 'З Вами завтра з 08:30 зв\'яжеться оператор колл-центру для підтвердження замовлення.',
                    'ru' => 'С Вами завтра с 08:30 свяжется оператор колл-центра для подтверждения заказа.',
                    'en' => 'A call center operator will contact you tomorrow at 08:30 to confirm your order.',
                ],
                'description' => 'Текст о звонке оператора (нерабочее время)',
            ],
            [
                'group' => 'order',
                'slug' => 'order.success.signature',
                'value' => [
                    'uk' => 'З повагою, команда «Три Пироги»',
                    'ru' => 'С уважением, команда «Три Пироги»',
                    'en' => 'Best regards, the "Three Pies" team',
                ],
                'description' => 'Подпись на странице успешного оформления заказа',
            ],
            [
                'group' => 'order',
                'slug' => 'order.success.back_to_home',
                'value' => [
                    'uk' => 'Повернутися на Головну',
                    'ru' => 'Вернуться на Главную',
                    'en' => 'Back to Home',
                ],
                'description' => 'Кнопка возврата на главную страницу',
            ],
            [
                'group' => 'order',
                'slug' => 'order.success.order_code',
                'value' => [
                    'uk' => 'Код замовлення',
                    'ru' => 'Код заказа',
                    'en' => 'Order code',
                ],
                'description' => 'Метка для кода заказа',
            ],
            [
                'group' => 'order',
                'slug' => 'order.success.date',
                'value' => [
                    'uk' => 'Дата',
                    'ru' => 'Дата',
                    'en' => 'Date',
                ],
                'description' => 'Метка для даты заказа',
            ],
            [
                'group' => 'order',
                'slug' => 'order.success.amount_to_pay',
                'value' => [
                    'uk' => 'Сума до оплати',
                    'ru' => 'Сумма к оплате',
                    'en' => 'Amount to pay',
                ],
                'description' => 'Метка для суммы к оплате',
            ],
            [
                'group' => 'order',
                'slug' => 'order.success.payment_method',
                'value' => [
                    'uk' => 'Спосіб оплати',
                    'ru' => 'Способ оплаты',
                    'en' => 'Payment method',
                ],
                'description' => 'Метка для способа оплаты',
            ],
        ];

        $this->command->info('Добавление переводов для страницы успешного оформления заказа...');

        foreach ($translations as $data) {
            SiteText::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'group' => $data['group'],
                    'value' => $data['value'],
                    'description' => $data['description'],
                ]
            );
            $this->command->line("✓ Добавлен/обновлен: {$data['slug']}");
        }

        $this->command->info('✅ Все переводы успешно добавлены!');
    }
}
