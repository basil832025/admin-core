<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
//use Spatie\Translatable\HasTranslations;
class CategoryVariation extends Model
{
   // use HasTranslations;
    protected $table = 'category_variation';
    protected $fillable = ['category_id', 'variation_id'];
   // public $translatable = ['name']; // добавь свои переводимые поля
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(Variation::class, 'variation_id');
    }
}
