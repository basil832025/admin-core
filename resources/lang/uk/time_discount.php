<?php

return [
    'nav' => [
        'navigation_group'   => __('admin.nav.groups.settings'),
        'navigation_label'   => 'Знижки за часом',
        'model_label'        => 'Знижка за часом',
        'plural_model_label' => 'Знижки за часом',
    ],

    'sections' => [
        'main'      => 'Основні параметри',
        'scope'     => 'Область дії',
    ],

    'fields' => [
        'name'              => 'Назва',
        'description'   => 'Опис знижки',
        'is_active'         => 'Активна',
        'days'              => 'Дні тижня',
        'time_from'         => 'Час з',
        'time_to'           => 'Час по',
        'time_type'         => 'Тип часу',
        'nth_item'          => 'Кожен N-й товар',
        'percent'           => 'Знижка %',
        'starts_at'         => 'Початок дії',
        'ends_at'           => 'Закінчення дії',
        'note'              => 'Примітка',
        'categories'        => 'Категорії (групи)',
        'products'          => 'Товари',
        'characteristics'   => 'Характеристики (будь-який value)',
        'characteristic_values' => 'Значення характеристик',
    ],

    'columns' => [
        'id'            => 'Id',
        'name'          => 'Назва',
        'percent'       => 'Знижка',
        'nth_item'      => 'Кожен N-й',
        'time_type'     => 'Тип часу',
        'days'          => 'Дні',
        'time_from'     => 'З',
        'time_to'       => 'По',
        'is_active'     => 'Активна',
        'starts_at'     => 'Початок',
        'ends_at'       => 'Закінчення',
    ],

    'filters' => [
        'is_active'     => 'Активна',
    ],

    'options' => [
        'weekdays' => [
            1 => 'Пн',
            2 => 'Вт',
            3 => 'Ср',
            4 => 'Чт',
            5 => 'Пт',
            6 => 'Сб',
            7 => 'Нд',
        ],
        'time_type_order_created'   => 'За часом створення замовлення',
        'time_type_order_fulfilled' => 'За часом виконання (доставка/видача)',
        'time_type_display_created' => 'Створення замовлення',
        'time_type_display_fulfilled'=> 'Виконання',
        'days_all'                   => 'Всі',
    ],

    'helpers' => [
        'days'                      => 'Якщо не вибрано — діє кожен день',
        'characteristic_values'     => 'Якщо заповнено — пріоритетніше, ніж просто «характеристики (будь-який value)»',
        'scope_description'          => 'Обмеж акцію за групами, товарами, характеристиками або їх значеннями',
    ],
];
