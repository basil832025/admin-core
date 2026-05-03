<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('bs_print_templates')
            ->where('code', 'cashier_revenue_by_product')
            ->first(['id', 'data_sources']);

        if (! $template) {
            return;
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

            // Ensure date params work even if UI sends datetime strings.
            $query = preg_replace(
                '/DATE\(COALESCE\(o\.date_order,\s*o\.created_at\)\)\s+BETWEEN\s+COALESCE\(NULLIF\(:date_from,\s*\'\'\),\s*CURDATE\(\)\)\s+AND\s+COALESCE\(NULLIF\(:date_to,\s*\'\'\),\s*CURDATE\(\)\)/i',
                "DATE(COALESCE(o.date_order, o.created_at)) BETWEEN DATE(COALESCE(NULLIF(:date_from, ''), CURDATE())) AND DATE(COALESCE(NULLIF(:date_to, ''), CURDATE()))",
                $query
            ) ?? $query;

            $source['query'] = $query;
        }
        unset($source);

        DB::table('bs_print_templates')
            ->where('id', (int) $template->id)
            ->update([
                'data_sources' => json_encode($sources, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // no-op
    }
};
