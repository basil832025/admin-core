<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('bs_print_templates')
            ->where('code', 'cashier_revenue_by_product')
            ->first(['id']);

        if (! $template) {
            return;
        }

        $templateBody = <<<'TWIG'
<div class="report-title">Звіт: Виторг по касі</div>
<div class="report-period">Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong></div>

{% set total_qty = 0 %}
{% set total_gross = 0 %}
{% set total_discount = 0 %}
{% set total_bonus_spent = 0 %}
{% set total_bonus_accrued = 0 %}
{% set total_revenue = 0 %}
{% set pay_CASH = 0 %}
{% set pay_CARD = 0 %}
{% set pay_POS = 0 %}
{% set pay_LIQPAY = 0 %}
{% set pay_INVOICE = 0 %}
{% set pay_ORG = 0 %}
{% set pay_CLUB = 0 %}
{% set pay_FREE = 0 %}
{% set pay_OTHER = 0 %}

<table class="report" style="margin-top:12px;">
  <thead>
    <tr>
      <th style="width:88px;">Артикул</th>
      <th>Назва</th>
      <th class="num" style="width:70px;">К-сть</th>
      <th class="num" style="width:110px;">Сума</th>
      <th class="num" style="width:110px;">Знижка</th>
      <th class="num" style="width:110px;">Бонуси</th>
      <th class="num" style="width:120px;">Виторг</th>
      <th class="num" style="width:92px;">CASH</th>
      <th class="num" style="width:92px;">CARD</th>
      <th class="num" style="width:92px;">POS</th>
      <th class="num" style="width:92px;">LIQPAY</th>
      <th class="num" style="width:78px;">INV</th>
      <th class="num" style="width:78px;">ORG</th>
      <th class="num" style="width:86px;">CLUB</th>
      <th class="num" style="width:78px;">FREE</th>
      <th class="num" style="width:92px;">OTHER</th>
    </tr>
  </thead>
  <tbody>
    {% if datasets.main is defined and datasets.main|length %}
      {% for row in datasets.main %}
        {% set total_qty = total_qty + (row.qty|default(0)) %}
        {% set total_gross = total_gross + (row.gross|default(0)) %}
        {% set total_discount = total_discount + (row.discount|default(0)) %}
        {% set total_bonus_spent = total_bonus_spent + (row.bonuses_spent|default(0)) %}
        {% set total_bonus_accrued = total_bonus_accrued + (row.bonuses_accrued|default(0)) %}
        {% set total_revenue = total_revenue + (row.revenue|default(0)) %}
        {% set pay_CASH = pay_CASH + (row.pay_CASH|default(0)) %}
        {% set pay_CARD = pay_CARD + (row.pay_CARD|default(0)) %}
        {% set pay_POS = pay_POS + (row.pay_POS|default(0)) %}
        {% set pay_LIQPAY = pay_LIQPAY + (row.pay_LIQPAY|default(0)) %}
        {% set pay_INVOICE = pay_INVOICE + (row.pay_INVOICE|default(0)) %}
        {% set pay_ORG = pay_ORG + (row.pay_ORG|default(0)) %}
        {% set pay_CLUB = pay_CLUB + (row.pay_CLUB|default(0)) %}
        {% set pay_FREE = pay_FREE + (row.pay_FREE|default(0)) %}
        {% set pay_OTHER = pay_OTHER + (row.pay_OTHER|default(0)) %}

        <tr>
          <td>{{ row.sku|default('-') }}</td>
          <td>{{ row.title|default('-') }}</td>
          <td class="num">{{ row.qty|default(0) }}</td>
          <td class="num">{{ (row.gross|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.discount|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.bonuses_spent|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.revenue|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.pay_CASH|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.pay_CARD|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.pay_POS|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.pay_LIQPAY|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.pay_INVOICE|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.pay_ORG|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.pay_CLUB|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.pay_FREE|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.pay_OTHER|default(0))|number_format(2, '.', ' ') }}</td>
        </tr>
      {% endfor %}
    {% else %}
      <tr><td colspan="16">Дані за вибраний період відсутні.</td></tr>
    {% endif %}
  </tbody>
  {% if datasets.main is defined and datasets.main|length %}
    <tfoot>
      <tr class="total-row">
        <td colspan="2"><strong>Разом</strong></td>
        <td class="num">{{ total_qty }}</td>
        <td class="num">{{ total_gross|number_format(2, '.', ' ') }}</td>
        <td class="num">{{ total_discount|number_format(2, '.', ' ') }}</td>
        <td class="num">{{ total_bonus_spent|number_format(2, '.', ' ') }}</td>
        <td class="num">{{ total_revenue|number_format(2, '.', ' ') }}</td>
        <td class="num">{{ pay_CASH|number_format(2, '.', ' ') }}</td>
        <td class="num">{{ pay_CARD|number_format(2, '.', ' ') }}</td>
        <td class="num">{{ pay_POS|number_format(2, '.', ' ') }}</td>
        <td class="num">{{ pay_LIQPAY|number_format(2, '.', ' ') }}</td>
        <td class="num">{{ pay_INVOICE|number_format(2, '.', ' ') }}</td>
        <td class="num">{{ pay_ORG|number_format(2, '.', ' ') }}</td>
        <td class="num">{{ pay_CLUB|number_format(2, '.', ' ') }}</td>
        <td class="num">{{ pay_FREE|number_format(2, '.', ' ') }}</td>
        <td class="num">{{ pay_OTHER|number_format(2, '.', ' ') }}</td>
      </tr>
    </tfoot>
  {% endif %}
</table>

<table class="report" style="margin-top:12px;">
  <thead>
    <tr>
      <th>Підсумки</th>
      <th class="num">Значення</th>
    </tr>
  </thead>
  <tbody>
    <tr><td><strong>Загальна сума (без знижок)</strong></td><td class="num">{{ total_gross|number_format(2, '.', ' ') }}</td></tr>
    <tr><td><strong>Сума знижки</strong></td><td class="num">{{ total_discount|number_format(2, '.', ' ') }}</td></tr>
    <tr><td><strong>Списано бонусів</strong></td><td class="num">{{ total_bonus_spent|number_format(2, '.', ' ') }}</td></tr>
    <tr><td><strong>Нараховано бонусів</strong></td><td class="num">{{ total_bonus_accrued|number_format(2, '.', ' ') }}</td></tr>
    <tr class="total-row"><td><strong>Виторг по касі</strong></td><td class="num">{{ total_revenue|number_format(2, '.', ' ') }}</td></tr>
  </tbody>
</table>

<table class="report" style="margin-top:12px;">
  <thead>
    <tr>
      <th colspan="2">Розшифровка по оплатах (виторг)</th>
    </tr>
  </thead>
  <tbody>
    <tr><td>CASH</td><td class="num">{{ pay_CASH|number_format(2, '.', ' ') }}</td></tr>
    <tr><td>CARD</td><td class="num">{{ pay_CARD|number_format(2, '.', ' ') }}</td></tr>
    <tr><td>POS</td><td class="num">{{ pay_POS|number_format(2, '.', ' ') }}</td></tr>
    <tr><td>LIQPAY</td><td class="num">{{ pay_LIQPAY|number_format(2, '.', ' ') }}</td></tr>
    <tr><td>INVOICE</td><td class="num">{{ pay_INVOICE|number_format(2, '.', ' ') }}</td></tr>
    <tr><td>ORG</td><td class="num">{{ pay_ORG|number_format(2, '.', ' ') }}</td></tr>
    <tr><td>CLUB</td><td class="num">{{ pay_CLUB|number_format(2, '.', ' ') }}</td></tr>
    <tr><td>FREE</td><td class="num">{{ pay_FREE|number_format(2, '.', ' ') }}</td></tr>
    <tr><td>OTHER</td><td class="num">{{ pay_OTHER|number_format(2, '.', ' ') }}</td></tr>
    <tr class="total-row"><td><strong>Разом</strong></td><td class="num">{{ total_revenue|number_format(2, '.', ' ') }}</td></tr>
  </tbody>
</table>
TWIG;

        DB::table('bs_print_templates')
            ->where('id', (int) $template->id)
            ->update([
                'template_body' => $templateBody,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // no-op
    }
};
