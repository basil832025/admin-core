<?php

namespace Database\Seeders;

use App\Models\PrintTemplate;
use App\Reports\DataProviders\ProductsCatalogReportProvider;
use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Database\Seeder;

class ProductsCatalogReportSeeder extends Seeder
{
    public function run(): void
    {
        $connection = config('database.default');
        $configuration = (new ConfigurationUrlParser)->parseConfiguration(config("database.connections.{$connection}"));

        if (config('project.name') !== '3piroga'
            || ! in_array($connection, ['mysql', 'mariadb'], true)
            || ($configuration['database'] ?? null) !== 'myadmin') {
            throw new \RuntimeException('ProductsCatalogReportSeeder requires project 3piroga and database myadmin.');
        }

        // Preserve an existing template and any edits made in the admin editor.
        PrintTemplate::withTrashed()->firstOrCreate(['code' => 'products_catalog'], [
            'name' => 'Каталог товарів — розміри та ціни',
            'type' => 'report',
            'engine' => 'twig',
            'output_format' => 'pdf',
            'default_paper_preset' => 'a4',
            'default_margin_top_mm' => 10,
            'default_margin_right_mm' => 10,
            'default_margin_bottom_mm' => 10,
            'default_margin_left_mm' => 10,
            'editor_mode' => 'code',
            'css_preset' => 'none',
            'is_active' => true,
            'description' => 'Товари та їх варіанти з каталогу. Усі категорії або вибрана категорія з підкатегоріями.',
            'parameters_schema' => [[
                'key' => 'category_id',
                'label' => 'Категорія',
                'type' => 'dictionary',
                'required' => false,
                'default' => '0',
                'dictionary_searchable' => true,
                'dictionary_query' => "SELECT 0 AS value, 'Усі категорії' AS label UNION ALL SELECT id AS value, COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(title, '$.uk')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(title, '$.ru')), ''), slug) AS label FROM bs_product_categories ORDER BY value",
            ]],
            'data_sources' => [[
                'key' => 'products',
                'type' => 'provider',
                'provider_class' => ProductsCatalogReportProvider::class,
                'enabled' => true,
            ]],
            'custom_css' => 'body{font-family:"DejaVu Sans",sans-serif;font-size:10pt}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px;text-align:left}th{background:#f1f5f9}thead{display:table-header-group}tr{page-break-inside:avoid}.price{text-align:right;white-space:nowrap}',
            'template_body' => <<<'TWIG'
<h2>Каталог товарів — розміри та ціни</h2>
<table>
    <thead><tr><th>Артикул</th><th>Назва товару</th><th>Розмір/об'єм</th><th>Ціна, грн</th><th>Категорія</th></tr></thead>
    <tbody>
    {% for row in datasets.products %}
        <tr><td>{{ row.sku }}</td><td>{{ row.title }}</td><td>{{ row.size_volume }}</td><td class="price">{{ row.price|number_format(2, ',', ' ') }}</td><td>{{ row.category_name }}</td></tr>
    {% else %}
        <tr><td colspan="5">Товарів не знайдено</td></tr>
    {% endfor %}
    </tbody>
</table>
TWIG,
        ]);
    }
}
