<?php

return [
    // Enable fiscalization integration
    'enabled' => (bool) env('CASHALOT_ENABLED', false),

    // API endpoint
    'api_url' => (string) env('CASHALOT_API_URL', 'https://fsapi.cashalot.org.ua/'),

    // HTTP timeout in seconds
    'timeout' => (int) env('CASHALOT_TIMEOUT', 20),

    // Номер ПРРО
    'numfiscal' => (string) env('CASHALOT_NUMFISCAL', ''),
    // Сертефикат в формате Base64
    'certificate' => (string) env('CASHALOT_CERTIFICATE', ''),
    // Подпись в формате Base64
    'key' => (string) env('CASHALOT_KEY', ''),
    // Пароль от подписи
    'password' => (string) env('CASHALOT_PASSWORD', ''),

    // Smart ID / PIN mode
    'use_smart_id' => (bool) env('CASHALOT_USE_SMART_ID', false),
    'key_pin' => (string) env('CASHALOT_KEY_PIN', ''),

    // RegisterCheck options
    'auto_open_shift' => (bool) env('CASHALOT_AUTO_OPEN_SHIFT', true),
    'get_qr_code' => (bool) env('CASHALOT_GET_QR_CODE', true),
    'qr_size' => (int) env('CASHALOT_QR_SIZE', 512),
    'visualization' => (bool) env('CASHALOT_VISUALIZATION', true),
    'vis_as_html' => (bool) env('CASHALOT_VIS_AS_HTML', false),

    // Send fiscal check to consumer
    'send_to_consumer' => (bool) env('CASHALOT_SEND_TO_CONSUMER', true),
    // 0 = SMS, 1 = Viber
    'consumer_service_type' => (int) env('CASHALOT_CONSUMER_SERVICE_TYPE', 0),
];
