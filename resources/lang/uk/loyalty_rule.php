<?php

return [
    'nav' => [
        'navigation_group'   => 'Лояльність',
        'navigation_label'   => 'Правила бонусів',
        'model_label'        => 'Правило бонусів',
        'plural_model_label' => 'Правила бонусів',
    ],

    'sections' => [
        'valid_period'      => 'Період дії',
        'regular_bonuses'   => 'Нарахування звичайних бонусів',
        'welcome_bonus'     => 'Привітальний бонус',
        'other'             => 'Інше',
    ],

    'fields' => [
        'valid_from'                    => 'Діє з дати',
        'earn_percent'                  => 'Відсоток від чека',
        'earn_expire_days'              => 'Термін життя бонусів (днів)',
        'min_order_sum_for_earn'        => 'Мін. сума замовлення для нарахування',
        'welcome_bonus_amount'          => 'Сума привітального бонусу',
        'welcome_bonus_expire_days'     => 'Термін життя привітального бонусу (днів)',
        'is_enabled'                    => 'Програма увімкнена',
    ],

    'columns' => [
        'valid_from'                    => 'Діє з',
        'is_enabled'                    => 'Вкл.',
        'earn_percent'                  => '% від чека',
        'earn_expire_days'              => 'Термін бонусів, дн.',
        'welcome_bonus_amount'          => 'Привітальний бонус',
        'welcome_bonus_expire_days'     => 'Термін привіт., дн.',
        'min_order_sum_for_earn'       => 'Мін. сума замовлення',
    ],

    'actions' => [
        'edit'          => 'Ред.',
        'delete'        => 'Видалити',
        'delete_bulk'   => 'Видалити вибрані',
    ],

    'helpers' => [
        'valid_from'                    => 'З цієї дати правило набуває чинності. На кожну дату може бути тільки одне правило.',
        'earn_percent'                   => 'Скільки відсотків від суми замовлення нараховувати в бонуси.',
        'earn_expire_days'               => 'Через скільки днів після нарахування звичайні бонуси згоряють.',
        'min_order_sum_for_earn'         => 'Якщо порожньо — нараховуємо з будь-якої суми.',
        'welcome_bonus_amount'           => 'Нараховується авторизованому клієнту на перше оплачене замовлення.',
        'is_enabled'                     => 'Якщо вимкнути — за цим правилом бонуси нараховуватися не будуть.',
    ],
];

