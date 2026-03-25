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
{% set dash_scale = 4.398229715 %}

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
{% set rec_l1 = rec_p1 * dash_scale %}
{% set rec_l2 = rec_p2 * dash_scale %}
{% set rec_l3 = rec_p3 * dash_scale %}
{% set rec_l4 = rec_p4 * dash_scale %}
{% set rec_l5 = rec_p5 * dash_scale %}
{% set rec_off2 = rec_l1 %}
{% set rec_off3 = rec_l1 + rec_l2 %}
{% set rec_off4 = rec_l1 + rec_l2 + rec_l3 %}
{% set rec_off5 = rec_l1 + rec_l2 + rec_l3 + rec_l4 %}
{% set rec_m1 = (rec_p1 / 2) * 3.6 %}
{% set rec_m2 = (rec_p1 + rec_p2 / 2) * 3.6 %}
{% set rec_m3 = (rec_p1 + rec_p2 + rec_p3 / 2) * 3.6 %}
{% set rec_m4 = (rec_p1 + rec_p2 + rec_p3 + rec_p4 / 2) * 3.6 %}
{% set rec_m5 = (rec_p1 + rec_p2 + rec_p3 + rec_p4 + rec_p5 / 2) * 3.6 %}

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
{% set del_l1 = del_p1 * dash_scale %}
{% set del_l2 = del_p2 * dash_scale %}
{% set del_l3 = del_p3 * dash_scale %}
{% set del_l4 = del_p4 * dash_scale %}
{% set del_l5 = del_p5 * dash_scale %}
{% set del_off2 = del_l1 %}
{% set del_off3 = del_l1 + del_l2 %}
{% set del_off4 = del_l1 + del_l2 + del_l3 %}
{% set del_off5 = del_l1 + del_l2 + del_l3 + del_l4 %}
{% set del_m1 = (del_p1 / 2) * 3.6 %}
{% set del_m2 = (del_p1 + del_p2 / 2) * 3.6 %}
{% set del_m3 = (del_p1 + del_p2 + del_p3 / 2) * 3.6 %}
{% set del_m4 = (del_p1 + del_p2 + del_p3 + del_p4 / 2) * 3.6 %}
{% set del_m5 = (del_p1 + del_p2 + del_p3 + del_p4 + del_p5 / 2) * 3.6 %}

<table style="width:100%;border-collapse:separate;border-spacing:0 8px;">
    <tbody>
        <tr>
            <td style="width:50%;vertical-align:top;padding-right:8px;">
                <div style="font-weight:700;font-size:16px;text-align:center;margin-bottom:6px;">Оформлення замовлення</div>
                <table style="width:100%;border-collapse:collapse;">
                    <tbody>
                        <tr>
                            <td style="width:235px;vertical-align:top;">
                                <div style="position:relative;width:220px;height:220px;margin:0 auto;">
                                    <svg width="220" height="220" viewBox="0 0 220 220" xmlns="http://www.w3.org/2000/svg" style="display:block;">
                                        <g transform="translate(110 110) rotate(-90)">
                                            <circle cx="0" cy="0" r="70" fill="none" stroke="#e2e8f0" stroke-width="44" />
                                            {% if rec_p1 > 0 %}<circle cx="0" cy="0" r="70" fill="none" stroke="{{ colors[0] }}" stroke-width="44" stroke-dasharray="{{ rec_l1|number_format(4, '.', '') }} 1000" stroke-dashoffset="0" />{% endif %}
                                            {% if rec_p2 > 0 %}<circle cx="0" cy="0" r="70" fill="none" stroke="{{ colors[1] }}" stroke-width="44" stroke-dasharray="{{ rec_l2|number_format(4, '.', '') }} 1000" stroke-dashoffset="-{{ rec_off2|number_format(4, '.', '') }}" />{% endif %}
                                            {% if rec_p3 > 0 %}<circle cx="0" cy="0" r="70" fill="none" stroke="{{ colors[2] }}" stroke-width="44" stroke-dasharray="{{ rec_l3|number_format(4, '.', '') }} 1000" stroke-dashoffset="-{{ rec_off3|number_format(4, '.', '') }}" />{% endif %}
                                            {% if rec_p4 > 0 %}<circle cx="0" cy="0" r="70" fill="none" stroke="{{ colors[3] }}" stroke-width="44" stroke-dasharray="{{ rec_l4|number_format(4, '.', '') }} 1000" stroke-dashoffset="-{{ rec_off4|number_format(4, '.', '') }}" />{% endif %}
                                            {% if rec_p5 > 0 %}<circle cx="0" cy="0" r="70" fill="none" stroke="{{ colors[4] }}" stroke-width="44" stroke-dasharray="{{ rec_l5|number_format(4, '.', '') }} 1000" stroke-dashoffset="-{{ rec_off5|number_format(4, '.', '') }}" />{% endif %}
                                        </g>
                                        <circle cx="110" cy="110" r="38" fill="#ffffff" stroke="#e2e8f0" stroke-width="1" />
                                        <text x="110" y="114" text-anchor="middle" style="font-size:11px;font-weight:700;fill:#334155;">{{ rec_total }}</text>
                                    </svg>

                                    {% if rec_p1 >= 4 %}<div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%) rotate({{ (rec_m1 - 90)|number_format(2, '.', '') }}deg) translate(86px,0) rotate({{ (90 - rec_m1)|number_format(2, '.', '') }}deg);font-size:11px;font-weight:700;color:#1f2937;background:#fff;padding:1px 4px;border-radius:8px;border:1px solid #cbd5e1;">{{ rec_p1|number_format(0, '.', ' ') }}%</div>{% endif %}
                                    {% if rec_p2 >= 4 %}<div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%) rotate({{ (rec_m2 - 90)|number_format(2, '.', '') }}deg) translate(86px,0) rotate({{ (90 - rec_m2)|number_format(2, '.', '') }}deg);font-size:11px;font-weight:700;color:#1f2937;background:#fff;padding:1px 4px;border-radius:8px;border:1px solid #cbd5e1;">{{ rec_p2|number_format(0, '.', ' ') }}%</div>{% endif %}
                                    {% if rec_p3 >= 4 %}<div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%) rotate({{ (rec_m3 - 90)|number_format(2, '.', '') }}deg) translate(86px,0) rotate({{ (90 - rec_m3)|number_format(2, '.', '') }}deg);font-size:11px;font-weight:700;color:#1f2937;background:#fff;padding:1px 4px;border-radius:8px;border:1px solid #cbd5e1;">{{ rec_p3|number_format(0, '.', ' ') }}%</div>{% endif %}
                                    {% if rec_p4 >= 4 %}<div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%) rotate({{ (rec_m4 - 90)|number_format(2, '.', '') }}deg) translate(86px,0) rotate({{ (90 - rec_m4)|number_format(2, '.', '') }}deg);font-size:11px;font-weight:700;color:#1f2937;background:#fff;padding:1px 4px;border-radius:8px;border:1px solid #cbd5e1;">{{ rec_p4|number_format(0, '.', ' ') }}%</div>{% endif %}
                                    {% if rec_p5 >= 4 %}<div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%) rotate({{ (rec_m5 - 90)|number_format(2, '.', '') }}deg) translate(86px,0) rotate({{ (90 - rec_m5)|number_format(2, '.', '') }}deg);font-size:11px;font-weight:700;color:#1f2937;background:#fff;padding:1px 4px;border-radius:8px;border:1px solid #cbd5e1;">{{ rec_p5|number_format(0, '.', ' ') }}%</div>{% endif %}
                                </div>
                            </td>
                            <td style="vertical-align:top;">
                                <table class="report" style="font-size:11px;">
                                    <tbody>
                                        <tr><td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[0] }};margin-right:6px;"></span>{{ labels[0] }}</td><td class="num">{{ rec_1 }}</td><td class="num">{{ rec_p1|number_format(2, '.', ' ') }}%</td></tr>
                                        <tr><td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[1] }};margin-right:6px;"></span>{{ labels[1] }}</td><td class="num">{{ rec_2 }}</td><td class="num">{{ rec_p2|number_format(2, '.', ' ') }}%</td></tr>
                                        <tr><td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[2] }};margin-right:6px;"></span>{{ labels[2] }}</td><td class="num">{{ rec_3 }}</td><td class="num">{{ rec_p3|number_format(2, '.', ' ') }}%</td></tr>
                                        <tr><td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[3] }};margin-right:6px;"></span>{{ labels[3] }}</td><td class="num">{{ rec_4 }}</td><td class="num">{{ rec_p4|number_format(2, '.', ' ') }}%</td></tr>
                                        <tr><td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[4] }};margin-right:6px;"></span>{{ labels[4] }}</td><td class="num">{{ rec_5 }}</td><td class="num">{{ rec_p5|number_format(2, '.', ' ') }}%</td></tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>

            <td style="width:50%;vertical-align:top;padding-left:8px;">
                <div style="font-weight:700;font-size:16px;text-align:center;margin-bottom:6px;">Доставка замовлення</div>
                <table style="width:100%;border-collapse:collapse;">
                    <tbody>
                        <tr>
                            <td style="width:235px;vertical-align:top;">
                                <div style="position:relative;width:220px;height:220px;margin:0 auto;">
                                    <svg width="220" height="220" viewBox="0 0 220 220" xmlns="http://www.w3.org/2000/svg" style="display:block;">
                                        <g transform="translate(110 110) rotate(-90)">
                                            <circle cx="0" cy="0" r="70" fill="none" stroke="#e2e8f0" stroke-width="44" />
                                            {% if del_p1 > 0 %}<circle cx="0" cy="0" r="70" fill="none" stroke="{{ colors[0] }}" stroke-width="44" stroke-dasharray="{{ del_l1|number_format(4, '.', '') }} 1000" stroke-dashoffset="0" />{% endif %}
                                            {% if del_p2 > 0 %}<circle cx="0" cy="0" r="70" fill="none" stroke="{{ colors[1] }}" stroke-width="44" stroke-dasharray="{{ del_l2|number_format(4, '.', '') }} 1000" stroke-dashoffset="-{{ del_off2|number_format(4, '.', '') }}" />{% endif %}
                                            {% if del_p3 > 0 %}<circle cx="0" cy="0" r="70" fill="none" stroke="{{ colors[2] }}" stroke-width="44" stroke-dasharray="{{ del_l3|number_format(4, '.', '') }} 1000" stroke-dashoffset="-{{ del_off3|number_format(4, '.', '') }}" />{% endif %}
                                            {% if del_p4 > 0 %}<circle cx="0" cy="0" r="70" fill="none" stroke="{{ colors[3] }}" stroke-width="44" stroke-dasharray="{{ del_l4|number_format(4, '.', '') }} 1000" stroke-dashoffset="-{{ del_off4|number_format(4, '.', '') }}" />{% endif %}
                                            {% if del_p5 > 0 %}<circle cx="0" cy="0" r="70" fill="none" stroke="{{ colors[4] }}" stroke-width="44" stroke-dasharray="{{ del_l5|number_format(4, '.', '') }} 1000" stroke-dashoffset="-{{ del_off5|number_format(4, '.', '') }}" />{% endif %}
                                        </g>
                                        <circle cx="110" cy="110" r="38" fill="#ffffff" stroke="#e2e8f0" stroke-width="1" />
                                        <text x="110" y="114" text-anchor="middle" style="font-size:11px;font-weight:700;fill:#334155;">{{ del_total }}</text>
                                    </svg>

                                    {% if del_p1 >= 4 %}<div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%) rotate({{ (del_m1 - 90)|number_format(2, '.', '') }}deg) translate(86px,0) rotate({{ (90 - del_m1)|number_format(2, '.', '') }}deg);font-size:11px;font-weight:700;color:#1f2937;background:#fff;padding:1px 4px;border-radius:8px;border:1px solid #cbd5e1;">{{ del_p1|number_format(0, '.', ' ') }}%</div>{% endif %}
                                    {% if del_p2 >= 4 %}<div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%) rotate({{ (del_m2 - 90)|number_format(2, '.', '') }}deg) translate(86px,0) rotate({{ (90 - del_m2)|number_format(2, '.', '') }}deg);font-size:11px;font-weight:700;color:#1f2937;background:#fff;padding:1px 4px;border-radius:8px;border:1px solid #cbd5e1;">{{ del_p2|number_format(0, '.', ' ') }}%</div>{% endif %}
                                    {% if del_p3 >= 4 %}<div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%) rotate({{ (del_m3 - 90)|number_format(2, '.', '') }}deg) translate(86px,0) rotate({{ (90 - del_m3)|number_format(2, '.', '') }}deg);font-size:11px;font-weight:700;color:#1f2937;background:#fff;padding:1px 4px;border-radius:8px;border:1px solid #cbd5e1;">{{ del_p3|number_format(0, '.', ' ') }}%</div>{% endif %}
                                    {% if del_p4 >= 4 %}<div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%) rotate({{ (del_m4 - 90)|number_format(2, '.', '') }}deg) translate(86px,0) rotate({{ (90 - del_m4)|number_format(2, '.', '') }}deg);font-size:11px;font-weight:700;color:#1f2937;background:#fff;padding:1px 4px;border-radius:8px;border:1px solid #cbd5e1;">{{ del_p4|number_format(0, '.', ' ') }}%</div>{% endif %}
                                    {% if del_p5 >= 4 %}<div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%) rotate({{ (del_m5 - 90)|number_format(2, '.', '') }}deg) translate(86px,0) rotate({{ (90 - del_m5)|number_format(2, '.', '') }}deg);font-size:11px;font-weight:700;color:#1f2937;background:#fff;padding:1px 4px;border-radius:8px;border:1px solid #cbd5e1;">{{ del_p5|number_format(0, '.', ' ') }}%</div>{% endif %}
                                </div>
                            </td>
                            <td style="vertical-align:top;">
                                <table class="report" style="font-size:11px;">
                                    <tbody>
                                        <tr><td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[0] }};margin-right:6px;"></span>{{ labels[0] }}</td><td class="num">{{ del_1 }}</td><td class="num">{{ del_p1|number_format(2, '.', ' ') }}%</td></tr>
                                        <tr><td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[1] }};margin-right:6px;"></span>{{ labels[1] }}</td><td class="num">{{ del_2 }}</td><td class="num">{{ del_p2|number_format(2, '.', ' ') }}%</td></tr>
                                        <tr><td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[2] }};margin-right:6px;"></span>{{ labels[2] }}</td><td class="num">{{ del_3 }}</td><td class="num">{{ del_p3|number_format(2, '.', ' ') }}%</td></tr>
                                        <tr><td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[3] }};margin-right:6px;"></span>{{ labels[3] }}</td><td class="num">{{ del_4 }}</td><td class="num">{{ del_p4|number_format(2, '.', ' ') }}%</td></tr>
                                        <tr><td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[4] }};margin-right:6px;"></span>{{ labels[4] }}</td><td class="num">{{ del_5 }}</td><td class="num">{{ del_p5|number_format(2, '.', ' ') }}%</td></tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
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
        // no-op
    }
};
