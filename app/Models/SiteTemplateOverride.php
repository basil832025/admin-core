<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\SiteTemplateOverrideVersion;

class SiteTemplateOverride extends Model
{
    use HasFactory;

    protected $table = 'bs_site_template_overrides';

    protected $fillable = [
        'key',
        'title',
        'source_path',
        'engine',
        'original_snapshot',
        'override_body',
        'is_active',
        'original_hash',
        'last_synced_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (SiteTemplateOverride $template): void {
            if (! $template->isDirty('override_body')) {
                return;
            }

            $previous = (string) ($template->getOriginal('override_body') ?? '');
            if ($previous === '') {
                return;
            }

            $template->versions()->create([
                'body' => $previous,
                'change_note' => null,
                'created_by' => auth()->id(),
            ]);
        });
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SiteTemplateOverrideVersion::class, 'site_template_override_id');
    }
}
