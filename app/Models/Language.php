<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $table = 'bs_languages';
    // Разрешаем массовое заполнение этих полей
    protected $fillable = [
        'name',
        'code',
        'country_code',
        'position',
        'active'

    ];
    protected $casts = [
        'active' => 'bool',
    ];

    public static function activeCodesOrdered(): array
    {
        return static::query()
            ->where('active', true)
            ->orderBy('position')
            ->pluck('code')                 // ['uk','en','ru', ...]
            ->map(fn ($c) => strtolower($c))
            ->values()
            ->all();
    }
    public function scopeActive($q)
    {
        return $q->where('active', true);
    }
}
