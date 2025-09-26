<?php
namespace App\Models\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
//use Spatie\Translatable\HasTranslations;
class ProductVariation extends Model
{
    protected $table = 'bs_product_variation';

    protected $fillable = [
        'product_id',
        'variation_id',
        'price',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variation()
    {
        return $this->belongsTo(Variation::class);
    }
    public function products()
    {
        return $this->belongsToMany(Product::class, 'bs_product_variation')
            ->withPivot('price')
            ->withTimestamps();
    }
}
