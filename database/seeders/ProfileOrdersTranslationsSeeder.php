<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class ProfileOrdersTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        $translations = [
            'profile.orders.title' => [
                'uk' => 'Історія замовлень',
                'ru' => 'История заказов',
                'en' => 'Order history',
            ],
            'profile.orders.all' => [
                'uk' => 'Усі',
                'ru' => 'Все',
                'en' => 'All',
            ],
            'profile.orders.cancelled' => [
                'uk' => 'Скасовані',
                'ru' => 'Отмененные',
                'en' => 'Cancelled',
            ],
            'profile.orders.completed' => [
                'uk' => 'Завершені',
                'ru' => 'Завершены',
                'en' => 'Completed',
            ],
            'profile.orders.empty' => [
                'uk' => 'Немає замовлень',
                'ru' => 'Нет заказов',
                'en' => 'No orders yet',
            ],
            'profile.orders.delivery.pickup' => [
                'uk' => 'Самовивіз',
                'ru' => 'Самовывоз',
                'en' => 'Pickup',
            ],
            'profile.orders.delivery.courier' => [
                'uk' => 'Доставка кур\'єром',
                'ru' => 'Доставка курьером',
                'en' => 'Courier delivery',
            ],
            'profile.orders.payment.card' => [
                'uk' => 'Оплата карткою',
                'ru' => 'Оплата картой',
                'en' => 'Card payment',
            ],
            'profile.orders.payment.cash' => [
                'uk' => 'Оплата готівкою',
                'ru' => 'Оплата наличными',
                'en' => 'Cash payment',
            ],
            'profile.orders.payment.online' => [
                'uk' => 'Онлайн оплата',
                'ru' => 'Онлайн оплата',
                'en' => 'Online payment',
            ],
            'profile.orders.order_for' => [
                'uk' => 'Замовлення на',
                'ru' => 'Заказ на',
                'en' => 'Order for',
            ],
            'profile.orders.uah' => [
                'uk' => 'грн',
                'ru' => 'грн',
                'en' => 'UAH',
            ],
            'profile.orders.bonuses' => [
                'uk' => 'бонусів',
                'ru' => 'бонусов',
                'en' => 'bonuses',
            ],
            'profile.orders.items' => [
                'uk' => 'позицій',
                'ru' => 'позиций',
                'en' => 'items',
            ],
            'profile.orders.details' => [
                'uk' => 'Деталі',
                'ru' => 'Детали',
                'en' => 'Details',
            ],
            'profile.orders.repeat' => [
                'uk' => 'Повторити замовлення',
                'ru' => 'Повторить заказ',
                'en' => 'Repeat order',
            ],
            'profile.orders.details_title' => [
                'uk' => 'Деталі замовлення',
                'ru' => 'Детали заказа',
                'en' => 'Order details',
            ],
            'profile.orders.back_to_history' => [
                'uk' => 'Назад до історії замовлень',
                'ru' => 'Назад к истории заказов',
                'en' => 'Back to order history',
            ],
            'profile.orders.number_from_date' => [
                'uk' => '№ :number від :day :month.',
                'ru' => '№ :number от :day :month.',
                'en' => 'No. :number from :day :month.',
            ],
            'profile.orders.product_fallback' => [
                'uk' => 'Товар',
                'ru' => 'Товар',
                'en' => 'Product',
            ],
            'profile.orders.items_pcs' => [
                'uk' => 'шт',
                'ru' => 'шт',
                'en' => 'pcs',
            ],
            'profile.orders.order_status_title' => [
                'uk' => 'Статус замовлення',
                'ru' => 'Статус заказа',
                'en' => 'Order status',
            ],
            'profile.orders.thank_you' => [
                'uk' => 'Дякуємо за замовлення!',
                'ru' => 'Спасибо за заказ!',
                'en' => 'Thank you for your order!',
            ],
            'profile.orders.delivered' => [
                'uk' => 'Доставлено',
                'ru' => 'Доставлен',
                'en' => 'Delivered',
            ],
            'profile.orders.recipient' => [
                'uk' => 'Отримувач',
                'ru' => 'Получатель',
                'en' => 'Recipient',
            ],
            'profile.orders.address' => [
                'uk' => 'Адреса',
                'ru' => 'Адрес',
                'en' => 'Address',
            ],
            'profile.orders.delivery_method' => [
                'uk' => 'Спосіб отримання',
                'ru' => 'Способ получения',
                'en' => 'Delivery method',
            ],
            'profile.orders.payment_method' => [
                'uk' => 'Спосіб оплати',
                'ru' => 'Способ оплаты',
                'en' => 'Payment method',
            ],
            'profile.orders.goods' => [
                'uk' => 'Товари',
                'ru' => 'Товары',
                'en' => 'Products',
            ],
            'profile.orders.discount' => [
                'uk' => 'Знижка',
                'ru' => 'Скидка',
                'en' => 'Discount',
            ],
            'profile.orders.bonuses_spent' => [
                'uk' => 'Списано бонусів',
                'ru' => 'Списано бонусов',
                'en' => 'Bonuses spent',
            ],
            'profile.orders.delivery' => [
                'uk' => 'Доставка',
                'ru' => 'Доставка',
                'en' => 'Delivery',
            ],
            'profile.orders.total_to_pay' => [
                'uk' => 'Разом до оплати',
                'ru' => 'Итого к оплате',
                'en' => 'Total to pay',
            ],
            'profile.orders.bonuses_accrued' => [
                'uk' => 'Нараховано бонусів',
                'ru' => 'Начислено бонусов',
                'en' => 'Bonuses accrued',
            ],
            'profile.bonuses.jan' => ['uk' => 'Січ', 'ru' => 'Янв', 'en' => 'Jan'],
            'profile.bonuses.feb' => ['uk' => 'Лют', 'ru' => 'Фев', 'en' => 'Feb'],
            'profile.bonuses.mar' => ['uk' => 'Бер', 'ru' => 'Мар', 'en' => 'Mar'],
            'profile.bonuses.apr' => ['uk' => 'Кві', 'ru' => 'Апр', 'en' => 'Apr'],
            'profile.bonuses.may' => ['uk' => 'Тра', 'ru' => 'Май', 'en' => 'May'],
            'profile.bonuses.jun' => ['uk' => 'Чер', 'ru' => 'Июн', 'en' => 'Jun'],
            'profile.bonuses.jul' => ['uk' => 'Лип', 'ru' => 'Июл', 'en' => 'Jul'],
            'profile.bonuses.aug' => ['uk' => 'Сер', 'ru' => 'Авг', 'en' => 'Aug'],
            'profile.bonuses.sep' => ['uk' => 'Вер', 'ru' => 'Сен', 'en' => 'Sep'],
            'profile.bonuses.oct' => ['uk' => 'Жов', 'ru' => 'Окт', 'en' => 'Oct'],
            'profile.bonuses.nov' => ['uk' => 'Лис', 'ru' => 'Ноя', 'en' => 'Nov'],
            'profile.bonuses.dec' => ['uk' => 'Гру', 'ru' => 'Дек', 'en' => 'Dec'],
            'profile.bonuses.mon' => ['uk' => 'Пн', 'ru' => 'Пн', 'en' => 'Mon'],
            'profile.bonuses.tue' => ['uk' => 'Вт', 'ru' => 'Вт', 'en' => 'Tue'],
            'profile.bonuses.wed' => ['uk' => 'Ср', 'ru' => 'Ср', 'en' => 'Wed'],
            'profile.bonuses.thu' => ['uk' => 'Чт', 'ru' => 'Чт', 'en' => 'Thu'],
            'profile.bonuses.fri' => ['uk' => 'Пт', 'ru' => 'Пт', 'en' => 'Fri'],
            'profile.bonuses.sat' => ['uk' => 'Сб', 'ru' => 'Сб', 'en' => 'Sat'],
            'profile.bonuses.sun' => ['uk' => 'Нд', 'ru' => 'Вс', 'en' => 'Sun'],
        ];

        foreach ($translations as $slug => $value) {
            SiteText::updateOrCreate(
                ['slug' => $slug],
                [
                    'group' => explode('.', $slug)[0] ?? 'profile',
                    'value' => $value,
                    'description' => 'Profile orders translation',
                ]
            );
        }
    }
}
