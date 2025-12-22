<?php

return [
    'nav' => [
        'navigation_label'   => 'Точки (заклади)',
        'model_label'        => 'Точка',
        'plural_model_label' => 'Точки',
    ],

    'sections' => [
        'main'      => 'Основне',
        'contacts'  => 'Контакти',
        'schedule'  => 'Графік роботи',
        'publish'   => 'Публікація',
    ],

    'fields' => [
        'title'         => 'Заголовок',
        'city'          => 'Місто',
        'address'       => 'Адреса',
        'slug'          => 'Слаг',
        'lat'           => 'Широта',
        'lng'           => 'Довгота',
        'svg_image_id'  => 'SVG-іконка для карти',
        'svg_preview'   => 'Прев\'ю іконки',
        'map_preview'   => 'Карта (Google)',
        'phones'        => 'Телефони',
        'emails'        => 'Emails',
        'phone'         => 'Телефон',
        'email'         => 'Email',
        'is_active'     => 'Активен',
        'note'          => 'Примітка',
        'svg_icon'      => 'SVG-іконка',
        'preview'       => 'Прев\'ю',
        'schedule'      => 'Графік',
        'sort'           => 'Сортування',
    ],

    'columns' => [
        'title'         => 'Назва',
        'city'          => 'Місто',
        'address'       => 'Адреса',
        'is_active'     => 'Вкл',
        'reviews_count' => 'Відгуки',
        'phones'        => 'Телефони',
        'emails'        => 'Email',
        'schedule'      => 'Графік',
        'updated_at'    => 'Оновлено',
    ],

    'filters' => [
        'is_active'     => 'Активна',
        'city'          => 'Місто',
    ],

    'actions' => [
        'add_phone'    => 'Додати до телефони',
        'add_email'     => 'Додати email',
    ],

    'helpers' => [
        'svg_icon_map'     => 'Іконка маркера (довідник SvgImage).',
        'slug_schedule'     => 'Слаг (main / delivery / pickup / holiday)',
        'slug_helper'       => 'Внутрішній слаг',
        'title_ua'         => 'Заголовок (UA)',
        'title_ru'         => 'Заголовок (RU)',
        'title_en'         => 'Title (EN)',
        'time_ua'           => 'Години (UA) напр. 11:30–22:30',
        'time_ru'           => 'Години (RU) напр. 11:30–22:30',
        'time_en'           => 'Hours (EN) e.g. 11:30–22:30',
        'working'           => 'Робочий',
    ],
];

