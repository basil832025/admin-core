<?php

return [
    'nav' => [
        'navigation_label'   => 'Clients',
        'model_label'        => 'Client',
        'plural_model_label' => 'Clients',
    ],

    'fields' => [
        'name'              => 'Full name',
        'phone'             => 'Phone',
        'email'             => 'Email',
        'birthday'          => 'Birthday',
        'gender'            => 'Gender',
        'password'          => 'Password',
        'photo'             => 'Photo',
        'note'              => 'Note',
        'is_active'         => 'Active',
        'is_foreign_phone'  => 'Foreign phone',
    ],

    'placeholders' => [
        'phone_ua'          => '(067) 123-45-67',
        'phone_foreign'     => 'E.g.: 491512345678 (digits only, 6–15)',
    ],

    'helpers' => [
        'is_foreign_phone' => 'Enable if the number is not Ukrainian',
    ],

    'gender' => [
        'male'   => 'Male',
        'female' => 'Female',
    ],

    'columns' => [
        'name'      => 'Full name',
        'phone'     => 'Phone',
        'email'     => 'Email',
        'gender'    => 'Gender',
        'is_active' => 'Active',
    ],

    'sections' => [
        'main'      => 'Main information',
        'metadata'  => 'Metadata',
    ],

    'metadata' => [
        'created_at'    => 'Created at',
        'updated_at'    => 'Last modified at',
    ],
];


