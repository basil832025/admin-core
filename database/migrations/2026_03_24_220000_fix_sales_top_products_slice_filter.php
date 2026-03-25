<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $templateBody = <<<'TWIG'
<div class="report-title">Звіт: Топ товарів по виторгу</div>
<div class="report-period">Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong></div>
{% set limit = params.top_limit|default(20) %}
{% set sum_qty = 0 %}
{% set sum_revenue = 0 %}
<table class="report">
    <thead>
        <tr>
            <th>#</th>
            <th>Товар</th>
            <th class="num">К-сть</th>
            <th class="num">Сер. ціна</th>
            <th class="num">Виторг</th>
            <th class="num">Частка, %</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.main is defined and datasets.main|length %}
            {% for row in datasets.main %}
                {% if loop.index <= limit %}
                    {% set sum_qty = sum_qty + (row.qty_total|default(0)) %}
                    {% set sum_revenue = sum_revenue + (row.revenue_total|default(0)) %}
                {% endif %}
            {% endfor %}

            {% for row in datasets.main %}
                {% if loop.index <= limit %}
                    {% set revenue = row.revenue_total|default(0) %}
                    <tr>
                        <td>{{ loop.index }}</td>
                        <td>{{ row.product_name|default('Товар') }}</td>
                        <td class="num">{{ row.qty_total|default(0)|number_format(2, '.', ' ') }}</td>
                        <td class="num">{{ row.avg_unit_price|default(0)|number_format(2, '.', ' ') }}</td>
                        <td class="num">{{ revenue|number_format(2, '.', ' ') }}</td>
                        <td class="num">{{ (sum_revenue > 0 ? (revenue * 100 / sum_revenue) : 0)|number_format(2, '.', ' ') }}</td>
                    </tr>
                {% endif %}
            {% endfor %}
        {% else %}
            <tr><td colspan="6">Дані за період відсутні.</td></tr>
        {% endif %}
        <tr class="total-row">
            <td colspan="2">Разом (Top {{ limit }})</td>
            <td class="num">{{ sum_qty|number_format(2, '.', ' ') }}</td>
            <td class="num">&nbsp;</td>
            <td class="num">{{ sum_revenue|number_format(2, '.', ' ') }}</td>
            <td class="num">100.00</td>
        </tr>
    </tbody>
</table>
TWIG;

        DB::table('bs_print_templates')
            ->where('code', 'sales_top_products')
            ->update([
                'template_body' => $templateBody,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $templateBody = <<<'TWIG'
<div class="report-title">Звіт: Топ товарів по виторгу</div>
<div class="report-period">Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong></div>
{% set limit = params.top_limit|default(20) %}
{% set sum_qty = 0 %}
{% set sum_revenue = 0 %}
<table class="report">
    <thead>
        <tr>
            <th>#</th>
            <th>Товар</th>
            <th class="num">К-сть</th>
            <th class="num">Сер. ціна</th>
            <th class="num">Виторг</th>
            <th class="num">Частка, %</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.main is defined and datasets.main|length %}
            {% set rows = datasets.main|slice(0, limit) %}
            {% for row in rows %}
                {% set sum_qty = sum_qty + (row.qty_total|default(0)) %}
                {% set sum_revenue = sum_revenue + (row.revenue_total|default(0)) %}
            {% endfor %}
            {% for row in rows %}
                {% set revenue = row.revenue_total|default(0) %}
                <tr>
                    <td>{{ loop.index }}</td>
                    <td>{{ row.product_name|default('Товар') }}</td>
                    <td class="num">{{ row.qty_total|default(0)|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ row.avg_unit_price|default(0)|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ revenue|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ (sum_revenue > 0 ? (revenue * 100 / sum_revenue) : 0)|number_format(2, '.', ' ') }}</td>
                </tr>
            {% endfor %}
        {% else %}
            <tr><td colspan="6">Дані за період відсутні.</td></tr>
        {% endif %}
        <tr class="total-row">
            <td colspan="2">Разом (Top {{ limit }})</td>
            <td class="num">{{ sum_qty|number_format(2, '.', ' ') }}</td>
            <td class="num">&nbsp;</td>
            <td class="num">{{ sum_revenue|number_format(2, '.', ' ') }}</td>
            <td class="num">100.00</td>
        </tr>
    </tbody>
</table>
TWIG;

        DB::table('bs_print_templates')
            ->where('code', 'sales_top_products')
            ->update([
                'template_body' => $templateBody,
                'updated_at' => now(),
            ]);
    }
};
