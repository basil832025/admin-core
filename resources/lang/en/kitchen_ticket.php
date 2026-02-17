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
        'order_time'    => 'Order<br>time',
        'urgent'        => 'Asap',
        'delivery_type' => 'Type',
        'items_count'   => 'Items<br>count',
        'priority'      => 'Priority',
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
        'priority_up'       => 'Increase priority',
        'priority_down'     => 'Decrease priority',
        'priority_set_urgent' => 'Urgent',
        'priority_set_normal' => 'Normal',
        'priority_set_wait'   => 'Can wait',
        'save'              => 'Save',
        'close'             => 'Close',
    ],

    'modals' => [
        'order_items_heading'   => 'Order items :number',
        'confirm_prepared_heading' => 'Confirm order completion',
        'confirm_prepared_description' => 'The order will be moved to archive and will go to assembly/pickup.',
        'delivery_heading' => 'Delivery for order :number',
        'delivery_address' => 'Address',
        'delivery_address_empty' => 'Address not provided',
        'delivery_note' => 'Note',
        'delivery_note_empty' => 'No note',
        'apartment_short' => 'apt. :value',
        'order_note_heading' => 'Order note :number',
        'order_note_label' => 'Note',
        'order_note_empty' => 'No note',
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


