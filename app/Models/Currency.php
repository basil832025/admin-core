<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $table = 'bs_currencies';
    protected $fillable = [
        'name',
        'code',
        'position',
        'active'

    ];
}
