<?php

return [
    'nav' => [
        'navigation_group'   => 'Настройки',
        'navigation_label'   => 'Промокоди',
        'model_label'        => 'Промокод',
        'plural_model_label' => 'Промокоди',
    ],

    'sections' => [
        'parameters'    => 'Параметри промокоду',
        'scope'         => 'Область дії',
        'statistics'    => 'Статистика (read-only)',
    ],

    'fields' => [
        'code'                      => 'Промокод',
        'percent'                   => 'Знижка %',
        'is_active'                 => 'Активний',
        'starts_at'                 => 'Початок дії',
        'ends_at'                   => 'Закінчення дії',
        'max_uses'                  => 'Глобальний ліміт використань',
        'per_client_limit'          => 'Ліміт на клієнта',
        'note'                      => 'Примітка',
        'categories'                => 'Категорії (групи)',
        'products'                  => 'Товари',
        'characteristics'           => 'Характеристики (будь-яке значення)',
        'characteristic_values'     => 'Значення характеристик',
        'used_total'                => 'Використань всього',
        'remaining'                => 'Залишилось глобальних використань',
    ],

    'columns' => [
        'code'          => 'Код',
        'percent'       => 'Знижка',
        'is_active'     => 'Активний',
        'starts_at'     => 'Початок',
        'ends_at'       => 'Закінчення',
        'usages_count'  => 'Вик. всього',
        'remaining'     => 'Залишок',
    ],

    'filters' => [
        'is_active'     => 'Активний',
        'active_now'    => 'Діє зараз',
    ],

    'helpers' => [
        'code_uppercase'            => 'Буде збережено в UPPERCASE',
        'max_uses_empty'             => 'Порожньо = без ліміту',
        'characteristic_values_hint' => 'Спочатку виберіть характеристику(и); тут відобразяться тільки їх значення.',
        'scope_description'         => 'Обмеж промокод за групами, товарами та/або характеристиками',
    ],
];
