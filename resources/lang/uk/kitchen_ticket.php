<?php

return [
    'nav' => [
        'navigation_group'   => 'КитченТач',
        'navigation_label'   => 'КухняТач',
        'model_label'        => 'кухонний тикет',
        'plural_model_label' => 'кухонні тикети',
    ],

    'columns' => [
        'order_number'  => '№ замовлення',
        'order_time'    => 'Час замовлення',
        'urgent'        => 'Якнайшвидше',
        'delivery_type' => 'Тип',
        'items_count'   => 'Кол товарів',
        'stage'         => 'Етап',
    ],

    'filters' => [
        'status'        => 'Статус',
        'urgent'        => 'Якнайшвидше',
        'delivery_type' => 'Тип',
    ],

    'filter_options' => [
        'current'       => 'Поточні',
        'archived'      => 'Архівні',
        'any'           => 'Будь-які',
        'urgent_only'   => 'Тільки термінові',
        'normal_only'   => 'Тільки звичайні',
        'delivery'      => 'Доставка',
        'pickup'        => 'Самовивіз',
    ],

    'actions' => [
        'current_orders'    => 'Поточні замовлення',
        'archived'          => 'Архівні',
        'to_filling'        => 'Начинка/Прийняв',
        'to_molding'        => 'Ліпка',
        'to_baking'         => 'Піч',
        'to_prepared'       => 'Приготовано',
        'save'              => 'Зберегти',
    ],

    'modals' => [
        'order_items_heading'   => 'Позиції замовлення :number',
        'confirm_prepared_heading' => 'Підтвердіть завершення замовлення',
        'confirm_prepared_description' => 'Замовлення буде переміщено в архів і потрапить у збірку/видачу.',
    ],

    'stages' => [
        'accepted'  => 'Прийняв',
        'filling'   => 'Начинка',
        'molding'   => 'Ліпка',
        'baking'    => 'Піч',
        'ready'     => 'Готово',
        'stage'     => 'Етап',
    ],

    'table_headers' => [
        'product'  => 'Товар',
        'quantity'  => 'К-во',
    ],

    'empty_state' => [
        'heading'      => 'Замовлень немає',
        'description'  => 'Як тільки оператор переведе замовлення «В обробці», воно з\'явиться тут.',
    ],

    'values' => [
        'yes'       => 'Так',
        'no'        => '—',
        'delivery'  => 'Доставка',
        'pickup'    => 'Самовивіз',
    ],

    'notifications' => [
        'marks_saved' => 'Відмітки збережено',
    ],

    'helpers' => [
        'calculation_missing' => 'Калькуляція відсутня',
    ],
];

