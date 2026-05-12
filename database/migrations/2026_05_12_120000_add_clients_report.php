<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $groupId = (int) DB::table('bs_report_groups')->where('slug', 'zagalne')->value('id');
        if ($groupId <= 0) {
            $groupId = (int) DB::table('bs_report_groups')->where('name', 'Загальне')->value('id');
        }

        $parametersSchema = [
            [
                'key' => 'date_from',
                'type' => 'date',
                'label' => 'Дата з',
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
                'key' => 'date_field',
                'type' => 'select',
                'label' => 'Поле дати',
                'default' => 'registration',
                'required' => true,
                'options' => [
                    'registration' => 'Дата реєстрації',
                    'first_order' => 'Дата першого замовлення',
                    'last_order' => 'Дата останнього замовлення',
                ],
            ],
            [
                'key' => 'min_orders',
                'type' => 'number',
                'label' => 'Кількість замовлень (більше)',
                'default' => null,
                'required' => false,
            ],
            [
                'key' => 'min_amount',
                'type' => 'number',
                'label' => 'Від суми',
                'default' => 0,
                'required' => false,
            ],
            [
                'key' => 'phone_filter',
                'type' => 'text',
                'label' => 'Телефон',
                'default' => null,
                'required' => false,
            ],
            [
                'key' => 'sort_by',
                'type' => 'select',
                'label' => 'Сортувати за',
                'default' => 'last_order_date',
                'required' => true,
                'options' => [
                    'client_id' => 'Client_ID',
                    'name' => 'Ім\'я',
                    'phone' => 'Телефон',
                    'email' => 'Email',
                    'registration_date' => 'Дата реєстрації',
                    'first_order_date' => 'Дата першого замовлення',
                    'last_order_date' => 'Дата останнього замовлення',
                    'orders_count' => 'Кількість замовлень',
                    'total_amount' => 'Загальна сума замовлень',
                    'average_check' => 'Середній чек',
                    'cancelled_orders_count' => 'Кількість скасувань',
                    'bonus_balance' => 'Бонусний баланс',
                    'used_bonuses' => 'Використовував бонуси',
                    'used_promotions' => 'Використовував акції',
                    'last_promotion' => 'Остання акція',
                    'favorite_category' => 'Улюблена категорія',
                    'favorite_pie_size' => 'Улюблений розмір пирога',
                    'city' => 'Місто',
                    'last_order_address' => 'Адреса останнього замовлення',
                    'client_comment' => 'Коментар клієнта',
                    'order_source' => 'Джерело замовлення',
                    'birthday' => 'Дата народження',
                ],
            ],
            [
                'key' => 'sort_direction',
                'type' => 'select',
                'label' => 'Напрям сортування',
                'default' => 'desc',
                'required' => true,
                'options' => [
                    'asc' => 'За зростанням',
                    'desc' => 'За спаданням',
                ],
            ],
        ];

        $dataSources = [
            [
                'key' => 'main',
                'type' => 'provider',
                'enabled' => true,
                'provider_class' => \App\Reports\DataProviders\ClientsReportProvider::class,
            ],
        ];

        $templateBody = <<<'TWIG'
<div class="report-title">Звіт: Клієнти</div>
<div class="report-period">
    Поле дати: <strong>
        {% if params.date_field|default('registration') == 'first_order' %}
            Дата першого замовлення
        {% elseif params.date_field|default('registration') == 'last_order' %}
            Дата останнього замовлення
        {% else %}
            Дата реєстрації
        {% endif %}
    </strong>
    | Період: <strong>{{ params.date_from|default(null) ? params.date_from|date('d.m.Y') : 'усі дані' }}</strong> - <strong>{{ params.date_to|default(null) ? params.date_to|date('d.m.Y') : 'усі дані' }}</strong>
    | Кількість замовлень більше: <strong>{{ params.min_orders is defined and params.min_orders is not same as(null) and params.min_orders != '' ? params.min_orders : 'без фільтра' }}</strong>
    | Від суми: <strong>{{ params.min_amount|default(0) }}</strong>
    | Телефон: <strong>{{ params.phone_filter|default('') ?: 'без фільтра' }}</strong>
    | Сортування: <strong>{{ params.sort_by|default('last_order_date') }}</strong>
    | Напрям: <strong>{{ params.sort_direction|default('desc') == 'asc' ? 'За зростанням' : 'За спаданням' }}</strong>
</div>

<table class="report clients-report" style="margin-top: 10px;">
    <thead>
        <tr>
            <th>Client_ID</th>
            <th>Ім'я</th>
            <th>Телефон</th>
            <th>Email</th>
            <th>Дата<br>реєстрації</th>
            <th>Дата першого<br>замовлення</th>
            <th>Дата останнього<br>замовлення</th>
            <th class="num">Кількість<br>замовлень</th>
            <th class="num">Загальна сума<br>замовлень</th>
            <th class="num">Середній чек</th>
            <th class="num">Кількість<br>скасувань</th>
            <th class="num">Бонусний<br>баланс</th>
            <th>Використовував<br>бонуси</th>
            <th>Використовував<br>акції</th>
            <th>Остання<br>акція</th>
            <th>Улюблена<br>категорія</th>
            <th>Улюблений<br>розмір пирога</th>
            <th>Місто</th>
            <th>Адреса останнього<br>замовлення</th>
            <th>Коментар<br>клієнта</th>
            <th>Джерело<br>замовлення</th>
            <th>Дата<br>народження</th>
            <th>UTM<br>source</th>
            <th>UTM<br>campaign</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.main is defined and datasets.main|length %}
            {% set total_orders_count = 0 %}
            {% set total_amount_sum = 0 %}
            {% set total_cancelled_count = 0 %}
            {% set total_bonus_balance = 0 %}
            {% for row in datasets.main %}
                {% set total_orders_count = total_orders_count + (row.orders_count|default(0)) %}
                {% set total_amount_sum = total_amount_sum + (row.total_amount|default(0)) %}
                {% set total_cancelled_count = total_cancelled_count + (row.cancelled_orders_count|default(0)) %}
                {% set total_bonus_balance = total_bonus_balance + (row.bonus_balance|default(0)) %}
                <tr>
                    <td>{{ row.client_id|default('') }}</td>
                    <td>{{ row.name|default('') }}</td>
                    <td>{{ row.phone|default('') }}</td>
                    <td>{{ row.email|default('') }}</td>
                    <td>{{ row.registration_date|default(null) ? row.registration_date|date('d.m.Y') : '' }}</td>
                    <td>{{ row.first_order_date|default(null) ? row.first_order_date|date('d.m.Y') : '' }}</td>
                    <td>{{ row.last_order_date|default(null) ? row.last_order_date|date('d.m.Y') : '' }}</td>
                    <td class="num">{{ row.orders_count|default(0) }}</td>
                    <td class="num">{{ row.total_amount|default(0)|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ row.average_check|default(0)|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ row.cancelled_orders_count|default(0) }}</td>
                    <td class="num">{{ row.bonus_balance|default(0)|number_format(2, '.', ' ') }}</td>
                    <td>{{ row.used_bonuses|default('Ні') }}</td>
                    <td>{{ row.used_promotions|default('Ні') }}</td>
                    <td>{{ row.last_promotion|default('') }}</td>
                    <td>{{ row.favorite_category|default('') }}</td>
                    <td>{{ row.favorite_pie_size|default('') }}</td>
                    <td>{{ row.city|default('') }}</td>
                    <td>{{ row.last_order_address|default('') }}</td>
                    <td>{{ row.client_comment|default('') }}</td>
                    <td>{{ row.order_source|default('') }}</td>
                    <td>{{ row.birthday|default(null) ? row.birthday|date('d.m.Y') : '' }}</td>
                    <td>{{ row.utm_source|default('') }}</td>
                    <td>{{ row.utm_campaign|default('') }}</td>
                </tr>
            {% endfor %}
            <tr class="total-row">
                <td colspan="7">Усього</td>
                <td class="num">{{ total_orders_count }}</td>
                <td class="num">{{ total_amount_sum|number_format(2, '.', ' ') }}</td>
                <td class="num"></td>
                <td class="num">{{ total_cancelled_count }}</td>
                <td class="num">{{ total_bonus_balance|number_format(2, '.', ' ') }}</td>
                <td colspan="12"></td>
            </tr>
        {% else %}
            <tr>
                <td colspan="24">Дані відсутні.</td>
            </tr>
        {% endif %}
    </tbody>
</table>
TWIG;

        $customCss = <<<'CSS'
.report-title{font-size:16px;font-weight:700;margin-bottom:4px;color:#0f172a;}
.report-period{font-size:10px;line-height:1.35;color:#334155;}
table.clients-report{font-size:8.5px;line-height:1.2;table-layout:fixed;}
.clients-report th,.clients-report td{padding:4px 5px;word-break:break-word;}
.clients-report thead th{font-size:8px;white-space:normal;word-break:break-word;line-height:1.15;text-align:center;vertical-align:middle;hyphens:auto;}
.clients-report tbody td{text-align:center;vertical-align:middle;}
.clients-report tbody td.num,.clients-report .num{text-align:right;}
.clients-report .total-row td{font-weight:700;}
.clients-report td:nth-child(1){width:44px;}
.clients-report td:nth-child(2){width:90px;}
.clients-report td:nth-child(3){width:82px;}
.clients-report td:nth-child(4){width:110px;}
.clients-report td:nth-child(5),
.clients-report td:nth-child(6),
.clients-report td:nth-child(7),
.clients-report td:nth-child(22){white-space:nowrap;}
.clients-report td:nth-child(8),
.clients-report td:nth-child(9),
.clients-report td:nth-child(10),
.clients-report td:nth-child(11),
.clients-report td:nth-child(12){white-space:nowrap;}
.clients-report th:nth-child(23),.clients-report td:nth-child(23),
.clients-report th:nth-child(24),.clients-report td:nth-child(24){width:42px;}
CSS;

        DB::table('bs_print_templates')->updateOrInsert(
            ['code' => 'clients_report'],
            [
                'name' => 'Клієнти',
                'type' => 'report',
                'report_group_id' => $groupId > 0 ? $groupId : null,
                'engine' => 'twig',
                'output_format' => 'pdf',
                'default_paper_preset' => 'custom',
                'default_paper_width_mm' => 297,
                'default_paper_height_mm' => 210,
                'default_margin_top_mm' => 6,
                'default_margin_right_mm' => 6,
                'default_margin_bottom_mm' => 6,
                'default_margin_left_mm' => 6,
                'editor_mode' => 'code',
                'css_preset' => 'report_table_dense',
                'custom_css' => $customCss,
                'description' => 'Клієнтський звіт з агрегатами по замовленнях, бонусах та акціях.',
                'template_body' => $templateBody,
                'parameters_schema' => json_encode($parametersSchema, JSON_UNESCAPED_UNICODE),
                'data_sources' => json_encode($dataSources, JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('bs_print_templates')
            ->where('code', 'clients_report')
            ->delete();
    }
};
