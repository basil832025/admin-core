<?php

return [
    'nav' => [
        'navigation_label'   => 'Locations (establishments)',
        'model_label'        => 'Location',
        'plural_model_label' => 'Locations',
    ],

    'sections' => [
        'main'      => 'Main',
        'contacts'  => 'Contacts',
        'schedule'  => 'Working hours',
        'publish'   => 'Publication',
    ],

    'fields' => [
        'title'         => 'Title',
        'city'          => 'City',
        'address'       => 'Address',
        'slug'          => 'Slug',
        'lat'           => 'Latitude',
        'lng'           => 'Longitude',
        'svg_image_id'  => 'SVG icon for map',
        'svg_preview'   => 'Icon preview',
        'map_preview'   => 'Map (Google)',
        'phones'       => 'Phones',
        'emails'        => 'Emails',
        'phone'         => 'Phone',
        'email'         => 'Email',
        'is_active'     => 'Active',
        'note'          => 'Note',
        'svg_icon'      => 'SVG icon',
        'preview'       => 'Preview',
        'schedule'      => 'Schedule',
        'sort'           => 'Sort',
    ],

    'columns' => [
        'title'         => 'Name',
        'city'          => 'City',
        'address'       => 'Address',
        'is_active'     => 'On',
        'reviews_count' => 'Reviews',
        'phones'        => 'Phones',
        'emails'        => 'Email',
        'schedule'      => 'Schedule',
        'updated_at'    => 'Updated',
    ],

    'filters' => [
        'is_active'     => 'Active',
        'city'          => 'City',
    ],

    'actions' => [
        'add_phone'    => 'Add phone',
        'add_email'     => 'Add email',
    ],

    'helpers' => [
        'svg_icon_map'     => 'Marker icon (SvgImage reference).',
        'slug_schedule'    => 'Slug (main / delivery / pickup / holiday)',
        'slug_helper'      => 'Internal slug',
        'title_ua'         => 'Title (UA)',
        'title_ru'         => 'Title (RU)',
        'title_en'         => 'Title (EN)',
        'time_ua'           => 'Hours (UA) e.g. 11:30–22:30',
        'time_ru'           => 'Hours (RU) e.g. 11:30–22:30',
        'time_en'           => 'Hours (EN) e.g. 11:30–22:30',
        'working'           => 'Working',
    ],
];


