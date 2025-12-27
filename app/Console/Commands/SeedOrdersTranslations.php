<?php

namespace App\Console\Commands;

use App\Models\SiteText;
use Illuminate\Console\Command;

class SeedOrdersTranslations extends Command
{
    protected $signature = 'translations:seed-orders';
    protected $description = 'Добавить переводы для страницы истории заказов в bs_site_texts';

    public function handle()
    {
        $translations = [
            // Основные ключи страницы истории заказов
            [
                'group' => 'profile',
                'slug' => 'profile.orders.title',
                'value' => ['uk' => 'История заказов', 'ru' => 'История заказов', 'en' => 'Order History'],
                'description' => 'Заголовок страницы истории заказов',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.all',
                'value' => ['uk' => 'Все', 'ru' => 'Все', 'en' => 'All'],
                'description' => 'Фильтр: Все заказы',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.cancelled',
                'value' => ['uk' => 'Отмененные', 'ru' => 'Отмененные', 'en' => 'Cancelled'],
                'description' => 'Фильтр: Отмененные заказы',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.completed',
                'value' => ['uk' => 'Завершены', 'ru' => 'Завершены', 'en' => 'Completed'],
                'description' => 'Фильтр: Завершенные заказы',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.empty',
                'value' => ['uk' => 'Нет заказов', 'ru' => 'Нет заказов', 'en' => 'No orders'],
                'description' => 'Сообщение при отсутствии заказов',
            ],
            // Детали заказа
            [
                'group' => 'profile',
                'slug' => 'profile.orders.details_title',
                'value' => ['uk' => 'Детали заказа', 'ru' => 'Детали заказа', 'en' => 'Order Details'],
                'description' => 'Заголовок детальной страницы заказа',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.back_to_history',
                'value' => ['uk' => 'Назад к истории заказов', 'ru' => 'Назад к истории заказов', 'en' => 'Back to order history'],
                'description' => 'Кнопка возврата к истории заказов',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.details',
                'value' => ['uk' => 'Детали', 'ru' => 'Детали', 'en' => 'Details'],
                'description' => 'Кнопка "Детали"',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.repeat',
                'value' => ['uk' => 'Повторить заказ', 'ru' => 'Повторить заказ', 'en' => 'Repeat order'],
                'description' => 'Кнопка "Повторить заказ"',
            ],
            // Доставка
            [
                'group' => 'profile',
                'slug' => 'profile.orders.delivery.courier',
                'value' => ['uk' => 'Доставка курьером', 'ru' => 'Доставка курьером', 'en' => 'Courier delivery'],
                'description' => 'Способ доставки: курьером',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.delivery.pickup',
                'value' => ['uk' => 'Самовывоз', 'ru' => 'Самовывоз', 'en' => 'Pickup'],
                'description' => 'Способ доставки: самовывоз',
            ],
            // Оплата
            [
                'group' => 'profile',
                'slug' => 'profile.orders.payment.card',
                'value' => ['uk' => 'Оплата картой', 'ru' => 'Оплата картой', 'en' => 'Card payment'],
                'description' => 'Способ оплаты: картой',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.payment.cash',
                'value' => ['uk' => 'Оплата наличными', 'ru' => 'Оплата наличными', 'en' => 'Cash payment'],
                'description' => 'Способ оплаты: наличными',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.payment.online',
                'value' => ['uk' => 'Онлайн оплата', 'ru' => 'Онлайн оплата', 'en' => 'Online payment'],
                'description' => 'Способ оплаты: онлайн',
            ],
            // Общие поля заказа
            [
                'group' => 'profile',
                'slug' => 'profile.orders.order_for',
                'value' => ['uk' => 'Заказ на', 'ru' => 'Заказ на', 'en' => 'Order for'],
                'description' => 'Текст "Заказ на"',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.uah',
                'value' => ['uk' => 'грн', 'ru' => 'грн', 'en' => 'UAH'],
                'description' => 'Валюта: гривна',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.items',
                'value' => ['uk' => 'позиций', 'ru' => 'позиций', 'en' => 'items'],
                'description' => 'Текст "позиций"',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.items_pcs',
                'value' => ['uk' => 'шт', 'ru' => 'шт', 'en' => 'pcs'],
                'description' => 'Единица измерения: штук',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.bonuses',
                'value' => ['uk' => 'бонусов', 'ru' => 'бонусов', 'en' => 'bonuses'],
                'description' => 'Текст "бонусов" (количество)',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.bonuses_title',
                'value' => ['uk' => 'Бонусы', 'ru' => 'Бонусы', 'en' => 'Bonuses'],
                'description' => 'Заголовок "Бонусы"',
            ],
            // Детальная страница заказа
            [
                'group' => 'profile',
                'slug' => 'profile.orders.delivered',
                'value' => ['uk' => 'Доставлен', 'ru' => 'Доставлен', 'en' => 'Delivered'],
                'description' => 'Статус: Доставлен',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.recipient',
                'value' => ['uk' => 'Получатель', 'ru' => 'Получатель', 'en' => 'Recipient'],
                'description' => 'Поле: Получатель',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.address',
                'value' => ['uk' => 'Адрес', 'ru' => 'Адрес', 'en' => 'Address'],
                'description' => 'Поле: Адрес',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.delivery_method',
                'value' => ['uk' => 'Способ получения', 'ru' => 'Способ получения', 'en' => 'Delivery method'],
                'description' => 'Поле: Способ получения',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.payment_method',
                'value' => ['uk' => 'Способ оплаты', 'ru' => 'Способ оплаты', 'en' => 'Payment method'],
                'description' => 'Поле: Способ оплаты',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.goods',
                'value' => ['uk' => 'Товары', 'ru' => 'Товары', 'en' => 'Products'],
                'description' => 'Заголовок раздела товаров',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.total_to_pay',
                'value' => ['uk' => 'Итого к оплате', 'ru' => 'Итого к оплате', 'en' => 'Total to pay'],
                'description' => 'Итоговая сумма к оплате',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.thank_you',
                'value' => ['uk' => 'Спасибо за заказ!', 'ru' => 'Спасибо за заказ!', 'en' => 'Thank you for your order!'],
                'description' => 'Сообщение благодарности',
            ],
            // Повторить заказ
            [
                'group' => 'profile',
                'slug' => 'profile.orders.repeat_requires_auth',
                'value' => ['uk' => 'Для повторения заказа необходимо авторизоваться', 'ru' => 'Для повторения заказа необходимо авторизоваться', 'en' => 'Authentication required to repeat order'],
                'description' => 'Ошибка: требуется авторизация',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.repeat_empty',
                'value' => ['uk' => 'В заказе нет товаров для добавления в корзину', 'ru' => 'В заказе нет товаров для добавления в корзину', 'en' => 'Order has no items to add to cart'],
                'description' => 'Ошибка: заказ пустой',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.repeat_no_available',
                'value' => ['uk' => 'Нет доступных товаров для добавления в корзину', 'ru' => 'Нет доступных товаров для добавления в корзину', 'en' => 'No available products to add to cart'],
                'description' => 'Ошибка: нет доступных товаров',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.repeat_success',
                'value' => ['uk' => 'Товары из заказа добавлены в корзину', 'ru' => 'Товары из заказа добавлены в корзину', 'en' => 'Order items added to cart'],
                'description' => 'Успешное добавление товаров',
            ],
            [
                'group' => 'profile',
                'slug' => 'profile.orders.repeat_skipped',
                'value' => ['uk' => 'пропущено', 'ru' => 'пропущено', 'en' => 'skipped'],
                'description' => 'Пропущенные товары',
            ],
        ];

        $this->info('Добавление переводов для страницы истории заказов...');

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

