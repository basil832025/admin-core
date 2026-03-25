<?php

use App\Reports\DataProviders\RevenueByGroupChartProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('bs_print_templates')
            ->where('code', 'vutog_group')
            ->first(['id', 'data_sources']);

        if (! $template) {
            return;
        }

        $dataSources = json_decode((string) ($template->data_sources ?? ''), true);
        if (! is_array($dataSources)) {
            $dataSources = [];
        }

        $hasChartSource = false;
        foreach ($dataSources as $source) {
            if (is_array($source) && (string) ($source['key'] ?? '') === 'chart') {
                $hasChartSource = true;
                break;
            }
        }

        if (! $hasChartSource) {
            $dataSources[] = [
                'key' => 'chart',
                'type' => 'provider',
                'provider_class' => RevenueByGroupChartProvider::class,
                'enabled' => true,
            ];
        }

        $templateBody = <<<'TWIG'
<div class="report-title">Звіт: Виторг по групах</div>
<div class="report-period">
    Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong>
</div>
{% set sum_qty = 0 %}
{% set sum_subtotal = 0 %}
{% set sum_revenue = 0 %}
<table class="report">
    <thead>
        <tr>
            <th>Група</th>
            <th class="num">К-сть</th>
            <th class="num">Сума до знижки</th>
            <th class="num">Знижка</th>
            <th class="num">Виторг</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.main is defined and datasets.main|length %}
            {% for row in datasets.main %}
                {% set qty = row.qty_total|default(0) %}
                {% set subtotal = row.subtotal_total|default(0) %}
                {% set discount = row.discount_total|default(0) %}
                {% set revenue = row.revenue_total|default(0) %}
                {% set sum_qty = sum_qty + qty %}
                {% set sum_subtotal = sum_subtotal + subtotal %}
                {% set sum_revenue = sum_revenue + revenue %}
                <tr>
                    <td>{{ row.group_name|default('Без групи') }}</td>
                    <td class="num">{{ qty }}</td>
                    <td class="num">{{ subtotal|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ discount|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ revenue|number_format(2, '.', ' ') }}</td>
                </tr>
            {% endfor %}
        {% else %}
            <tr>
                <td colspan="5">За обраний період дані відсутні.</td>
            </tr>
        {% endif %}
        <tr class="total-row">
            <td>Разом</td>
            <td class="num">{{ sum_qty }}</td>
            <td class="num">{{ sum_subtotal|number_format(2, '.', ' ') }}</td>
            <td class="num">&nbsp;</td>
            <td class="num">{{ sum_revenue|number_format(2, '.', ' ') }}</td>
        </tr>
    </tbody>
</table>

{% set chart = datasets.chart|default({}) %}
{% if chart.values is defined and chart.values|length %}
<table style="width:100%;border-collapse:collapse;margin-top:12px;">
    <tbody>
        <tr>
            <td style="width:40%;vertical-align:top;">
                <div style="font-size:16px;font-weight:700;text-align:center;margin-bottom:8px;">Категорія товарів</div>
                <div style="width:220px;height:220px;margin:0 auto;">
                    <img alt="chart" src="{{ chart_donut_png(chart.values, chart.colors, chart.total|number_format(0, '.', ''))|e }}" style="display:block;width:220px;height:220px;" />
                </div>
            </td>
            <td style="width:60%;vertical-align:top;">
                <table class="report" style="font-size:11px;">
                    <thead>
                        <tr>
                            <th>Категорія</th>
                            <th class="num">Виторг</th>
                            <th class="num">Доля, %</th>
                        </tr>
                    </thead>
                    <tbody>
                        {% for item in chart.rows %}
                            <tr>
                                <td><span style="display:inline-block;width:10px;height:10px;background:{{ item.color|default('#4f81bd') }};margin-right:6px;"></span>{{ item.group_name|default('Без групи') }}</td>
                                <td class="num">{{ item.revenue_total|default(0)|number_format(2, '.', ' ') }}</td>
                                <td class="num">{{ item.percent|default(0)|number_format(2, '.', ' ') }}</td>
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
                'data_sources' => json_encode($dataSources, JSON_UNESCAPED_UNICODE),
                'template_body' => $templateBody,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // no-op
    }
};
