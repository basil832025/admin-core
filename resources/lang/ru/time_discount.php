<?php

return [
    'nav' => [
        'navigation_group'   => 'Настройки',
        'navigation_label'   => 'Скидки по времени',
        'model_label'        => 'Скидка по времени',
        'plural_model_label' => 'Скидки по времени',
    ],

    'sections' => [
        'main'      => 'Основные параметры',
        'scope'     => 'Область действия',
    ],

    'fields' => [
        'name'              => 'Название',
        'description'   => 'Описания скидки',
        'is_active'         => 'Активна',
        'days'              => 'Дни недели',
        'time_from'         => 'Время с',
        'time_to'           => 'Время по',
        'time_type'         => 'Тип времени',
        'nth_item'          => 'Каждый N-й товар',
        'percent'           => 'Скидка %',
        'starts_at'         => 'Начало действия',
        'ends_at'           => 'Окончание действия',
        'note'              => 'Примечание',
        'categories'        => 'Категории (группы)',
        'products'          => 'Товары',
        'characteristics'   => 'Характеристики (любой value)',
        'characteristic_values' => 'Значения характеристик',
    ],

    'columns' => [
        'id'            => 'Id',
        'name'          => 'Название',
        'percent'       => 'Скидка',
        'nth_item'      => 'Каждый N-й',
        'time_type'     => 'Тип времени',
        'days'          => 'Дни',
        'time_from'     => 'С',
        'time_to'       => 'По',
        'is_active'     => 'Активна',
        'starts_at'     => 'Начало',
        'ends_at'       => 'Окончание',
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
            7 => 'Вс',
        ],
        'time_type_order_created'   => 'По времени создания заказа',
        'time_type_order_fulfilled' => 'По времени выполнения (доставка/выдача)',
        'time_type_display_created' => 'Создание заказа',
        'time_type_display_fulfilled'=> 'Выполнение',
        'days_all'                   => 'Все',
    ],

    'helpers' => [
        'days'                      => 'Если не выбрано — действует каждый день',
        'characteristic_values'     => 'Если заполнено — приоритетнее, чем просто «характеристики (любой value)»',
        'scope_description'         => 'Ограничь акцию по группам, товарам, характеристикам или их значениям',
    ],
];

