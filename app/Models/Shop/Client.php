<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class Client extends Model
{
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

    public function addresses(): HasMany
    {
        return $this->hasMany(ClientAddress::class);
    }
}
