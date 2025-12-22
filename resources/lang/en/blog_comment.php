<?php

return [
    'nav' => [
        'navigation_label'   => 'Article comments',
        'model_label'        => 'Comment',
        'plural_model_label' => 'Comments',
    ],

    'sections' => [
        'comment_data' => 'Comment data',
    ],

    'fields' => [
        'blog_id'      => 'Blog post',
        'author_name'  => 'Author name',
        'author_email' => 'Author email',
        'content'      => 'Content',
        'parent_id'    => 'Reply to',
        'is_approved' => 'Approved',
    ],

    'columns' => [
        'author_name'  => 'Author',
        'author_email' => 'Email',
        'content'      => 'Comment',
        'is_approved' => 'Approved',
        'created_at'  => 'Created',
    ],

    'filters' => [
        'approved' => 'Approved only',
    ],
];

