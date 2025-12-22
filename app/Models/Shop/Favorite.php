<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    protected $table = 'bs_favorites';
    protected $fillable = ['user_id', 'product_id'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
