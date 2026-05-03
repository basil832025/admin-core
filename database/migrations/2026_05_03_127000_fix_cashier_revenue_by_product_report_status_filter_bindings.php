<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('bs_print_templates')
            ->where('code', 'cashier_revenue_by_product')
            ->first(['id', 'data_sources', 'parameters_schema']);

        if (! $template) {
            return;
        }

        // Ensure the second placeholder exists in schema (RunReport requires all bindings present).
        $schema = json_decode((string) ($template->parameters_schema ?? ''), true);
        if (! is_array($schema)) {
            $schema = [];
        }

        $has2 = false;
        foreach ($schema as $item) {
            if (is_array($item) && (string) ($item['key'] ?? '') === 'status_filter2') {
                $has2 = true;
                break;
            }
        }

        if (! $has2) {
            // Hidden technical param, kept in sync with status_filter.
            $schema[] = [
                'key' => 'status_filter2',
                'type' => 'text',
                'label' => 'status_filter2',
                'default' => 'delivered',
                'required' => false,
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

            // PDO may not allow repeating the same named placeholder.
            // Use :status_filter and :status_filter2.
            $query = str_replace(":status_filter", ":status_filter2", $query);
            // restore the first occurrence as :status_filter
            $query = preg_replace('/\:status_filter2/', ':status_filter', $query, 1) ?? $query;

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
