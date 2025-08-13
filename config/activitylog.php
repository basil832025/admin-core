<?php

return [

    'enabled' => env('ACTIVITY_LOGGER_ENABLED', true),

    'delete_records_older_than_days' => 365,

    'default_log_name' => 'system',

    'default_auth_driver' => null,

    'subject_returns_soft_deleted_models' => false,

    'activity_model' => \Spatie\Activitylog\Models\Activity::class,

    'table_name' => env('ACTIVITY_LOGGER_TABLE_NAME', 'activity_log'),

    'database_connection' => env('ACTIVITY_LOGGER_DB_CONNECTION'),

    /*
     * Показывать только изменённые поля
     */
    'log_only_dirty' => true,

    /*
     * Игнорировать системные поля, чтобы в лог не попадали timestamps
     */
    'ignore_changed_attributes' => ['updated_at', 'created_at', 'deleted_at'],

    /*
     * Сериализация значений в JSON
     */
    'json_encode_options' => JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,

    /*
     * Формат даты в логах
     */
    'date_format' => 'Y-m-d H:i:s',

];
