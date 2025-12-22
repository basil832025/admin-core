<?php

namespace App\Models\Shop;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bs_product_reviews';

    protected $fillable = [
        'product_id', 'name', 'email', 'rating', 'content', 'status', 'ip', 'user_agent',
    ];

    protected $casts = [
        'rating' => 'integer',
        'status' => ReviewStatus::class,
    ];

    // связи
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // скоупы
    public function scopePublished($q)
    {
        return $q->where('status', ReviewStatus::Published);
    }

    public function scopePending($q)
    {
        return $q->where('status', ReviewStatus::Pending);
    }
    // Инициалы для кружка-аватара
    public function getInitialsAttribute(): string
    {
        $name = trim((string) $this->name);
        if ($name === '') return 'U';
        $parts = preg_split('~\s+~u', $name, -1, PREG_SPLIT_NO_EMPTY);
        $letters = array_map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)), $parts);
        return implode('', array_slice($letters, 0, 2));
    }

}
