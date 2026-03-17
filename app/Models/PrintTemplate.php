<?php

namespace App\Models;

use App\Enums\PrintTemplateType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrintTemplate extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'bs_print_templates';

    protected $fillable = [
        'name',
        'code',
        'type',
        'report_group_id',
        'engine',
        'output_format',
        'default_paper_preset',
        'default_paper_width_mm',
        'default_paper_height_mm',
        'default_margin_top_mm',
        'default_margin_right_mm',
        'default_margin_bottom_mm',
        'default_margin_left_mm',
        'description',
        'editor_mode',
        'editor_meta',
        'css_preset',
        'custom_css',
        'template_body',
        'parameters_schema',
        'data_sources',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => PrintTemplateType::class,
        'report_group_id' => 'integer',
        'editor_meta' => 'array',
        'css_preset' => 'string',
        'default_paper_width_mm' => 'float',
        'default_paper_height_mm' => 'float',
        'default_margin_top_mm' => 'float',
        'default_margin_right_mm' => 'float',
        'default_margin_bottom_mm' => 'float',
        'default_margin_left_mm' => 'float',
        'parameters_schema' => 'array',
        'data_sources' => 'array',
        'is_active' => 'boolean',
    ];

    public function operationProfiles(): HasMany
    {
        return $this->hasMany(PrintOperationProfile::class, 'print_template_id');
    }

    public function reportGroup(): BelongsTo
    {
        return $this->belongsTo(ReportGroup::class, 'report_group_id');
    }
}
