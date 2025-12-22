<?php

return [
    'nav' => [
        'navigation_group'   => 'Магазин',
        'navigation_label'   => 'Характеристики',
        'model_label'        => 'Характеристика',
        'plural_model_label' => 'Характеристики',
    ],

    'fields' => [
        'name'                      => 'Назва',
        'slug'                      => 'Slug',
        'category'                  => 'Категорія',
        'icon'                      => 'Іконка (SVG)',
        'icon_preview'              => 'Прев\'ю іконки',
        'pricing_type'              => 'Тип ціноутворення',
        'sort_position'             => 'Позиція сортування',
        'expand_all_values'         => 'Розкривати всі значення',
        'is_required'               => 'Обов\'язкова',
        'show_on_main_tab'          => 'На головній вкладці показати товара',
        'field_type'                => 'Тип поля',
        'is_active'                 => 'Активність',
    ],

    'columns' => [
        'name'          => 'Назва',
        'slug'          => 'Slug',
        'category'      => 'Категорія',
        'field_type'    => 'Тип поля',
        'pricing_type'  => 'Ціноутворення',
        'icon'          => 'Іконка',
        'is_required'   => 'Обов\'язкова',
        'is_main_tab'   => 'На головній',
        'is_active'     => 'Активна',
        'sort_order'    => 'Позиція',
    ],

    'sections' => [
        'main' => 'Основні',
        'icon' => 'Іконка',
    ],

    'pricing_types' => [
        'no_impact'    => 'Не впливає',
        'surcharge'    => 'Надбавка',
        'fixed'        => 'Фіксована',
    ],

    'field_types' => [
        'text'        => 'TextInput',
        'datetime'    => 'Date/Time',
        'number'      => 'Number',
        'decimal'     => 'Decimal',
        'textarea'    => 'Textarea',
        'toggle'      => 'Switch',
        'color'       => 'Color Picker',
        'file'        => 'File/Image',
        'select'      => 'Select (одно)',
        'radio'       => 'RadioList',
        'multiselect' => 'MultiSelect',
        'checkbox'    => 'CheckboxList',
    ],

    'helpers' => [
        'icon_hint'           => 'Виберіть тільки з SVG, позначених як «Використовувати в характеристиках».',
        'icon_not_selected'   => 'Іконка не вибрана',
        'icon_load_error'     => 'Не вдалося завантажити SVG',
        'icon_invalid'        => 'Некорректний SVG',
    ],

    'values' => [
        'label'      => 'Значення',
        'value'      => 'Значення',
        'color'      => 'Колір',
        'sort_order' => 'Позиція',
        'is_active'  => 'Активно',
    ],
];

