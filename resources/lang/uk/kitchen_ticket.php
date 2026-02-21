<?php

return [
    'nav' => [
        'navigation_group'   => 'КитченТач',
        'navigation_label'   => 'КухняТач',
        'model_label'        => 'кухонний тикет',
        'plural_model_label' => 'кухонні тикети',
    ],

    'columns' => [
        'order_number'  => '№ зам.',
        'order_time'    => 'Час<br>зам.',
        'timer'         => 'Таймер',
        'urgent'        => 'Якнай<br>швидше',
        'delivery_type' => 'Тип',
        'items_count'   => 'К-сть<br>товарів',
        'items'         => 'Товари',
        'items_qty'     => 'К-сть',
        'priority'      => 'Пріор.',
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
        'priority_up'       => 'Підвищити пріоритет',
        'priority_down'     => 'Знизити пріоритет',
        'priority_set_urgent' => 'Терміново',
        'priority_set_normal' => 'Звичайний',
        'priority_set_wait'   => 'Може почекати',
        'save'              => 'Зберегти',
        'close'             => 'Закрити',
    ],

    'modals' => [
        'order_items_heading'   => 'Позиції замовлення :number',
        'confirm_prepared_heading' => 'Підтвердіть завершення замовлення',
        'confirm_prepared_description' => 'Замовлення буде переміщено в архів і потрапить у збірку/видачу.',
        'delivery_heading' => 'Доставка замовлення :number',
        'delivery_address' => 'Адреса',
        'delivery_address_empty' => 'Адреса не вказана',
        'delivery_note' => 'Примітка',
        'delivery_note_empty' => 'Примітка відсутня',
        'apartment_short' => 'кв. :value',
        'order_note_heading' => 'Примітка до замовлення :number',
        'order_note_label' => 'Примітка',
        'order_note_empty' => 'Примітка відсутня',
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


