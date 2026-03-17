<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $templates = [
            [
                'code' => 'receipt_kitchen_default',
                'name' => 'Чек кухні (базовий)',
                'type' => 'receipt',
                'engine' => 'twig',
                'output_format' => 'pdf',
                'template_body' => '<div style="text-align:center;font-weight:700;font-size:14pt;margin-bottom:2mm;">{{ kitchen_header|default("Робочий чек кухні") }}</div>'
                    . '<div><strong>Замовлення №:</strong> {{ order_number|default("-") }}</div>'
                    . '<div><strong>Оператор:</strong> {{ operator|default("-") }}</div>'
                    . '<div><strong>Друк:</strong> {{ printed_at|default("-") }}</div>'
                    . '<div><strong>Телефон:</strong> {{ phone|default("-") }}</div>'
                    . '<div><strong>Доставка:</strong> {{ delivery_time|default("-") }}</div>'
                    . '<div><strong>Адреса:</strong> {{ address|default("-") }}</div>'
                    . '<div><strong>Коментар:</strong> {{ note|default("-") }}</div>'
                    . '<hr>'
                    . '{% if items_html is defined and items_html %}{{ items_html|raw }}{% else %}<div>{{ items|default("")|nl2br }}</div>{% endif %}'
                    . '<hr>'
                    . '<div style="font-size:13pt;font-weight:700;">Сума: {{ total|default("-") }}</div>',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'receipt_client_default',
                'name' => 'Чек клієнта (базовий)',
                'type' => 'receipt',
                'engine' => 'twig',
                'output_format' => 'pdf',
                'template_body' => '<div style="text-align:center;font-weight:700;font-size:14pt;margin-bottom:1mm;">ЧЕК ДЛЯ КЛІЄНТА</div>'
                    . '{% if client_logo is defined %}{{ client_logo|raw }}{% endif %}'
                    . '<div><strong>Замовлення №:</strong> {{ order_number|default("-") }}</div>'
                    . '<div><strong>Друк:</strong> {{ printed_at|default("-") }}</div>'
                    . '<div><strong>Телефон:</strong> {{ phone|default("-") }}</div>'
                    . '<div><strong>Час доставки:</strong> {{ delivery_time|default("-") }}</div>'
                    . '<div><strong>Адреса:</strong> {{ address|default("-") }}</div>'
                    . '<hr>'
                    . '{% if items_html is defined and items_html %}{{ items_html|raw }}{% else %}<div>{{ items|default("")|nl2br }}</div>{% endif %}'
                    . '<hr>'
                    . '<div style="font-size:13pt;font-weight:700;">До сплати: {{ total|default("-") }}</div>'
                    . '<div style="text-align:center;margin-top:2mm;">Дякуємо за замовлення</div>',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'receipt_logistic_default',
                'name' => 'Чек логіста (базовий)',
                'type' => 'receipt',
                'engine' => 'twig',
                'output_format' => 'pdf',
                'template_body' => '<div style="text-align:center;font-weight:700;font-size:14pt;margin-bottom:2mm;">СЛУЖБОВИЙ ЧЕК ЛОГІСТА</div>'
                    . '<div><strong>Замовлення №:</strong> {{ order_number|default("-") }}</div>'
                    . '<div><strong>Оператор:</strong> {{ operator|default("-") }}</div>'
                    . '<div><strong>Клієнт:</strong> {{ client_name|default("-") }}</div>'
                    . '<div><strong>Телефон:</strong> {{ phone|default("-") }}</div>'
                    . '<div><strong>Доставка:</strong> {{ delivery_time|default("-") }}</div>'
                    . '<div><strong>Адреса:</strong> {{ address|default("-") }}</div>'
                    . '<div><strong>Коментар:</strong> {{ note|default("-") }}</div>'
                    . '<hr>'
                    . '{% if items_html is defined and items_html %}{{ items_html|raw }}{% else %}<div>{{ items|default("")|nl2br }}</div>{% endif %}'
                    . '<hr>'
                    . '<div style="font-size:13pt;font-weight:700;">До сплати: {{ total|default("-") }}</div>',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($templates as $template) {
            DB::table('bs_print_templates')->updateOrInsert(
                ['code' => $template['code']],
                $template,
            );
        }

        $kitchenTemplateId = (int) DB::table('bs_print_templates')->where('code', 'receipt_kitchen_default')->value('id');
        $clientTemplateId = (int) DB::table('bs_print_templates')->where('code', 'receipt_client_default')->value('id');
        $logisticTemplateId = (int) DB::table('bs_print_templates')->where('code', 'receipt_logistic_default')->value('id');

        $profiles = [
            [
                'operation_code' => 'kitchen_work_receipt',
                'name' => 'Профіль: Робочий чек кухні',
                'print_template_id' => $kitchenTemplateId ?: null,
                'copies' => 1,
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ],
            [
                'operation_code' => 'client_receipt',
                'name' => 'Профіль: Чек для клієнта',
                'print_template_id' => $clientTemplateId ?: null,
                'copies' => 1,
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ],
            [
                'operation_code' => 'logistic_receipt',
                'name' => 'Профіль: Чек для логіста',
                'print_template_id' => $logisticTemplateId ?: null,
                'copies' => 1,
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        ];

        foreach ($profiles as $profile) {
            DB::table('bs_print_operation_profiles')->updateOrInsert(
                ['operation_code' => $profile['operation_code']],
                $profile,
            );
        }
    }

    public function down(): void
    {
        DB::table('bs_print_operation_profiles')
            ->whereIn('operation_code', ['kitchen_work_receipt', 'client_receipt', 'logistic_receipt'])
            ->delete();

        DB::table('bs_print_templates')
            ->whereIn('code', ['receipt_kitchen_default', 'receipt_client_default', 'receipt_logistic_default'])
            ->delete();
    }
};
