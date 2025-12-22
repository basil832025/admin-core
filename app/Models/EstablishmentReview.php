<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EstablishmentReview extends Model
{
    protected $table = 'bs_establishment_reviews';

    protected $fillable = [
        'author_name','author_avatar','rating','text',
        'location_id','is_active','posted_at',
    ];

    protected $casts = ['posted_at' => 'datetime'];

    // Скоупы
    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeForLocation($q, int $id) { return $q->where('location_id', $id); }
    public function scopeNewest($q) { return $q->orderByDesc('posted_at')->orderByDesc('id'); }

    // Аватар как абсолютный URL
    public function getAvatarUrlAttribute(): ?string
    {
        $path = $this->author_avatar;
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;
        return Storage::disk('public')->url($path);
    }
    // Инициалы для кружка-аватара
    public function getInitialsAttribute(): string
    {
        $name = trim((string) $this->author_name);
        if ($name === '') return 'U';
        $parts = preg_split('~\s+~u', $name, -1, PREG_SPLIT_NO_EMPTY);
        $letters = array_map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)), $parts);
        return implode('', array_slice($letters, 0, 2));
    }
    public function location() { return $this->belongsTo(Location::class, 'location_id'); }
}


