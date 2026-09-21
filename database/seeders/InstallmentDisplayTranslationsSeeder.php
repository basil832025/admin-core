<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class InstallmentDisplayTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        $translations = [
            ['group' => 'cart', 'slug' => 'cart.installment.title', 'value' => ['uk' => 'Можна оплатити частинами!', 'ru' => 'Можно оплатить частями!', 'en' => 'Pay in installments!'], 'description' => 'Installment display: cart title'],
            ['group' => 'cart', 'slug' => 'cart.installment.plan', 'value' => ['uk' => '3 місяці — по', 'ru' => '3 месяца — по', 'en' => '3 months —'], 'description' => 'Installment display: cart payment prefix'],
            ['group' => 'cart', 'slug' => 'cart.installment.per_month', 'value' => ['uk' => 'на місяць', 'ru' => 'в месяц', 'en' => 'per month'], 'description' => 'Installment display: cart monthly suffix'],
            ['group' => 'product', 'slug' => 'product.installment.prefix', 'value' => ['uk' => 'Від', 'ru' => 'От', 'en' => 'From'], 'description' => 'Installment display: amount prefix'],
            ['group' => 'product', 'slug' => 'product.installment.term', 'value' => ['uk' => 'грн × 3 платежі', 'ru' => 'грн × 3 платежа', 'en' => 'UAH × 3 payments'], 'description' => 'Installment display: product term'],
            ['group' => 'product', 'slug' => 'product.installment.per_month_short', 'value' => ['uk' => 'міс.', 'ru' => 'мес.', 'en' => 'mo.'], 'description' => 'Installment display: short monthly suffix'],
            ['group' => 'product', 'slug' => 'product.installment.monobank', 'value' => ['uk' => 'Покупка частинами', 'ru' => 'Покупка частями', 'en' => 'Installment purchase'], 'description' => 'Installment display: Monobank label'],
            ['group' => 'product', 'slug' => 'product.installment.privatbank', 'value' => ['uk' => 'Оплата частинами', 'ru' => 'Оплата частями', 'en' => 'Payment in installments'], 'description' => 'Installment display: PrivatBank label'],
            ['group' => 'product', 'slug' => 'product.installment.dialog_title', 'value' => ['uk' => 'Купити частинами', 'ru' => 'Купить частями', 'en' => 'Buy in installments'], 'description' => 'Installment display: dialog title'],
            ['group' => 'product', 'slug' => 'product.installment.dialog_payments', 'value' => ['uk' => 'платежі', 'ru' => 'платежа', 'en' => 'payments'], 'description' => 'Installment display: dialog payments suffix'],
            ['group' => 'product', 'slug' => 'product.installment.dialog_hint', 'value' => ['uk' => 'Оформити оплату частинами можна під час оформлення замовлення.', 'ru' => 'Оформить оплату частями можно при оформлении заказа.', 'en' => 'You can choose installment payment during checkout.'], 'description' => 'Installment display: dialog hint'],
        ];

        foreach ($translations as $translation) {
            SiteText::updateOrCreate(
                ['slug' => $translation['slug']],
                [
                    'group' => $translation['group'],
                    'value' => $translation['value'],
                    'description' => $translation['description'],
                ],
            );
        }

        $this->command?->info('Installment display translations added/updated.');
    }
}
