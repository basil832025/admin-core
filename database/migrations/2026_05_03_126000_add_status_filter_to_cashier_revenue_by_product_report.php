<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('bs_print_templates')
            ->where('code', 'cashier_revenue_by_product')
            ->first(['id', 'parameters_schema', 'data_sources']);

        if (! $template) {
            return;
        }

        $schema = json_decode((string) ($template->parameters_schema ?? ''), true);
        if (! is_array($schema)) {
            $schema = [];
        }

        // Avoid duplicating the param if migration re-runs on another env.
        $has = false;
        foreach ($schema as $item) {
            if (is_array($item) && (string) ($item['key'] ?? '') === 'status_filter') {
                $has = true;
                break;
            }
        }

        if (! $has) {
            $schema[] = [
                'key' => 'status_filter',
                'type' => 'select',
                'label' => 'Статус заказов',
                'default' => 'delivered',
                'required' => true,
                'options' => [
                    'delivered' => 'Доставлено (по умолчанию)',
                    'all_active' => 'Все (кроме корзины и отмененных)',
                    'new' => 'Новые',
                    'processing' => 'В обработке',
                    'on_hold' => 'Отложенные',
                    'filling' => 'Начинка',
                    'molding' => 'Лепка',
                    'baking' => 'Печь',
                    'prepared' => 'Приготовлен',
                    'assembled' => 'Собран',
                    'shipped' => 'В пути',
                ],
            ];
        }

        $sources = json_decode((string) ($template->data_sources ?? ''), true);
        if (! is_array($sources)) {
            $sources = [];
        }

        foreach ($sources as &$source) {
            if (! is_array($source)) {
                continue;
            }

            if ((string) ($source['key'] ?? '') !== 'main' || (string) ($source['type'] ?? '') !== 'sql') {
                continue;
            }

            $query = (string) ($source['query'] ?? '');
            if ($query === '') {
                continue;
            }

            // Replace the fixed delivered filter with a param-based one.
            // Keep cancelled excluded always; cart is excluded always too.
            $query = preg_replace(
                "/\\n\\s*AND\\s+o\\.status\\s*=\\s*'delivered'\\s*\\n/i",
                "\n  AND o.status NOT IN ('cart', 'cancelled')\n  AND (COALESCE(NULLIF(:status_filter, ''), 'delivered') = 'all_active' OR o.status = COALESCE(NULLIF(:status_filter, ''), 'delivered'))\n",
                $query
            ) ?? $query;

            // If there was no fixed filter (unexpected), inject the condition before GROUP BY.
            if (! str_contains($query, ':status_filter')) {
                $query = preg_replace(
                    '/\nGROUP BY\s+t\.sku,\s+t\.title\s*\n/i',
                    "\n  AND o.status NOT IN ('cart', 'cancelled')\n  AND (COALESCE(NULLIF(:status_filter, ''), 'delivered') = 'all_active' OR o.status = COALESCE(NULLIF(:status_filter, ''), 'delivered'))\nGROUP BY t.sku, t.title\n",
                    $query
                ) ?? $query;
            }

            $source['query'] = $query;
        }
        unset($source);

        DB::table('bs_print_templates')
            ->where('id', (int) $template->id)
            ->update([
                'parameters_schema' => json_encode($schema, JSON_UNESCAPED_UNICODE),
                'data_sources' => json_encode($sources, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // no-op
    }
};
