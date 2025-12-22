<?php

return [
    'nav' => [
        'navigation_label'   => 'Клиенты',
        'model_label'        => 'Клиент',
        'plural_model_label' => 'Клиенты',
    ],

    'fields' => [
        'name'              => 'ФИО',
        'phone'             => 'Телефон',
        'email'             => 'Email',
        'birthday'          => 'Дата рождения',
        'gender'            => 'Пол',
        'password'          => 'Пароль',
        'photo'             => 'Фото',
        'note'              => 'Примечание',
        'is_active'         => 'Активен',
        'is_foreign_phone'  => 'Телефон другой страны',
    ],

    'placeholders' => [
        'phone_ua'          => '(067) 123-45-67',
        'phone_foreign'     => 'Напр.: 491512345678 (только цифры, 6–15)',
    ],

    'helpers' => [
        'is_foreign_phone' => 'Включите, если номер не украинский',
    ],

    'gender' => [
        'male'   => 'Мужчина',
        'female' => 'Женщина',
    ],

    'columns' => [
        'name'      => 'ФИО',
        'phone'     => 'Телефон',
        'email'     => 'Email',
        'gender'     => 'Пол',
        'is_active' => 'Активен',
    ],

    'sections' => [
        'main'      => 'Основная информация',
        'metadata'  => 'Служебная информация',
    ],

    'metadata' => [
        'created_at'    => 'Создан',
        'updated_at'    => 'Изменён',
    ],
];

