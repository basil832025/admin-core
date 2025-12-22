<?php
// app/Models/Shop/LiqPayLog.php
namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;

class LiqPayLog extends Model
{
    protected $table = 'bs_liqpay_log';

    protected $fillable = [
        'log_date',
        'signature',
        'payment_id',
        'action',
        'status',
        'type',
        'paytype',
        'acq_id',
        'shop_order_id',
        'order_id',
        'liqpay_order_id',
        'description',
        'sender_phone',
        'sender_first_name',
        'sender_last_name',
        'sender_card_mask2',
        'sender_card_bank',
        'sender_card_type',
        'sender_card_country',
        'amount',
        'currency',
        'sender_commission',
        'receiver_commission',
        'amount_debit',
        'amount_credit',
        'commission_debit',
        'commission_credit',
        'language',
        'create_date',
        'end_date',
        'transaction_id',
        'payload',
    ];

    protected $casts = [
        'log_date'            => 'datetime',
        'amount'              => 'float',
        'sender_commission'   => 'float',
        'receiver_commission' => 'float',
        'amount_debit'        => 'float',
        'amount_credit'       => 'float',
        'commission_debit'    => 'float',
        'commission_credit'   => 'float',
        'payload'             => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'shop_order_id');
    }
}
