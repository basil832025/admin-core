<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientRecipient extends Model
{
    protected $table = 'bs_client_recipients';

    protected $fillable = [
        'client_id',
        'surname',
        'name',
        'patronymic',
        'phone',
        'is_default',
    ];

    protected $casts = [
        'client_id' => 'integer',
        'is_default' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->surname,
            $this->name,
            $this->patronymic,
        ])));
    }

    public function setPhoneAttribute($value): void
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if (str_starts_with($digits, '0')) {
            $digits = '38' . $digits;
        }

        if (strlen($digits) === 9) {
            $digits = '380' . $digits;
        }

        $this->attributes['phone'] = $digits;
    }
}
