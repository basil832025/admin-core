<?php

return [
    'nav' => [
        'navigation_group'   => 'КитченТач',
        'navigation_label'   => 'КухняТач',
        'model_label'        => 'кухонный тикет',
        'plural_model_label' => 'кухонные тикеты',
    ],

    'columns' => [
        'order_number'  => '№ заказа',
        'order_time'    => 'Время<br>заказ.',
        'timer'         => 'Таймер',
        'urgent'        => 'Как<br>можно быстрее',
        'delivery_type' => 'Тип',
        'items_count'   => 'Кол-во<br>товаров',
        'items'         => 'Товары',
        'items_qty'     => 'Кол-во',
        'priority'      => 'Приор.',
        'stage'         => 'Этап',
    ],

    'filters' => [
        'status'        => 'Статус',
        'urgent'        => 'Поскорее',
        'delivery_type' => 'Тип',
    ],

    'filter_options' => [
        'current'       => 'Текущие',
        'archived'      => 'Архивные',
        'any'           => 'Любые',
        'urgent_only'   => 'Только срочные',
        'normal_only'   => 'Только обычные',
        'delivery'      => 'Доставка',
        'pickup'        => 'Самовывоз',
    ],

    'actions' => [
        'current_orders'    => 'Текущие заказы',
        'archived'          => 'Архивные',
        'to_filling'        => 'Начинка/Принял',
        'to_molding'        => 'Лепка',
        'to_baking'         => 'Печь',
        'to_prepared'       => 'Приготовлен',
        'priority_up'       => 'Повысить приоритет',
        'priority_down'     => 'Понизить приоритет',
        'priority_set_urgent' => 'Срочно',
        'priority_set_normal' => 'Обычный',
        'priority_set_wait'   => 'Может подождать',
        'save'              => 'Сохранить',
        'close'             => 'Закрыть',
    ],

    'modals' => [
        'order_items_heading'   => 'Позиции заказа :number',
        'confirm_prepared_heading' => 'Подтвердите завершение заказа',
        'confirm_prepared_description' => 'Заказ будет перемещён в архив и попадёт в сборку/выдачу.',
        'delivery_heading' => 'Доставка заказа :number',
        'delivery_address' => 'Адрес',
        'delivery_address_empty' => 'Адрес не указан',
        'delivery_note' => 'Примечание',
        'delivery_note_empty' => 'Примечание отсутствует',
        'apartment_short' => 'кв. :value',
        'order_note_heading' => 'Примечание к заказу :number',
        'order_note_label' => 'Примечание',
        'order_note_empty' => 'Примечание отсутствует',
    ],

    'stages' => [
        'accepted'  => 'Принял',
        'filling'   => 'Начинка',
        'molding'   => 'Лепка',
        'baking'    => 'Печь',
        'ready'     => 'Готово',
        'stage'     => 'Этап',
    ],

    'table_headers' => [
        'product'  => 'Товар',
        'quantity'  => 'К-во',
    ],

    'empty_state' => [
        'heading'      => 'Заказов нет',
        'description'  => 'Как только оператор переведёт заказ «В обработке», он появится здесь.',
    ],

    'values' => [
        'yes'       => 'Да',
        'no'        => '—',
        'delivery'  => 'Доставка',
        'pickup'    => 'Самовывоз',
    ],

    'notifications' => [
        'marks_saved' => 'Отметки сохранены',
    ],

    'helpers' => [
        'calculation_missing' => 'Калькуляция отсутствует',
    ],
];


