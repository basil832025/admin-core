<?php

namespace App\Console\Commands;

use App\Models\SiteText;
use Illuminate\Console\Command;

class SeedOrderEmailTranslations extends Command
{
    protected $signature = 'translations:seed-order-email';
    protected $description = 'Добавить переводы для email заказа клиенту в bs_site_texts';

    public function handle(): int
    {
        $translations = [
            [
                'group' => 'order',
                'slug' => 'order.email.thank_you',
                'value' => [
                    'uk' => 'Дякуємо за ваше замовлення!',
                    'ru' => 'Спасибо за ваш заказ!',
                    'en' => 'Thank you for your order!',
                ],
                'description' => 'Заголовок благодарности в email',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.greeting',
                'value' => [
                    'uk' => 'Шановний клієнте!',
                    'ru' => 'Уважаемый клиент!',
                    'en' => 'Dear customer!',
                ],
                'description' => 'Приветствие в email',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.thank_you_message',
                'value' => [
                    'uk' => 'Дякуємо Вам за замовлення в нашому ресторані «Три Пироги».',
                    'ru' => 'Благодарим Вас за заказ в нашем ресторане «Три Пироги».',
                    'en' => 'Thank you for your order at our restaurant "Three Pies".',
                ],
                'description' => 'Сообщение благодарности',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.order_info',
                'value' => [
                    'uk' => 'Інформація про замовлення',
                    'ru' => 'Информация о заказе',
                    'en' => 'Order information',
                ],
                'description' => 'Заголовок информации о заказе',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.order_number',
                'value' => [
                    'uk' => 'Номер замовлення',
                    'ru' => 'Номер заказа',
                    'en' => 'Order number',
                ],
                'description' => 'Метка номера заказа',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.order_date',
                'value' => [
                    'uk' => 'Дата створення',
                    'ru' => 'Дата создания',
                    'en' => 'Creation date',
                ],
                'description' => 'Метка даты создания',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.order_status',
                'value' => [
                    'uk' => 'Статус',
                    'ru' => 'Статус',
                    'en' => 'Status',
                ],
                'description' => 'Метка статуса',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.delivery_date',
                'value' => [
                    'uk' => 'Дата доставки',
                    'ru' => 'Дата доставки',
                    'en' => 'Delivery date',
                ],
                'description' => 'Метка даты доставки',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.delivery_time',
                'value' => [
                    'uk' => 'Час доставки',
                    'ru' => 'Время доставки',
                    'en' => 'Delivery time',
                ],
                'description' => 'Метка времени доставки',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.delivery_address',
                'value' => [
                    'uk' => 'Адреса доставки',
                    'ru' => 'Адрес доставки',
                    'en' => 'Delivery address',
                ],
                'description' => 'Заголовок адреса доставки',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.no_address',
                'value' => [
                    'uk' => '—',
                    'ru' => '—',
                    'en' => '—',
                ],
                'description' => 'Нет адреса',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.delivery_method',
                'value' => [
                    'uk' => 'Спосіб доставки',
                    'ru' => 'Способ доставки',
                    'en' => 'Delivery method',
                ],
                'description' => 'Заголовок способа доставки',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.pickup',
                'value' => [
                    'uk' => 'Самовивіз',
                    'ru' => 'Самовывоз',
                    'en' => 'Pickup',
                ],
                'description' => 'Самовывоз',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.delivery',
                'value' => [
                    'uk' => 'Доставка кур\'єром',
                    'ru' => 'Доставка курьером',
                    'en' => 'Courier delivery',
                ],
                'description' => 'Доставка курьером',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.payment_method',
                'value' => [
                    'uk' => 'Спосіб оплати',
                    'ru' => 'Способ оплаты',
                    'en' => 'Payment method',
                ],
                'description' => 'Заголовок способа оплаты',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.items',
                'value' => [
                    'uk' => 'Товари',
                    'ru' => 'Товары',
                    'en' => 'Items',
                ],
                'description' => 'Заголовок товаров',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.item_name',
                'value' => [
                    'uk' => 'Назва',
                    'ru' => 'Название',
                    'en' => 'Name',
                ],
                'description' => 'Метка названия товара',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.quantity',
                'value' => [
                    'uk' => 'Кількість',
                    'ru' => 'Количество',
                    'en' => 'Quantity',
                ],
                'description' => 'Метка количества',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.unit_price',
                'value' => [
                    'uk' => 'Ціна за одиницю',
                    'ru' => 'Цена за единицу',
                    'en' => 'Unit price',
                ],
                'description' => 'Метка цены за единицу',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.total',
                'value' => [
                    'uk' => 'Сума',
                    'ru' => 'Сумма',
                    'en' => 'Total',
                ],
                'description' => 'Метка суммы',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.pcs',
                'value' => [
                    'uk' => 'шт.',
                    'ru' => 'шт.',
                    'en' => 'pcs.',
                ],
                'description' => 'Штуки',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.currency',
                'value' => [
                    'uk' => 'грн',
                    'ru' => 'грн',
                    'en' => 'UAH',
                ],
                'description' => 'Валюта',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.items_total',
                'value' => [
                    'uk' => 'Товари',
                    'ru' => 'Товары',
                    'en' => 'Items',
                ],
                'description' => 'Итого товары',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.discount',
                'value' => [
                    'uk' => 'Знижка',
                    'ru' => 'Скидка',
                    'en' => 'Discount',
                ],
                'description' => 'Скидка',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.total_to_pay',
                'value' => [
                    'uk' => 'Разом до оплати',
                    'ru' => 'Итого к оплате',
                    'en' => 'Total to pay',
                ],
                'description' => 'Итого к оплате',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.comments',
                'value' => [
                    'uk' => 'Коментарі',
                    'ru' => 'Комментарии',
                    'en' => 'Comments',
                ],
                'description' => 'Заголовок комментариев',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.closing',
                'value' => [
                    'uk' => 'Дякуємо за ваше замовлення!',
                    'ru' => 'Спасибо за ваш заказ!',
                    'en' => 'Thank you for your order!',
                ],
                'description' => 'Заключительная фраза',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.signature',
                'value' => [
                    'uk' => 'З повагою, команда «Три Пироги»',
                    'ru' => 'С уважением, команда «Три Пироги»',
                    'en' => 'Best regards, the "Three Pies" team',
                ],
                'description' => 'Подпись',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.no_email',
                'value' => [
                    'uk' => 'Email не вказано',
                    'ru' => 'Email не указан',
                    'en' => 'Email not specified',
                ],
                'description' => 'Ошибка: email не указан',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.sent_success',
                'value' => [
                    'uk' => 'Замовлення відправлено на email',
                    'ru' => 'Заказ отправлен на email',
                    'en' => 'Order sent to email',
                ],
                'description' => 'Успешная отправка',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.sent_error',
                'value' => [
                    'uk' => 'Помилка відправки email',
                    'ru' => 'Ошибка отправки email',
                    'en' => 'Email sending error',
                ],
                'description' => 'Ошибка отправки',
            ],
            [
                'group' => 'order',
                'slug' => 'order.email.product',
                'value' => [
                    'uk' => 'Товар',
                    'ru' => 'Товар',
                    'en' => 'Product',
                ],
                'description' => 'Товар по умолчанию',
            ],
        ];

        $this->info('Добавление переводов для email заказа клиенту...');

        foreach ($translations as $data) {
            SiteText::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'group' => $data['group'],
                    'value' => $data['value'],
                    'description' => $data['description'],
                ]
            );
            $this->line("✓ Добавлен/обновлен: {$data['slug']}");
        }

        $this->info('✅ Все переводы успешно добавлены!');
        return 0;
    }
}
