<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteTemplateOverrideVersion extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'bs_site_template_override_versions';

    protected $fillable = [
        'site_template_override_id',
        'body',
        'change_note',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'created_by' => 'integer',
        'created_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(SiteTemplateOverride::class, 'site_template_override_id');
    }
}
