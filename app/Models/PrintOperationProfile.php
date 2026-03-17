<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintOperationProfile extends Model
{
    use HasFactory;

    protected $table = 'bs_print_operation_profiles';

    protected $fillable = [
        'name',
        'operation_code',
        'print_template_id',
        'printer_id',
        'printer_name',
        'copies',
        'paper_settings',
        'param_bindings',
        'is_active',
    ];

    protected $casts = [
        'paper_settings' => 'array',
        'param_bindings' => 'array',
        'is_active' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(PrintTemplate::class, 'print_template_id');
    }
}
