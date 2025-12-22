<?php

return [
    'nav' => [
        'navigation_label'   => 'Reviews',
        'model_label'        => 'Review',
        'plural_model_label' => 'Reviews',
    ],

    'fields' => [
        'product'    => 'Product',
        'name'       => 'Name',
        'email'      => 'Email',
        'rating'     => 'Rating',
        'review'     => 'Review',
        'status'     => 'Status',
        'ip'         => 'IP',
        'user_agent' => 'User-Agent',
    ],

    'columns' => [
        'product'    => 'Product',
        'name'       => 'Name',
        'email'      => 'Email',
        'rating'     => '★',
        'status'     => 'Status',
        'created_at' => 'Added',
    ],

    'actions' => [
        'publish'      => 'Publish',
        'reject'       => 'Reject',
        'publish_bulk' => 'Publish selected',
        'reject_bulk'  => 'Reject selected',
    ],

    'filters' => [
        'status' => 'Status',
    ],
];

