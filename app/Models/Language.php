<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    // Разрешаем массовое заполнение этих полей
    protected $fillable = [
        'name',
        'code',
        'country_code',
        'position',
        'active'

    ];
}
