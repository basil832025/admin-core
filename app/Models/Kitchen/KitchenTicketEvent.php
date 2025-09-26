<?php

namespace App\Models\Kitchen;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenTicketEvent extends Model
{
    protected $table = 'bs_kitchen_ticket_events';
    protected $fillable = ['kitchen_ticket_id','from_stage','to_stage','user_id','meta'];
    protected $casts = ['meta' => 'array'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(KitchenTicket::class, 'kitchen_ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
