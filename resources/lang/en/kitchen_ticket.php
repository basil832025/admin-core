<?php

return [
    'nav' => [
        'navigation_group'   => 'KitchenTouch',
        'navigation_label'   => 'KitchenTouch',
        'model_label'        => 'kitchen ticket',
        'plural_model_label' => 'kitchen tickets',
    ],

    'columns' => [
        'order_number'  => 'Order #',
        'order_time'    => 'Order time',
        'urgent'        => 'Urgent',
        'delivery_type' => 'Type',
        'items_count'   => 'Items count',
        'stage'         => 'Stage',
    ],

    'filters' => [
        'status'        => 'Status',
        'urgent'        => 'Urgent',
        'delivery_type' => 'Type',
    ],

    'filter_options' => [
        'current'       => 'Current',
        'archived'      => 'Archived',
        'any'           => 'Any',
        'urgent_only'   => 'Urgent only',
        'normal_only'   => 'Normal only',
        'delivery'      => 'Delivery',
        'pickup'        => 'Pickup',
    ],

    'actions' => [
        'current_orders'    => 'Current orders',
        'archived'          => 'Archived',
        'to_filling'        => 'Filling/Accepted',
        'to_molding'        => 'Molding',
        'to_baking'         => 'Baking',
        'to_prepared'       => 'Prepared',
        'save'              => 'Save',
    ],

    'modals' => [
        'order_items_heading'   => 'Order items :number',
        'confirm_prepared_heading' => 'Confirm order completion',
        'confirm_prepared_description' => 'The order will be moved to archive and will go to assembly/pickup.',
    ],

    'stages' => [
        'accepted'  => 'Accepted',
        'filling'   => 'Filling',
        'molding'   => 'Molding',
        'baking'    => 'Baking',
        'ready'     => 'Ready',
        'stage'     => 'Stage',
    ],

    'table_headers' => [
        'product'  => 'Product',
        'quantity'  => 'Qty',
    ],

    'empty_state' => [
        'heading'      => 'No orders',
        'description'  => 'As soon as the operator moves the order to "In progress", it will appear here.',
    ],

    'values' => [
        'yes'       => 'Yes',
        'no'        => '—',
        'delivery'  => 'Delivery',
        'pickup'    => 'Pickup',
    ],

    'notifications' => [
        'marks_saved' => 'Marks saved',
    ],

    'helpers' => [
        'calculation_missing' => 'Calculation missing',
    ],
];

