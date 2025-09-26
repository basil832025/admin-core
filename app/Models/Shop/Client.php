<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class Client extends Model
{
    protected $table = 'bs_clients';
    protected $fillable = [
        'name',
        'phone',
        'email',
        'birthday',
        'note',
        'is_active',
        'gender',
        'password',
        'photo',
    ];

    protected $casts = [
        'birthday' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Client $client) {
            if (!empty($client->password) && !str_starts_with($client->password, '$2y$')) {
                $client->password = Hash::make($client->password);
            }
        });
    }
    // Красивый вывод телефона
    public function getPhonePrettyAttribute(): string
    {
        $d = preg_replace('/\D+/', '', (string) $this->phone);
        // приводим к локальным 10 цифрам (обрежем +38/380/0 в начале)
        $d = preg_replace('/^(38)?0?/', '', $d);
        if (strlen($d) !== 10) {
            return $this->phone ?: '-';
        }
        return '+38 (' . substr($d, 0, 2) . ') ' . substr($d, 2, 3) . '-' . substr($d, 5, 2) . '-' . substr($d, 7, 2);
    }
    public function addresses(): HasMany
    {
        return $this->hasMany(ClientAddress::class);
    }
}
