<?php

return [
    'nav' => [
        'navigation_label'   => 'Клієнти',
        'model_label'        => 'Клієнт',
        'plural_model_label' => 'Клієнти',
    ],

    'fields' => [
        'name'              => 'ПІБ',
        'phone'             => 'Телефон',
        'email'             => 'Email',
        'birthday'          => 'Дата народження',
        'gender'            => 'Стать',
        'password'          => 'Пароль',
        'photo'             => 'Фото',
        'note'              => 'Примітка',
        'is_active'         => 'Активний',
        'is_foreign_phone'  => 'Телефон іншої країни',
    ],

    'placeholders' => [
        'phone_ua'          => '(067) 123-45-67',
        'phone_foreign'     => 'Напр.: 491512345678 (лише цифри, 6–15)',
    ],

    'helpers' => [
        'is_foreign_phone' => 'Увімкніть, якщо номер не український',
    ],

    'gender' => [
        'male'   => 'Чоловік',
        'female' => 'Жінка',
    ],

    'columns' => [
        'name'      => 'ПІБ',
        'phone'     => 'Телефон',
        'email'     => 'Email',
        'gender'    => 'Стать',
        'is_active' => 'Активний',
    ],

    'sections' => [
        'main'      => 'Основна інформація',
        'metadata'  => 'Службова інформація',
    ],

    'metadata' => [
        'created_at'    => 'Створено',
        'updated_at'    => 'Оновлено',
    ],
];


