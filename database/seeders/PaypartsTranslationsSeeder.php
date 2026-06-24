<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class PaypartsTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        $translations = [
            [
                'group' => 'cart',
                'slug' => 'cart.payment.payparts_unavailable',
                'value' => [
                    'uk' => 'Оплата частинами зараз налаштовується. Доступні банки зʼявляться після додавання записів у адмінці.',
                    'ru' => 'Оплата частями сейчас настраивается. Доступные банки появятся после добавления записей в админке.',
                    'en' => 'Payment in parts is being configured. Available banks will appear after records are added in the admin panel.',
                ],
                'description' => 'Checkout Payparts: banks unavailable message',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.read_terms',
                'value' => [
                    'uk' => 'Читати умови',
                    'ru' => 'Читать условия',
                    'en' => 'Read terms',
                ],
                'description' => 'Checkout Payparts: read terms link',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.payparts_no_rules',
                'value' => [
                    'uk' => 'Для цієї суми немає доступних умов кредитування.',
                    'ru' => 'Для этой суммы нет доступных условий кредитования.',
                    'en' => 'There are no available credit terms for this amount.',
                ],
                'description' => 'Checkout Payparts: no rules for amount',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.credit_type',
                'value' => [
                    'uk' => 'Умова кредиту',
                    'ru' => 'Условие кредита',
                    'en' => 'Credit type',
                ],
                'description' => 'Checkout Payparts: credit type label',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.payparts_type_pp',
                'value' => [
                    'uk' => 'Оплата частинами',
                    'ru' => 'Оплата частями',
                    'en' => 'Payment in parts',
                ],
                'description' => 'Checkout Payparts: PrivatBank PP type label',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.payparts_type_ii',
                'value' => [
                    'uk' => 'Миттєва розстрочка',
                    'ru' => 'Мгновенная рассрочка',
                    'en' => 'Instant installment',
                ],
                'description' => 'Checkout Payparts: PrivatBank II type label',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.credit_term',
                'value' => [
                    'uk' => 'Строк кредиту',
                    'ru' => 'Срок кредита',
                    'en' => 'Credit term',
                ],
                'description' => 'Checkout Payparts: credit term label',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.payments_count',
                'value' => [
                    'uk' => 'платежів',
                    'ru' => 'платежей',
                    'en' => 'payments',
                ],
                'description' => 'Checkout Payparts: payments count suffix',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.from_amount',
                'value' => [
                    'uk' => 'від',
                    'ru' => 'от',
                    'en' => 'from',
                ],
                'description' => 'Checkout Payparts: from amount prefix',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.monthly_payment',
                'value' => [
                    'uk' => 'Щомісяця',
                    'ru' => 'Ежемесячно',
                    'en' => 'Monthly',
                ],
                'description' => 'Checkout Payparts: monthly payment label',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.credit_total',
                'value' => [
                    'uk' => 'Вартість кредиту',
                    'ru' => 'Стоимость кредита',
                    'en' => 'Credit cost',
                ],
                'description' => 'Checkout Payparts: credit total label',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.credit_total_help',
                'value' => [
                    'uk' => 'Довідка про вартість кредиту',
                    'ru' => 'Справка о стоимости кредита',
                    'en' => 'Credit cost help',
                ],
                'description' => 'Checkout Payparts: credit total tooltip aria label',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.credit_total_tooltip',
                'value' => [
                    'uk' => 'Фактична вартість буде визначена при оформленні чек-договору з Банком згідно з обраними умовами.',
                    'ru' => 'Фактическая стоимость будет определена при оформлении чек-договора с Банком согласно выбранных условий.',
                    'en' => 'The actual cost will be determined when the receipt agreement is completed with the Bank according to the selected terms.',
                ],
                'description' => 'Checkout Payparts: credit total tooltip text',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.credit_percent',
                'value' => [
                    'uk' => '% за кредитом',
                    'ru' => '% по кредиту',
                    'en' => 'Credit %',
                ],
                'description' => 'Checkout Payparts: credit percent label',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.payparts_financial_phone',
                'value' => [
                    'uk' => 'Фінансовий номер телефону',
                    'ru' => 'Финансовый номер телефона',
                    'en' => 'Financial phone number',
                ],
                'description' => 'Checkout Payparts: financial phone label',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.payparts_financial_phone_format',
                'value' => [
                    'uk' => 'Введіть повний номер телефону',
                    'ru' => 'Введите полный номер телефона',
                    'en' => 'Enter the full phone number',
                ],
                'description' => 'Checkout Payparts: financial phone browser validation title',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.payparts_financial_phone_required',
                'value' => [
                    'uk' => 'Поле обовʼязкове',
                    'ru' => 'Поле обязательное',
                    'en' => 'This field is required',
                ],
                'description' => 'Checkout Payparts: financial phone required validation',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.payparts_financial_phone_invalid',
                'value' => [
                    'uk' => 'Введіть повний номер телефону',
                    'ru' => 'Введите полный номер телефона',
                    'en' => 'Enter the full phone number',
                ],
                'description' => 'Checkout Payparts: financial phone invalid validation',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.payparts_bank_unavailable',
                'value' => [
                    'uk' => 'Обраний банк зараз недоступний.',
                    'ru' => 'Выбранный банк сейчас недоступен.',
                    'en' => 'The selected bank is currently unavailable.',
                ],
                'description' => 'Checkout Payparts: selected bank unavailable',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.payparts_plan_unavailable',
                'value' => [
                    'uk' => 'Обрані умови оплати частинами недоступні для цієї суми.',
                    'ru' => 'Выбранные условия оплаты частями недоступны для этой суммы.',
                    'en' => 'The selected payment in parts terms are unavailable for this amount.',
                ],
                'description' => 'Checkout Payparts: selected plan unavailable',
            ],
            [
                'group' => 'cart',
                'slug' => 'cart.payment.payparts_create_failed',
                'value' => [
                    'uk' => 'Не вдалося перейти до оплати частинами. Спробуйте ще раз або оберіть інший спосіб оплати.',
                    'ru' => 'Не удалось перейти к оплате частями. Попробуйте еще раз или выберите другой способ оплаты.',
                    'en' => 'Could not proceed to payment in parts. Try again or choose another payment method.',
                ],
                'description' => 'Checkout Payparts: create payment failed',
            ],
            [
                'group' => 'checkout',
                'slug' => 'checkout.payparts.order_title',
                'value' => [
                    'uk' => 'Оплата частинами замовлення №',
                    'ru' => 'Оплата частями заказа №',
                    'en' => 'Payment in parts for order #',
                ],
                'description' => 'Payparts payment page title',
            ],
            [
                'group' => 'checkout',
                'slug' => 'checkout.payparts.bank',
                'value' => [
                    'uk' => 'Банк',
                    'ru' => 'Банк',
                    'en' => 'Bank',
                ],
                'description' => 'Payparts payment page bank label',
            ],
            [
                'group' => 'checkout',
                'slug' => 'checkout.payparts.back_to_checkout',
                'value' => [
                    'uk' => 'Повернутися до оформлення',
                    'ru' => 'Вернуться к оформлению',
                    'en' => 'Back to checkout',
                ],
                'description' => 'Payparts payment page back button',
            ],
            [
                'group' => 'checkout',
                'slug' => 'checkout.payparts.redirect_hint',
                'value' => [
                    'uk' => 'Відкрийте сторінку ПриватБанку в новій вкладці та підтвердьте оплату частинами. Цю сторінку не закривайте: ми очікуємо підтвердження від банку.',
                    'ru' => 'Откройте страницу ПриватБанка в новой вкладке и подтвердите оплату частями. Эту страницу не закрывайте: мы ожидаем подтверждение от банка.',
                    'en' => 'Open the PrivatBank page in a new tab and confirm payment in parts. Do not close this page: we are waiting for confirmation from the bank.',
                ],
                'description' => 'Payparts payment page redirect hint',
            ],
            [
                'group' => 'checkout',
                'slug' => 'checkout.payparts.waiting_bank',
                'value' => [
                    'uk' => 'Очікуємо підтвердження від ПриватБанку...',
                    'ru' => 'Ожидаем подтверждение от ПриватБанка...',
                    'en' => 'Waiting for confirmation from PrivatBank...',
                ],
                'description' => 'Payparts payment page waiting status',
            ],
            [
                'group' => 'checkout',
                'slug' => 'checkout.payparts.go_to_bank',
                'value' => [
                    'uk' => 'Відкрити ПриватБанк',
                    'ru' => 'Открыть ПриватБанк',
                    'en' => 'Open PrivatBank',
                ],
                'description' => 'Payparts payment page go to bank button',
            ],
            [
                'group' => 'checkout',
                'slug' => 'checkout.payparts.prepare_failed',
                'value' => [
                    'uk' => 'Не вдалося підготувати перехід до банку. Спробуйте ще раз.',
                    'ru' => 'Не удалось подготовить переход в банк. Попробуйте еще раз.',
                    'en' => 'Could not prepare the bank redirect. Try again.',
                ],
                'description' => 'Payparts payment page prepare failed',
            ],
            [
                'group' => 'checkout',
                'slug' => 'checkout.payparts.return_after_success',
                'value' => [
                    'uk' => 'Після підтвердження банку ми автоматично відкриємо сторінку успішного замовлення.',
                    'ru' => 'После подтверждения банка мы автоматически откроем страницу успешного заказа.',
                    'en' => 'After bank confirmation, we will automatically open the successful order page.',
                ],
                'description' => 'Payparts payment page return after success hint',
            ],
            [
                'group' => 'checkout',
                'slug' => 'checkout.payparts.failed_status',
                'value' => [
                    'uk' => 'Банк не підтвердив оплату частинами. Спробуйте ще раз або оберіть інший спосіб оплати.',
                    'ru' => 'Банк не подтвердил оплату частями. Попробуйте еще раз или выберите другой способ оплаты.',
                    'en' => 'The bank did not confirm payment in parts. Try again or choose another payment method.',
                ],
                'description' => 'Payparts payment page failed status',
            ],
            [
                'group' => 'checkout',
                'slug' => 'checkout.payparts.long_waiting_bank',
                'value' => [
                    'uk' => 'Підтвердження ще не надійшло. Якщо ви вже завершили оформлення в банку, зачекайте ще трохи або оновіть сторінку.',
                    'ru' => 'Подтверждение еще не поступило. Если вы уже завершили оформление в банке, подождите еще немного или обновите страницу.',
                    'en' => 'Confirmation has not arrived yet. If you have already completed the bank process, wait a little longer or refresh the page.',
                ],
                'description' => 'Payparts payment page long waiting status',
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

        $this->command?->info('Payparts translations added/updated.');
    }
}
