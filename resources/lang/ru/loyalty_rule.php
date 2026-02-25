<?php

return [
    'nav' => [
        'navigation_group'   => 'Магазин',
        'navigation_label'   => 'Правила бонусов',
        'model_label'        => 'Правило бонусов',
        'plural_model_label' => 'Правила бонусов',
    ],

    'sections' => [
        'valid_period'      => 'Период действия',
        'regular_bonuses'   => 'Начисление обычных бонусов',
        'welcome_bonus'     => 'Приветственный бонус',
        'other'             => 'Прочее',
    ],

    'fields' => [
        'valid_from'                    => 'Действует с даты',
        'earn_percent'                  => 'Процент от чека',
        'earn_expire_days'              => 'Срок жизни бонусов (дней)',
        'min_order_sum_for_earn'        => 'Мин. сумма заказа для начисления',
        'welcome_bonus_amount'          => 'Сумма приветственного бонуса',
        'welcome_bonus_expire_days'     => 'Срок жизни приветственного бонуса (дней)',
        'is_enabled'                    => 'Программа включена',
    ],

    'columns' => [
        'valid_from'                    => 'Действует с',
        'is_enabled'                    => 'Вкл.',
        'earn_percent'                  => '% от чека',
        'earn_expire_days'              => 'Срок бонусов, дн.',
        'welcome_bonus_amount'          => 'Приветственный бонус',
        'welcome_bonus_expire_days'     => 'Срок приветств., дн.',
        'min_order_sum_for_earn'       => 'Мин. сумма заказа',
    ],

    'actions' => [
        'edit'          => 'Ред.',
        'delete'        => 'Удалить',
        'delete_bulk'   => 'Удалить выбранные',
    ],

    'helpers' => [
        'valid_from'                    => 'С этой даты правило вступает в силу. На каждую дату может быть только одно правило.',
        'earn_percent'                   => 'Сколько процентов от суммы заказа начислять в бонусы.',
        'earn_expire_days'               => 'Через сколько дней после начисления обычные бонусы сгорают.',
        'min_order_sum_for_earn'         => 'Если пусто — начисляем с любой суммы.',
        'welcome_bonus_amount'           => 'Начисляется авторизованному клиенту на первый оплаченный заказ.',
        'is_enabled'                     => 'Если выключить — по этому правилу бонусы начисляться не будут.',
    ],
];


