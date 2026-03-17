<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportGroup extends Model
{
    use HasFactory;

    protected $table = 'bs_report_groups';

    protected $fillable = [
        'name',
        'slug',
        'sort',
        'is_active',
    ];

    protected $casts = [
        'sort' => 'integer',
        'is_active' => 'boolean',
    ];

    public function reportTemplates(): HasMany
    {
        return $this->hasMany(PrintTemplate::class, 'report_group_id');
    }
}
