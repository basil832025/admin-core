<?php
namespace App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderAdjustment extends Model
{
    protected $table = 'shop_order_adjustments';
    protected $fillable = [
        'shop_order_id','shop_order_item_id','type','label',
        'amount','promotion_id','promo_code_id','meta',
    ];
    protected $casts = ['meta' => 'array'];
}
