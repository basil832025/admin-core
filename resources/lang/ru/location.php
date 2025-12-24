<?php

return [
    'nav' => [
        'navigation_label'   => 'Точки (заведения)',
        'model_label'        => 'Точка',
        'plural_model_label' => 'Точки',
    ],

    'sections' => [
        'main'      => 'Основное',
        'contacts'  => 'Контакты',
        'schedule'  => 'График работы',
        'publish'   => 'Публикация',
    ],

    'fields' => [
        'title'         => 'Заголовок',
        'city'          => 'Город',
        'address'       => 'Адрес',
        'slug'          => 'Слаг',
        'lat'           => 'Широта',
        'lng'           => 'Долгота',
        'svg_image_id'  => 'SVG-иконка для карты',
        'svg_preview'   => 'Превью иконки',
        'map_preview'   => 'Карта (Google)',
        'phones'        => 'Телефоны',
        'emails'        => 'Emails',
        'phone'         => 'Телефон',
        'email'         => 'Email',
        'is_active'     => 'Активен',
        'note'          => 'Примечание',
        'svg_icon'      => 'SVG-иконка',
        'preview'       => 'Превью',
        'schedule'      => 'График',
        'sort'           => 'Сортировка',
    ],

    'columns' => [
        'title'         => 'Название',
        'city'          => 'Город',
        'address'       => 'Адрес',
        'is_active'     => 'Вкл',
        'reviews_count' => 'Отзывы',
        'phones'        => 'Телефоны',
        'emails'        => 'Email',
        'schedule'      => 'График',
        'updated_at'     => 'Обновлено',
    ],

    'filters' => [
        'is_active'     => 'Активна',
        'city'          => 'Город',
    ],

    'actions' => [
        'add_phone'    => 'Добавить телефон',
        'add_email'     => 'Добавить email',
    ],

    'helpers' => [
        'svg_icon_map'     => 'Иконка маркера (справочник SvgImage).',
        'slug_schedule'    => 'Слаг (main / delivery / pickup / holiday)',
        'slug_helper'      => 'Внутренний слаг',
        'title_ua'         => 'Заголовок (UA)',
        'title_ru'         => 'Заголовок (RU)',
        'title_en'         => 'Title (EN)',
        'time_ua'           => 'Часы (UA) напр. 11:30–22:30',
        'time_ru'           => 'Часы (RU) напр. 11:30–22:30',
        'time_en'           => 'Hours (EN) e.g. 11:30–22:30',
        'working'           => 'Рабочий',
    ],
];


