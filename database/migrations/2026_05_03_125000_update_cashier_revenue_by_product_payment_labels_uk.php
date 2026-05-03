<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('bs_print_templates')
            ->where('code', 'cashier_revenue_by_product')
            ->first(['id', 'template_body']);

        if (! $template) {
            return;
        }

        $templateBody = (string) ($template->template_body ?? '');
        if (trim($templateBody) === '') {
            return;
        }

        // Update payment labels to short Ukrainian variants.
        $replacements = [
            // Header labels in main table
            '>CASH<' => '>Гот.<',
            '>CARD<' => '>Карт.<',
            '>LIQPAY<' => '>Liq<',
            '>INV<' => '>Безгот.<',
            '>ORG<' => '>Рах.ф.<',
            '>FREE<' => '>Без опл.<',
            '>OTHER<' => '>Інше<',

            // Breakdown table rows
            '<td>CASH</td>' => '<td>Гот.</td>',
            '<td>CARD</td>' => '<td>Карт.</td>',
            '<td>LIQPAY</td>' => '<td>Liq</td>',
            '<td>INVOICE</td>' => '<td>Безгот.</td>',
            '<td>ORG</td>' => '<td>Рах.ф.</td>',
            '<td>FREE</td>' => '<td>Без опл.</td>',
            '<td>OTHER</td>' => '<td>Інше</td>',
        ];

        $updated = str_replace(array_keys($replacements), array_values($replacements), $templateBody);

        DB::table('bs_print_templates')
            ->where('id', (int) $template->id)
            ->update([
                'template_body' => $updated,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // no-op
    }
};
