<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $clientBody = DB::table('bs_print_templates')
            ->where('code', 'receipt_client_default')
            ->value('template_body');

        if (is_string($clientBody) && $clientBody !== '') {
            $updatedClientBody = str_replace(
                "{% set delivery = shipping_total > 0 ? shipping_total : shipping_price %}\n\n{% set bonuses_spent = order.bonuses_spent|default(0) %}\n\n{% set base_no_bonus = order.subtotal|default(0) + order.discount_total|default(0) + delivery %}",
                "{% set delivery = delivery_amount|default(shipping_total > 0 ? shipping_total : shipping_price) %}\n\n{% set bonuses_spent = order.bonuses_spent|default(0) %}\n\n{% set to_pay = payable_total|default(null) %}\n{% set base_no_bonus = order.subtotal|default(0) + order.discount_total|default(0) + delivery %}\n{% set to_pay = to_pay is not null ? to_pay : (base_no_bonus - bonuses_spent) %}",
                $clientBody,
            );

            $updatedClientBody = str_replace(
                '{% set to_pay = base_no_bonus - bonuses_spent %}',
                '{% set to_pay = payable_total|default(base_no_bonus - bonuses_spent) %}',
                $updatedClientBody,
            );

            if ($updatedClientBody !== $clientBody) {
                DB::table('bs_print_templates')
                    ->where('code', 'receipt_client_default')
                    ->update([
                        'template_body' => $updatedClientBody,
                        'updated_at' => now(),
                    ]);
            }
        }

        $logisticBody = DB::table('bs_print_templates')
            ->where('code', 'receipt_logistic_default')
            ->value('template_body');

        if (is_string($logisticBody) && $logisticBody !== '') {
            $updatedLogisticBody = str_replace(
                "{% set delivery = order.shipping_total|default(0) > 0 ? order.shipping_total|default(0) : order.shipping_price|default(0) %}",
                "{% set delivery = delivery_amount|default(order.shipping_total|default(0) > 0 ? order.shipping_total|default(0) : order.shipping_price|default(0)) %}",
                $logisticBody,
            );

            $updatedLogisticBody = str_replace(
                "{% set to_pay = order.grand_total|default(order.total_price_sale|default(order.total_price|default(0))) %}",
                "{% set to_pay = payable_total|default(order.grand_total|default(order.total_price_sale|default(order.total_price|default(0)))) %}",
                $updatedLogisticBody,
            );

            if ($updatedLogisticBody !== $logisticBody) {
                DB::table('bs_print_templates')
                    ->where('code', 'receipt_logistic_default')
                    ->update([
                        'template_body' => $updatedLogisticBody,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
    }
};
