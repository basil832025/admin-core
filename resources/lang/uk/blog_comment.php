<?php

return [
    'nav' => [
        'navigation_label'   => 'Коментарі до статей',
        'model_label'        => 'Коментар',
        'plural_model_label' => 'Коментарі',
    ],

    'sections' => [
        'comment_data' => 'Дані коментаря',
    ],

    'fields' => [
        'blog_id'      => 'Пост блогу',
        'author_name'  => 'Ім\'я автора',
        'author_email' => 'Email автора',
        'content'      => 'Контент',
        'parent_id'    => 'Відповідь на',
        'is_approved' => 'Схвалено',
    ],

    'columns' => [
        'author_name'  => 'Автор',
        'author_email' => 'Email',
        'content'      => 'Коментар',
        'is_approved' => 'Схвалено',
        'created_at'  => 'Створено',
    ],

    'filters' => [
        'approved' => 'Тільки схвалені',
    ],
];


