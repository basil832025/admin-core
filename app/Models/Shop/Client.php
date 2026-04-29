<?php

namespace App\Models\Shop;

//use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Client extends Authenticatable
{
    use Notifiable;
    protected $table = 'bs_clients';
    protected $fillable = [
        'name',
        'phone',
        'email',
        'birthday',
        'note',
        'client_group_id',
        'is_active',
        'gender',
        'password',
        'photo',
        'phone_verified_at',
    ];
    protected $hidden = ['password','remember_token'];
    protected $casts = [
        'birthday' => 'date',
        'is_active' => 'boolean',
        'phone_verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Client $client) {
            if (!empty($client->password) && !str_starts_with($client->password, '$2y$')) {
                $client->password = Hash::make($client->password);
            }
        });
    }
    // Возврат полной ссылки на аватар
    public function getAvatarUrlAttribute(): string
    {
        // Если фото загружено — формируем публичный URL из storage
        if (!empty($this->photo)) {
            // Если уже абсолютная ссылка (например, с https://)
            if (str_starts_with($this->photo, 'http')) {
                return $this->photo;
            }

            $photoPath = str_replace('\\', '/', (string) $this->photo);
            $photoPath = ltrim($photoPath, '/');

            $diskPath = str_starts_with($photoPath, 'storage/')
                ? ltrim(substr($photoPath, strlen('storage/')), '/')
                : $photoPath;

            if (! Storage::disk('public')->exists($diskPath)) {
                return asset('images/avatar-empty.svg');
            }

            if (str_starts_with($photoPath, 'storage/')) {
                return '/' . $photoPath;
            }

            // Если файл есть в public storage
            return asset('storage/' . $photoPath);
        }

        // 🔹 Путь к дефолтной иконке (например, “пустой аватар”)
        return asset('images/avatar-empty.svg');
    }

    public function favorites()
    {
        return $this->belongsToMany(
            \App\Models\Shop\Product::class,
            'bs_favorites',
            'client_id',
            'product_id'
        )->withTimestamps();
    }
    public function setPhoneAttribute($value)
    {
        $d = preg_replace('/\D+/', '', (string)$value);
        if (str_starts_with($d,'0')) $d = '38'.$d;
        if (strlen($d) === 9)        $d = '380'.$d;
        $this->attributes['phone'] = $d;
    }

    public function hasFavorite($productId)
    {
        return $this->favorites()->where('product_id', $productId)->exists();
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

    public function group(): BelongsTo
    {
        return $this->belongsTo(ClientGroup::class, 'client_group_id');
    }
}
