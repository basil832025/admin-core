<?php

return [
    'order_form_mode' => env('CALLCENTER_ORDER_FORM_MODE', 'food'),
    'filters_no' => array_values(array_filter(array_map(
        static fn (string $filter): string => trim($filter),
        explode(',', (string) env('FILTERS_NO', ''))
    ))),
    'actions_no' => array_values(array_filter(array_map(
        static fn (string $action): string => trim($action),
        explode(',', (string) env('ACTIONS_NO', env('CALLCENTER_ACTIONS_NO', '')))
    ))),
];
