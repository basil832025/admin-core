<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;
class CategoryCharacteristic extends Model
{
   // use HasTranslations;
    protected $table = 'category_characteristic';
    protected $fillable = ['category_id', 'characteristic_id', 'affects_price'];
    public $translatable = ['name']; // добавь свои переводимые поля
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function characteristic(): BelongsTo
    {
        return $this->belongsTo(Characteristic::class, 'characteristic_id');
    }
}
