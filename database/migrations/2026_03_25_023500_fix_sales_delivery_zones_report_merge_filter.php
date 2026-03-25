<?php

use App\Reports\DataProviders\DeliveryZonesChartProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('bs_print_templates')
            ->where('code', 'sales_delivery_zones_daily')
            ->first(['id', 'data_sources']);

        if (! $template) {
            return;
        }

        $sources = json_decode((string) ($template->data_sources ?? ''), true);
        if (! is_array($sources)) {
            $sources = [];
        }

        $newSources = [];
        foreach ($sources as $source) {
            if (! is_array($source)) {
                continue;
            }

            if ((string) ($source['key'] ?? '') === 'chart') {
                $newSources[] = [
                    'key' => 'chart',
                    'type' => 'provider',
                    'provider_class' => DeliveryZonesChartProvider::class,
                    'enabled' => true,
                ];
                continue;
            }

            $newSources[] = $source;
        }

        $hasChart = false;
        foreach ($newSources as $source) {
            if ((string) ($source['key'] ?? '') === 'chart') {
                $hasChart = true;
                break;
            }
        }

        if (! $hasChart) {
            $newSources[] = [
                'key' => 'chart',
                'type' => 'provider',
                'provider_class' => DeliveryZonesChartProvider::class,
                'enabled' => true,
            ];
        }

        $templateBody = <<<'TWIG'
<div class="report-title">Звіт: Доставки по зонам (по даті доставки)</div>
<div class="report-period">
    Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong>
</div>

{% set total_deliveries = 0 %}
{% set total_sum = 0 %}

<table class="report" style="margin-top:12px;">
    <thead>
        <tr>
            <th>Дата</th>
            <th>Зона</th>
            <th class="num">К-во доставок</th>
            <th class="num">Сумма заказов</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.main is defined and datasets.main|length %}
            {% for row in datasets.main %}
                {% set deliveries = row.deliveries_count|default(0) %}
                {% set amount = row.orders_sum|default(0) %}
                {% set total_deliveries = total_deliveries + deliveries %}
                {% set total_sum = total_sum + amount %}
                <tr>
                    <td>{{ row.report_date|default('-') }}</td>
                    <td>{{ row.zone_name|default('Unknown') }}</td>
                    <td class="num">{{ deliveries }}</td>
                    <td class="num">{{ amount|number_format(2, '.', ' ') }}</td>
                </tr>
            {% endfor %}
            <tr class="total-row">
                <td colspan="2">Разом</td>
                <td class="num">{{ total_deliveries }}</td>
                <td class="num">{{ total_sum|number_format(2, '.', ' ') }}</td>
            </tr>
        {% else %}
            <tr>
                <td colspan="4">За выбранный период данных нет.</td>
            </tr>
        {% endif %}
    </tbody>
</table>

{% set chart = datasets.chart|default({}) %}
{% if chart.values is defined and chart.values|length %}
<table style="width:100%;border-collapse:collapse;margin-top:14px;">
    <tbody>
        <tr>
            <td style="width:38%;vertical-align:top;">
                <div style="font-size:15px;font-weight:700;text-align:center;margin-bottom:8px;">Доля доставок по зонам</div>
                <div style="width:260px;height:260px;margin:0 auto;">
                    <img alt="chart" src="{{ chart_donut_png(chart.values, chart.colors, chart.total|default(0))|e }}" style="display:block;width:260px;height:260px;" />
                </div>
            </td>
            <td style="width:62%;vertical-align:top;">
                <table class="report" style="font-size:11px;">
                    <thead>
                        <tr>
                            <th>Зона</th>
                            <th class="num">К-во</th>
                            <th class="num">Доля, %</th>
                            <th class="num">Сумма</th>
                        </tr>
                    </thead>
                    <tbody>
                        {% for row in chart.rows|default([]) %}
                            <tr>
                                <td><span style="display:inline-block;width:10px;height:10px;background:{{ row.zone_color|default('#4f81bd') }};margin-right:6px;"></span>{{ row.zone_name|default('Unknown') }}</td>
                                <td class="num">{{ row.deliveries_count|default(0) }}</td>
                                <td class="num">{{ row.percent|default(0)|number_format(2, '.', ' ') }}</td>
                                <td class="num">{{ row.orders_sum|default(0)|number_format(2, '.', ' ') }}</td>
                            </tr>
                        {% endfor %}
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>
{% endif %}
TWIG;

        DB::table('bs_print_templates')
            ->where('id', (int) $template->id)
            ->update([
                'data_sources' => json_encode($newSources, JSON_UNESCAPED_UNICODE),
                'template_body' => $templateBody,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // no-op
    }
};
