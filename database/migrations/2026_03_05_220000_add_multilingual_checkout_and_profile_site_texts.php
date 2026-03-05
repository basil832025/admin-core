<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            'profile.orders.order_status_title' => [
                'uk' => 'Статус замовлення',
                'ru' => 'Статус заказа',
                'en' => 'Order status',
                'description' => 'Order status title in profile order details',
            ],
            'profile.orders.discount' => [
                'uk' => 'Знижка',
                'ru' => 'Скидка',
                'en' => 'Discount',
                'description' => 'Discount label in profile order details',
            ],
            'profile.orders.bonuses_spent' => [
                'uk' => 'Списано бонусів',
                'ru' => 'Списано бонусов',
                'en' => 'Bonuses spent',
                'description' => 'Spent bonuses label in profile order details',
            ],
            'profile.orders.bonuses_accrued' => [
                'uk' => 'Нараховано бонусів',
                'ru' => 'Начислено бонусов',
                'en' => 'Bonuses earned',
                'description' => 'Accrued bonuses label in profile order details',
            ],
            'profile.orders.delivery' => [
                'uk' => 'Доставка',
                'ru' => 'Доставка',
                'en' => 'Delivery',
                'description' => 'Delivery label in profile order details',
            ],

            'checkout.liqpay.email_saved' => [
                'uk' => 'Email збережено. Тепер можна перейти до оплати.',
                'ru' => 'Email сохранен. Теперь можно перейти к оплате.',
                'en' => 'Email saved. You can proceed to payment now.',
                'description' => 'Success message after email save on LiqPay page',
            ],
            'checkout.liqpay.save_email_and_continue' => [
                'uk' => 'Зберегти email та продовжити',
                'ru' => 'Сохранить email и продолжить',
                'en' => 'Save email and continue',
                'description' => 'Button label to save email on LiqPay page',
            ],
            'checkout.liqpay.email_required_before_pay' => [
                'uk' => 'Щоб перейти до оплати, спочатку вкажіть email для надсилання фіскального чека.',
                'ru' => 'Чтобы перейти к оплате, сначала укажите email для отправки фискального чека.',
                'en' => 'To proceed to payment, first provide an email for the fiscal receipt.',
                'description' => 'Hint when email is required before LiqPay payment',
            ],
            'checkout.liqpay.change_email' => [
                'uk' => 'Змінити email',
                'ru' => 'Изменить email',
                'en' => 'Change email',
                'description' => 'Link label to change email on LiqPay page',
            ],
            'checkout.liqpay.change_email_title' => [
                'uk' => 'Змінити email',
                'ru' => 'Изменить email',
                'en' => 'Change email',
                'description' => 'Modal title to change email on LiqPay page',
            ],
            'checkout.liqpay.email_required' => [
                'uk' => 'Вкажіть email — на нього буде надіслано фіскальний чек.',
                'ru' => 'Укажите email — на него будет отправлен фискальный чек.',
                'en' => 'Provide an email - fiscal receipt will be sent there.',
                'description' => 'Validation message for required email on LiqPay',
            ],

            'checkout.fiscal_receipt.title' => [
                'uk' => 'Фіскальний чек',
                'ru' => 'Фискальный чек',
                'en' => 'Fiscal receipt',
                'description' => 'Fiscal receipt email title',
            ],
            'checkout.fiscal_receipt.hello' => [
                'uk' => 'Дякуємо за оплату замовлення.',
                'ru' => 'Спасибо за оплату заказа.',
                'en' => 'Thank you for your payment.',
                'description' => 'Fiscal receipt email greeting',
            ],
            'checkout.fiscal_receipt.order' => [
                'uk' => 'Замовлення',
                'ru' => 'Заказ',
                'en' => 'Order',
                'description' => 'Order label in fiscal receipt email',
            ],
            'checkout.fiscal_receipt.number' => [
                'uk' => 'Фіскальний номер',
                'ru' => 'Фискальный номер',
                'en' => 'Fiscal number',
                'description' => 'Fiscal number label in receipt email',
            ],
            'checkout.fiscal_receipt.open' => [
                'uk' => 'Відкрити фіскальний чек',
                'ru' => 'Открыть фискальный чек',
                'en' => 'Open fiscal receipt',
                'description' => 'Open fiscal receipt button text',
            ],
            'checkout.fiscal_receipt.link_fallback' => [
                'uk' => 'Якщо кнопка не працює, відкрийте посилання:',
                'ru' => 'Если кнопка не работает, откройте ссылку:',
                'en' => 'If the button does not work, open this link:',
                'description' => 'Fallback link message in receipt email',
            ],
            'checkout.fiscal_receipt.signature' => [
                'uk' => 'З повагою, команда «Три Пироги»',
                'ru' => 'С уважением, команда «Три Пироги»',
                'en' => 'Best regards, Three Pies team',
                'description' => 'Signature in fiscal receipt email',
            ],

            'order.email.shipping' => [
                'uk' => 'Доставка',
                'ru' => 'Доставка',
                'en' => 'Delivery',
                'description' => 'Shipping label in client order email',
            ],
            'order.email.bonuses_spent' => [
                'uk' => 'Списано бонусів',
                'ru' => 'Списано бонусов',
                'en' => 'Bonuses spent',
                'description' => 'Spent bonuses label in client order email',
            ],
            'order.email.discount_total' => [
                'uk' => 'Знижка',
                'ru' => 'Скидка',
                'en' => 'Discount',
                'description' => 'Total discount label in client order email',
            ],
        ];

        foreach ($rows as $slug => $data) {
            $record = DB::table('bs_site_texts')->where('slug', $slug)->first();

            $existing = [];
            if ($record?->value) {
                $decoded = json_decode((string) $record->value, true);
                if (is_array($decoded)) {
                    $existing = $decoded;
                }
            }

            $value = array_merge($existing, [
                'uk' => $data['uk'],
                'ru' => $data['ru'],
                'en' => $data['en'],
            ]);

            $payload = [
                'group' => 'site',
                'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
                'description' => $data['description'],
                'updated_at' => now(),
            ];

            if ($record) {
                DB::table('bs_site_texts')
                    ->where('id', $record->id)
                    ->update($payload);
            } else {
                $payload['slug'] = $slug;
                $payload['created_at'] = now();

                DB::table('bs_site_texts')->insert($payload);
            }
        }
    }

    public function down(): void
    {
        DB::table('bs_site_texts')->whereIn('slug', [
            'profile.orders.order_status_title',
            'profile.orders.discount',
            'profile.orders.bonuses_spent',
            'profile.orders.bonuses_accrued',
            'profile.orders.delivery',
            'checkout.liqpay.email_saved',
            'checkout.liqpay.save_email_and_continue',
            'checkout.liqpay.email_required_before_pay',
            'checkout.liqpay.change_email',
            'checkout.liqpay.change_email_title',
            'checkout.liqpay.email_required',
            'checkout.fiscal_receipt.title',
            'checkout.fiscal_receipt.hello',
            'checkout.fiscal_receipt.order',
            'checkout.fiscal_receipt.number',
            'checkout.fiscal_receipt.open',
            'checkout.fiscal_receipt.link_fallback',
            'checkout.fiscal_receipt.signature',
            'order.email.shipping',
            'order.email.bonuses_spent',
            'order.email.discount_total',
        ])->delete();
    }
};
