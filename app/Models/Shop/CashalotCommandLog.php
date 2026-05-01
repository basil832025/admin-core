<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;

class CashalotCommandLog extends Model
{
    protected $table = 'bs_cashalot_command_logs';

    protected $fillable = [
        'admin_user_id',
        'command',
        'prro_num_fiscal',
        'request_payload',
        'response_payload',
        'status',
        'error_code',
        'error_message',
        'result_num_fiscal',
        'shift_id',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];
}
