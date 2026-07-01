<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LEGACY_FILTER_SQL = <<<'SQL'
      AND (
            COALESCE(CAST(:only_pies AS UNSIGNED), 0) = 0
            OR parent_category.slug = 'pies'
          )
SQL;

    private const FILTER_SQL = <<<'SQL'
      AND (
            COALESCE(CAST(:only_pies AS UNSIGNED), 0) = 0
            OR parent_category.slug = 'pies'
            OR EXISTS (
                SELECT 1
                FROM bs_cc_source_categories source_category
                WHERE source_category.source_id = o.source_id
                  AND source_category.local_category_id = category.id
                  AND (
                        source_category.alias LIKE '%-pies'
                        OR source_category.alias LIKE '%-pirogi'
                      )
            )
          )
SQL;

    public function up(): void
    {
        $template = DB::table('bs_print_templates')
            ->where('code', 'cashier_revenue_by_product')
            ->first(['id', 'parameters_schema', 'data_sources']);

        if (! $template) {
            return;
        }

        $parameters = json_decode((string) $template->parameters_schema, true);
        $parameters = is_array($parameters) ? $parameters : [];

        if (! collect($parameters)->contains(
            fn (mixed $item): bool => is_array($item) && ($item['key'] ?? null) === 'only_pies'
        )) {
            $parameters[] = [
                'key' => 'only_pies',
                'type' => 'boolean',
                'label' => 'Тільки пироги',
                'default' => false,
                'required' => false,
            ];
        }

        $dataSources = json_decode((string) $template->data_sources, true);
        if (! is_array($dataSources) || ! isset($dataSources[0]) || ! is_array($dataSources[0])) {
            return;
        }

        $query = (string) ($dataSources[0]['query'] ?? '');
        if (! str_contains($query, ':only_pies')) {
            $needle = "      AND o.status <> 'cart'\n";
            if (! str_contains($query, $needle)) {
                throw new RuntimeException('Could not add only_pies filter to cashier report query.');
            }

            $query = str_replace($needle, $needle.self::FILTER_SQL."\n", $query);
        } elseif (! str_contains($query, 'source_category.alias')) {
            $query = str_replace(self::LEGACY_FILTER_SQL, self::FILTER_SQL, $query);
        }

        $dataSources[0]['query'] = $query;

        DB::table('bs_print_templates')
            ->where('id', (int) $template->id)
            ->update([
                'parameters_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'data_sources' => json_encode($dataSources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $template = DB::table('bs_print_templates')
            ->where('code', 'cashier_revenue_by_product')
            ->first(['id', 'parameters_schema', 'data_sources']);

        if (! $template) {
            return;
        }

        $parameters = json_decode((string) $template->parameters_schema, true);
        $parameters = is_array($parameters) ? array_values(array_filter(
            $parameters,
            fn (mixed $item): bool => ! is_array($item) || ($item['key'] ?? null) !== 'only_pies'
        )) : [];

        $dataSources = json_decode((string) $template->data_sources, true);
        if (is_array($dataSources) && isset($dataSources[0]) && is_array($dataSources[0])) {
            $query = (string) ($dataSources[0]['query'] ?? '');
            $dataSources[0]['query'] = str_replace(self::FILTER_SQL."\n", '', $query);
        }

        DB::table('bs_print_templates')
            ->where('id', (int) $template->id)
            ->update([
                'parameters_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'data_sources' => json_encode($dataSources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
    }
};