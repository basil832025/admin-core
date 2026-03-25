<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $templateBody = <<<'TWIG'
<div class="report-title">Звіт: Аналіз часу отримання і доставки замовлень</div>
<div class="report-period">Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong></div>

{% set received = (datasets.received is defined and datasets.received|length) ? datasets.received[0] : {} %}
{% set delivered = (datasets.delivered is defined and datasets.delivered|length) ? datasets.delivered[0] : {} %}

<table class="report" style="margin-bottom:12px;">
    <thead>
        <tr>
            <th>К-сть замовлень</th>
            <th class="num">Всього</th>
            <th class="num">09:00-11:59</th>
            <th class="num">12:00-14:00</th>
            <th class="num">14:01-17:59</th>
            <th class="num">18:00-20:00</th>
            <th class="num">Інше</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Оформлення замовлення (NEW)</td>
            <td class="num">{{ received.total_orders|default(0) }}</td>
            <td class="num">{{ received.slot_0900_1159|default(0) }}</td>
            <td class="num">{{ received.slot_1200_1400|default(0) }}</td>
            <td class="num">{{ received.slot_1401_1759|default(0) }}</td>
            <td class="num">{{ received.slot_1800_2000|default(0) }}</td>
            <td class="num">{{ received.slot_other|default(0) }}</td>
        </tr>
        <tr>
            <td>Доставка замовлення (DELIVERED)</td>
            <td class="num">{{ delivered.total_orders|default(0) }}</td>
            <td class="num">{{ delivered.slot_0900_1159|default(0) }}</td>
            <td class="num">{{ delivered.slot_1200_1400|default(0) }}</td>
            <td class="num">{{ delivered.slot_1401_1759|default(0) }}</td>
            <td class="num">{{ delivered.slot_1800_2000|default(0) }}</td>
            <td class="num">{{ delivered.slot_other|default(0) }}</td>
        </tr>
    </tbody>
</table>

{% set labels = ['09:00-11:59', '12:00-14:00', '14:01-17:59', '18:00-20:00', 'Інше'] %}
{% set colors = ['#4f81bd', '#c0504d', '#9bbb59', '#8064a2', '#4bacc6'] %}

{% set rec_total = received.total_orders|default(0) %}
{% set rec_1 = received.slot_0900_1159|default(0) %}
{% set rec_2 = received.slot_1200_1400|default(0) %}
{% set rec_3 = received.slot_1401_1759|default(0) %}
{% set rec_4 = received.slot_1800_2000|default(0) %}
{% set rec_5 = received.slot_other|default(0) %}
{% set rec_p1 = rec_total > 0 ? (rec_1 * 100 / rec_total) : 0 %}
{% set rec_p2 = rec_total > 0 ? (rec_2 * 100 / rec_total) : 0 %}
{% set rec_p3 = rec_total > 0 ? (rec_3 * 100 / rec_total) : 0 %}
{% set rec_p4 = rec_total > 0 ? (rec_4 * 100 / rec_total) : 0 %}
{% set rec_p5 = rec_total > 0 ? (rec_5 * 100 / rec_total) : 0 %}
{% set rec_a = rec_p1 %}
{% set rec_b = rec_p1 + rec_p2 %}
{% set rec_c = rec_p1 + rec_p2 + rec_p3 %}
{% set rec_d = rec_p1 + rec_p2 + rec_p3 + rec_p4 %}

{% set del_total = delivered.total_orders|default(0) %}
{% set del_1 = delivered.slot_0900_1159|default(0) %}
{% set del_2 = delivered.slot_1200_1400|default(0) %}
{% set del_3 = delivered.slot_1401_1759|default(0) %}
{% set del_4 = delivered.slot_1800_2000|default(0) %}
{% set del_5 = delivered.slot_other|default(0) %}
{% set del_p1 = del_total > 0 ? (del_1 * 100 / del_total) : 0 %}
{% set del_p2 = del_total > 0 ? (del_2 * 100 / del_total) : 0 %}
{% set del_p3 = del_total > 0 ? (del_3 * 100 / del_total) : 0 %}
{% set del_p4 = del_total > 0 ? (del_4 * 100 / del_total) : 0 %}
{% set del_p5 = del_total > 0 ? (del_5 * 100 / del_total) : 0 %}
{% set del_a = del_p1 %}
{% set del_b = del_p1 + del_p2 %}
{% set del_c = del_p1 + del_p2 + del_p3 %}
{% set del_d = del_p1 + del_p2 + del_p3 + del_p4 %}

<table class="report" style="margin-bottom:12px;">
    <tbody>
        <tr>
            <td style="width:50%;vertical-align:top;">
                <div style="font-weight:700;margin-bottom:6px;">Оформлення замовлення</div>
                <div style="width:220px;height:220px;margin:0 auto 10px;border-radius:50%;background:conic-gradient({{ colors[0] }} 0% {{ rec_a|number_format(4, '.', '') }}%, {{ colors[1] }} {{ rec_a|number_format(4, '.', '') }}% {{ rec_b|number_format(4, '.', '') }}%, {{ colors[2] }} {{ rec_b|number_format(4, '.', '') }}% {{ rec_c|number_format(4, '.', '') }}%, {{ colors[3] }} {{ rec_c|number_format(4, '.', '') }}% {{ rec_d|number_format(4, '.', '') }}%, {{ colors[4] }} {{ rec_d|number_format(4, '.', '') }}% 100%);position:relative;">
                    <div style="position:absolute;left:50%;top:50%;width:96px;height:96px;transform:translate(-50%, -50%);background:#fff;border-radius:50%;border:1px solid #e2e8f0;"></div>
                </div>
                <table class="report" style="font-size:11px;">
                    <tbody>
                        <tr><td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[0] }};margin-right:6px;"></span>09:00-11:59</td><td class="num">{{ rec_1 }}</td><td class="num">{{ rec_p1|number_format(2, '.', ' ') }}%</td></tr>
                        <tr><td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[1] }};margin-right:6px;"></span>12:00-14:00</td><td class="num">{{ rec_2 }}</td><td class="num">{{ rec_p2|number_format(2, '.', ' ') }}%</td></tr>
                        <tr><td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[2] }};margin-right:6px;"></span>14:01-17:59</td><td class="num">{{ rec_3 }}</td><td class="num">{{ rec_p3|number_format(2, '.', ' ') }}%</td></tr>
                        <tr><td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[3] }};margin-right:6px;"></span>18:00-20:00</td><td class="num">{{ rec_4 }}</td><td class="num">{{ rec_p4|number_format(2, '.', ' ') }}%</td></tr>
                        <tr><td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[4] }};margin-right:6px;"></span>Інше</td><td class="num">{{ rec_5 }}</td><td class="num">{{ rec_p5|number_format(2, '.', ' ') }}%</td></tr>
                    </tbody>
                </table>
            </td>
            <td style="width:50%;vertical-align:top;">
                <div style="font-weight:700;margin-bottom:6px;">Доставка замовлення</div>
                <div style="width:220px;height:220px;margin:0 auto 10px;border-radius:50%;background:conic-gradient({{ colors[0] }} 0% {{ del_a|number_format(4, '.', '') }}%, {{ colors[1] }} {{ del_a|number_format(4, '.', '') }}% {{ del_b|number_format(4, '.', '') }}%, {{ colors[2] }} {{ del_b|number_format(4, '.', '') }}% {{ del_c|number_format(4, '.', '') }}%, {{ colors[3] }} {{ del_c|number_format(4, '.', '') }}% {{ del_d|number_format(4, '.', '') }}%, {{ colors[4] }} {{ del_d|number_format(4, '.', '') }}% 100%);position:relative;">
                    <div style="position:absolute;left:50%;top:50%;width:96px;height:96px;transform:translate(-50%, -50%);background:#fff;border-radius:50%;border:1px solid #e2e8f0;"></div>
                </div>
                <table class="report" style="font-size:11px;">
                    <tbody>
                        <tr><td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[0] }};margin-right:6px;"></span>09:00-11:59</td><td class="num">{{ del_1 }}</td><td class="num">{{ del_p1|number_format(2, '.', ' ') }}%</td></tr>
                        <tr><td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[1] }};margin-right:6px;"></span>12:00-14:00</td><td class="num">{{ del_2 }}</td><td class="num">{{ del_p2|number_format(2, '.', ' ') }}%</td></tr>
                        <tr><td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[2] }};margin-right:6px;"></span>14:01-17:59</td><td class="num">{{ del_3 }}</td><td class="num">{{ del_p3|number_format(2, '.', ' ') }}%</td></tr>
                        <tr><td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[3] }};margin-right:6px;"></span>18:00-20:00</td><td class="num">{{ del_4 }}</td><td class="num">{{ del_p4|number_format(2, '.', ' ') }}%</td></tr>
                        <tr><td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[4] }};margin-right:6px;"></span>Інше</td><td class="num">{{ del_5 }}</td><td class="num">{{ del_p5|number_format(2, '.', ' ') }}%</td></tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>
TWIG;

        DB::table('bs_print_templates')
            ->where('code', 'sales_receiving_delivery_time_analysis')
            ->update([
                'template_body' => $templateBody,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // No-op rollback for template body visual tweak.
    }
};
