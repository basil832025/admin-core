<?php

return [
    'nav' => [
        'navigation_group'   => __('admin.nav.groups.settings'),
        'navigation_label'   => 'SVG Зображення',
        'model_label'        => 'SVG зображення',
        'plural_model_label' => 'SVG зображення',
    ],

    'sections' => [
        'main'      => 'Основне',
        'colors'    => 'Кольори (однотонні)',
        'svg_code'  => 'SVG код',
        'preview'   => 'Прев\'ю',
    ],

    'fields' => [
        'slug'              => 'Ім\'я (латиницею) / slug',
        'title'             => 'Заголовок',
        'description'       => 'Опис',
        'default_color'     => 'Колір за замовчуванням',
        'color_variants'    => 'Варіанти кольору (пресети)',
        'svg_code'          => 'SVG',
        'is_attr'           => 'Використовувати в характеристиках',
    ],

    'columns' => [
        'slug'              => 'Slug',
        'title'             => 'Заголовок',
        'svg_preview'       => 'Прев\'ю',
        'is_attr'           => 'Вик. в характ.',
        'file_path'         => 'Файл',
        'updated_at'        => 'Оновлено',
    ],

    'filters' => [
        'is_attr'           => 'Тільки для характеристик',
    ],

    'filter_options' => [
        'all'               => 'Всі',
        'yes'               => 'Так',
        'no'                => 'Ні',
    ],

    'helpers' => [
        'slug'              => 'Буде ім\'ям файлу: images/svg/{slug}.svg',
        'default_color'     => 'Використовується, якщо при виводі не задано свій колір.',
        'color_variants'    => 'Перелічіть HEX, наприклад: #111827, #FF7500, #EF4444',
        'svg_code'          => 'Вставте повний код, починаючи з &lt;svg ...&gt;',
        'is_attr'           => 'Відмічайте, якщо SVG — це іконка для характеристик/властивостей товару.',
        'is_attr_tooltip'   => 'Використовується в характеристиках',
        'preview_invalid'   => 'Вставте коректний SVG (починається з &lt;svg&gt;)',
        'preview_live'      => 'Живий попередній перегляд',
        'preview_no_color' => 'без кольору (буде брати поточний колір тексту)',
    ],
];


