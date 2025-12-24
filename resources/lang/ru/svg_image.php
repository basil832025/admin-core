<?php

return [
    'nav' => [
        'navigation_group'   => 'Медиа',
        'navigation_label'   => 'SVG Изображения',
        'model_label'        => 'SVG изображение',
        'plural_model_label' => 'SVG изображения',
    ],

    'sections' => [
        'main'      => 'Основное',
        'colors'    => 'Цвета (однотонные)',
        'svg_code'  => 'SVG код',
        'preview'   => 'Превью',
    ],

    'fields' => [
        'slug'              => 'Имя (латиницей) / slug',
        'title'             => 'Заголовок',
        'description'       => 'Описание',
        'default_color'     => 'Цвет по умолчанию',
        'color_variants'    => 'Варианты цвета (пресеты)',
        'svg_code'          => 'SVG',
        'is_attr'           => 'Использовать в характеристиках',
    ],

    'columns' => [
        'slug'              => 'Slug',
        'title'             => 'Заголовок',
        'svg_preview'       => 'Превью',
        'is_attr'           => 'Исполь в характ.',
        'file_path'         => 'Файл',
        'updated_at'        => 'Обновлено',
    ],

    'filters' => [
        'is_attr'           => 'Только для характеристик',
    ],

    'filter_options' => [
        'all'               => 'Все',
        'yes'               => 'Да',
        'no'                => 'Нет',
    ],

    'helpers' => [
        'slug'              => 'Будет именем файла: images/svg/{slug}.svg',
        'default_color'     => 'Используется, если при выводе не задан свой цвет.',
        'color_variants'    => 'Перечислите HEX, например: #111827, #FF7500, #EF4444',
        'svg_code'          => 'Вставьте полный код, начиная с &lt;svg ...&gt;',
        'is_attr'           => 'Отмечайте, если SVG — это иконка для характеристик/свойств товара.',
        'is_attr_tooltip'   => 'Используется в характеристиках',
        'preview_invalid'   => 'Вставьте корректный SVG (начинается с &lt;svg&gt;)',
        'preview_live'      => 'Живой предпросмотр',
        'preview_no_color'  => 'без цвета (будет брать текущий цвет текста)',
    ],
];


