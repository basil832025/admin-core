<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $templateBody = <<<'TWIG'
<div class="report-title">Звіт: Воронка статусів замовлень</div>
<div class="report-period">Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong></div>
{% set status_labels = {
    'cart': 'Кошик',
    'new': 'Нове',
    'processing': 'В обробці',
    'on_hold': 'Відкладено',
    'filling': 'Начинка',
    'molding': 'Ліпка',
    'baking': 'Піч',
    'prepared': 'Приготовано',
    'assembled': 'Зібрано',
    'shipped': 'В дорозі',
    'delivered': 'Доставлено',
    'cancelled': 'Скасовано'
} %}
{% set total_orders = 0 %}
{% set delivered_orders = 0 %}
{% set cancelled_orders = 0 %}
<table class="report">
    <thead>
        <tr>
            <th>Статус</th>
            <th class="num">К-сть</th>
            <th class="num">Виторг</th>
            <th class="num">Частка, %</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.main is defined and datasets.main|length %}
            {% for row in datasets.main %}
                {% set count = row.orders_count|default(0) %}
                {% set total_orders = total_orders + count %}
                {% if row.status|default('') == 'delivered' %}
                    {% set delivered_orders = delivered_orders + count %}
                {% endif %}
                {% if row.status|default('') == 'cancelled' %}
                    {% set cancelled_orders = cancelled_orders + count %}
                {% endif %}
            {% endfor %}
            {% for row in datasets.main %}
                {% set count = row.orders_count|default(0) %}
                {% set status_code = row.status|default('') %}
                <tr>
                    <td>{{ status_labels[status_code]|default(status_code != '' ? status_code : '-') }}</td>
                    <td class="num">{{ count }}</td>
                    <td class="num">{{ row.revenue_total|default(0)|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ (total_orders > 0 ? count * 100 / total_orders : 0)|number_format(2, '.', ' ') }}</td>
                </tr>
            {% endfor %}
        {% else %}
            <tr><td colspan="4">Дані за період відсутні.</td></tr>
        {% endif %}
    </tbody>
</table>

<div style="margin-top:8px;font-size:12px;">
    <strong>Конверсія у delivered:</strong> {{ (total_orders > 0 ? delivered_orders * 100 / total_orders : 0)|number_format(2, '.', ' ') }}% &nbsp;|&nbsp;
    <strong>Доля cancelled:</strong> {{ (total_orders > 0 ? cancelled_orders * 100 / total_orders : 0)|number_format(2, '.', ' ') }}%
</div>
TWIG;

        DB::table('bs_print_templates')
            ->where('code', 'sales_status_funnel')
            ->update([
                'template_body' => $templateBody,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $templateBody = <<<'TWIG'
<div class="report-title">Звіт: Воронка статусів замовлень</div>
<div class="report-period">Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong></div>
{% set total_orders = 0 %}
{% set delivered_orders = 0 %}
{% set cancelled_orders = 0 %}
<table class="report">
    <thead>
        <tr>
            <th>Статус</th>
            <th class="num">К-сть</th>
            <th class="num">Виторг</th>
            <th class="num">Частка, %</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.main is defined and datasets.main|length %}
            {% for row in datasets.main %}
                {% set count = row.orders_count|default(0) %}
                {% set total_orders = total_orders + count %}
                {% if row.status|default('') == 'delivered' %}
                    {% set delivered_orders = delivered_orders + count %}
                {% endif %}
                {% if row.status|default('') == 'cancelled' %}
                    {% set cancelled_orders = cancelled_orders + count %}
                {% endif %}
            {% endfor %}
            {% for row in datasets.main %}
                {% set count = row.orders_count|default(0) %}
                <tr>
                    <td>{{ row.status|default('-') }}</td>
                    <td class="num">{{ count }}</td>
                    <td class="num">{{ row.revenue_total|default(0)|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ (total_orders > 0 ? count * 100 / total_orders : 0)|number_format(2, '.', ' ') }}</td>
                </tr>
            {% endfor %}
        {% else %}
            <tr><td colspan="4">Дані за період відсутні.</td></tr>
        {% endif %}
    </tbody>
</table>

<div style="margin-top:8px;font-size:12px;">
    <strong>Конверсія у delivered:</strong> {{ (total_orders > 0 ? delivered_orders * 100 / total_orders : 0)|number_format(2, '.', ' ') }}% &nbsp;|&nbsp;
    <strong>Доля cancelled:</strong> {{ (total_orders > 0 ? cancelled_orders * 100 / total_orders : 0)|number_format(2, '.', ' ') }}%
</div>
TWIG;

        DB::table('bs_print_templates')
            ->where('code', 'sales_status_funnel')
            ->update([
                'template_body' => $templateBody,
                'updated_at' => now(),
            ]);
    }
};
