<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class ClientGroup extends Model
{
    use HasTranslations;

    protected $table = 'bs_client_groups';

    protected $fillable = [
        'name',
        'is_active',
        'is_blacklist',
    ];

    protected $casts = [
        'name' => 'array',
        'is_active' => 'boolean',
        'is_blacklist' => 'boolean',
    ];

    public array $translatable = [
        'name',
    ];

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'client_group_id');
    }

    public function getDisplayNameAttribute(): string
    {
        $locale = app()->getLocale();

        return (string) ($this->getTranslation('name', $locale, false)
            ?: $this->getTranslation('name', 'uk', false)
            ?: $this->getTranslation('name', 'ru', false)
            ?: $this->getTranslation('name', 'en', false)
            ?: '—');
    }
}
