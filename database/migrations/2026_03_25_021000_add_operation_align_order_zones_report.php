<?php

use App\Reports\DataProviders\AlignOrderZonesOperationProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $groupId = (int) DB::table('bs_report_groups')->where('slug', 'operations')->value('id');

        if ($groupId <= 0) {
            $groupId = (int) DB::table('bs_report_groups')->where('name', 'Операции')->value('id');
        }

        if ($groupId <= 0) {
            DB::table('bs_report_groups')->insert([
                'name' => 'Операции',
                'slug' => 'operations',
                'sort' => 900,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $groupId = (int) DB::table('bs_report_groups')->where('slug', 'operations')->value('id');
        }

        $parametersSchema = [
            [
                'key' => 'date_from',
                'type' => 'date',
                'label' => 'Дата с',
                'default' => null,
                'required' => false,
            ],
            [
                'key' => 'date_to',
                'type' => 'date',
                'label' => 'Дата по',
                'default' => null,
                'required' => false,
            ],
            [
                'key' => 'brands',
                'type' => 'dictionary',
                'label' => 'Сайт / источник',
                'default' => 'all',
                'required' => false,
                'dictionary_query' => <<<'SQL'
SELECT 'all' AS value, 'Все сайты' AS label
UNION ALL
SELECT CAST(id AS CHAR) AS value, name AS label
FROM bs_cc_sources
WHERE is_active = 1
UNION ALL
SELECT 'local' AS value, '3 Пирога (local)' AS label
ORDER BY label
SQL,
                'dictionary_connection' => null,
                'dictionary_searchable' => true,
            ],
            [
                'key' => 'apply_changes',
                'type' => 'boolean',
                'label' => 'Применять изменения в БД',
                'default' => false,
                'required' => false,
            ],
            [
                'key' => 'only_without_zone',
                'type' => 'boolean',
                'label' => 'Только заказы без зоны',
                'default' => true,
                'required' => false,
            ],
            [
                'key' => 'use_street_fallback',
                'type' => 'boolean',
                'label' => 'Использовать определение по улице',
                'default' => true,
                'required' => false,
            ],
            [
                'key' => 'geocode_missing_coords',
                'type' => 'boolean',
                'label' => 'Пробовать геокодировать адреса без координат',
                'default' => false,
                'required' => false,
            ],
            [
                'key' => 'chunk_size',
                'type' => 'number',
                'label' => 'Размер батча',
                'default' => 200,
                'required' => true,
            ],
            [
                'key' => 'max_orders',
                'type' => 'number',
                'label' => 'Лимит заказов (0 = без лимита)',
                'default' => 0,
                'required' => false,
            ],
        ];

        $dataSources = [
            [
                'key' => 'operation',
                'type' => 'provider',
                'provider_class' => AlignOrderZonesOperationProvider::class,
                'enabled' => true,
            ],
        ];

        $templateBody = <<<'TWIG'
{% set op = datasets.operation|default({}) %}

<div class="report-title">Операция выравнивания зон по заказам</div>
<div class="report-period">
    Период фильтра: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong>
</div>

<table class="report" style="margin-top:12px;">
    <thead>
        <tr>
            <th>Показатель</th>
            <th class="num">Значение</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>Режим запуска</td><td class="num">{{ op.run_mode|default('dry_run') }}</td></tr>
        <tr><td>Обработано заказов</td><td class="num">{{ op.processed|default(0) }}</td></tr>
        <tr><td>Обновлено заказов</td><td class="num">{{ op.updated|default(0) }}</td></tr>
        <tr><td>Ошибки</td><td class="num">{{ op.errors|default(0) }}</td></tr>
        <tr><td>Зона определена по координатам</td><td class="num">{{ op.coords_found|default(0) }}</td></tr>
        <tr><td>Координаты получены геокодингом</td><td class="num">{{ op.coords_geocoded|default(0) }}</td></tr>
        <tr><td>Время запуска</td><td class="num">{{ op.started_at|default('-') }}</td></tr>
    </tbody>
</table>

<table class="report" style="margin-top:12px;">
    <thead>
        <tr>
            <th>Зона</th>
            <th class="num">Кол-во заказов</th>
        </tr>
    </thead>
    <tbody>
        {% if op.zone_rows is defined and op.zone_rows|length %}
            {% for row in op.zone_rows %}
                <tr>
                    <td>{{ row.zone_name|default('Unknown') }}</td>
                    <td class="num">{{ row.orders_count|default(0) }}</td>
                </tr>
            {% endfor %}
        {% else %}
            <tr><td colspan="2">Нет данных</td></tr>
        {% endif %}
    </tbody>
</table>

<table class="report" style="margin-top:12px;">
    <thead>
        <tr>
            <th>Метод определения</th>
            <th class="num">Кол-во заказов</th>
        </tr>
    </thead>
    <tbody>
        {% if op.method_rows is defined and op.method_rows|length %}
            {% for row in op.method_rows %}
                <tr>
                    <td>{{ row.method|default('unknown') }}</td>
                    <td class="num">{{ row.orders_count|default(0) }}</td>
                </tr>
            {% endfor %}
        {% else %}
            <tr><td colspan="2">Нет данных</td></tr>
        {% endif %}
    </tbody>
</table>

<table class="report" style="margin-top:12px;">
    <thead>
        <tr>
            <th>ID заказа</th>
            <th>Номер</th>
            <th>Улица</th>
            <th>Дом</th>
            <th class="num">Доставка</th>
        </tr>
    </thead>
    <tbody>
        {% if op.unknown_rows is defined and op.unknown_rows|length %}
            {% for row in op.unknown_rows %}
                <tr>
                    <td>{{ row.order_id|default(0) }}</td>
                    <td>{{ row.order_number|default('-') }}</td>
                    <td>{{ row.street|default('-') }}</td>
                    <td>{{ row.house|default('-') }}</td>
                    <td class="num">{{ row.shipping_price|default(0)|number_format(2, '.', ' ') }}</td>
                </tr>
            {% endfor %}
        {% else %}
            <tr><td colspan="5">Заказы с неразрешенной зоной не найдены</td></tr>
        {% endif %}
    </tbody>
</table>
TWIG;

        DB::table('bs_print_templates')->updateOrInsert(
            ['code' => 'operation_align_order_zones'],
            [
                'name' => 'Операция выравнивания зон по заказам',
                'type' => 'report',
                'report_group_id' => $groupId > 0 ? $groupId : null,
                'engine' => 'twig',
                'output_format' => 'pdf',
                'default_paper_preset' => 'a4',
                'default_margin_top_mm' => 8,
                'default_margin_right_mm' => 8,
                'default_margin_bottom_mm' => 8,
                'default_margin_left_mm' => 8,
                'editor_mode' => 'code',
                'css_preset' => 'report_table_default',
                'description' => 'Операция заполнения зон заказов по координатам, цене доставки и эвристике улицы.',
                'template_body' => $templateBody,
                'parameters_schema' => json_encode($parametersSchema, JSON_UNESCAPED_UNICODE),
                'data_sources' => json_encode($dataSources, JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('bs_print_templates')
            ->where('code', 'operation_align_order_zones')
            ->delete();
    }
};
