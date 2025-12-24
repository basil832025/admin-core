<?php

return [
    'nav' => [
        'navigation_label'   => 'Комментарии к статьям',
        'model_label'        => 'Комментарий',
        'plural_model_label' => 'Комментарии',
    ],

    'sections' => [
        'comment_data' => 'Данные комментария',
    ],

    'fields' => [
        'blog_id'      => 'Пост блога',
        'author_name'  => 'Имя автора',
        'author_email' => 'Email автора',
        'content'      => 'Контент',
        'parent_id'    => 'Ответ на',
        'is_approved' => 'Одобрен',
    ],

    'columns' => [
        'author_name'  => 'Автор',
        'author_email' => 'Email',
        'content'      => 'Комментарий',
        'is_approved' => 'Одобрен',
        'created_at'  => 'Создан',
    ],

    'filters' => [
        'approved' => 'Только одобренные',
    ],
];


