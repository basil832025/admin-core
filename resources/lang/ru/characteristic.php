<?php

return [
    'nav' => [
        'navigation_group'   => 'Магазин',
        'navigation_label'   => 'Характеристики',
        'model_label'        => 'Характеристика',
        'plural_model_label' => 'Характеристики',
    ],

    'fields' => [
        'name'                      => 'Название',
        'slug'                      => 'Slug',
        'category'                  => 'Категория',
        'icon'                      => 'Иконка (SVG)',
        'icon_preview'              => 'Превью иконки',
        'pricing_type'              => 'Тип ценообразования',
        'sort_position'             => 'Позиция сортировки',
        'expand_all_values'         => 'Раскрывать все значения',
        'is_required'               => 'Обязательная',
        'show_on_main_tab'          => 'На главной вкладке показать товара',
        'field_type'                => 'Тип поля',
        'is_active'                 => 'Активность',
    ],

    'columns' => [
        'name'          => 'Название',
        'slug'          => 'Slug',
        'category'      => 'Категория',
        'field_type'    => 'Тип поля',
        'pricing_type'  => 'Ценообразование',
        'icon'          => 'Иконка',
        'is_required'   => 'Обязательная',
        'is_main_tab'   => 'На главной',
        'is_active'     => 'Активна',
        'sort_order'    => 'Позиция',
    ],

    'sections' => [
        'main' => 'Основные',
        'icon' => 'Иконка',
    ],

    'pricing_types' => [
        'no_impact'    => 'Не влияет',
        'surcharge'    => 'Надбавка',
        'fixed'        => 'Фиксированная',
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
        'icon_hint'           => 'Выбирайте только из SVG, отмеченных как «Использовать в характеристиках».',
        'icon_not_selected'   => 'Иконка не выбрана',
        'icon_load_error'     => 'Не удалось загрузить SVG',
        'icon_invalid'        => 'Некорректный SVG',
    ],

    'values' => [
        'label'      => 'Значения',
        'value'      => 'Значение',
        'color'      => 'Цвет',
        'sort_order' => 'Позиция',
        'is_active'  => 'Активно',
    ],
];

