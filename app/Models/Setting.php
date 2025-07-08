<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'site_name',
        'logo_path',
        'favicon_path',
        'phone',
        'email',
        'social_links',
        'admin_color_scheme',
        'default_language_code',
    ];
    // Если соцссылки сохраняются как JSON
    protected $casts = [
        'social_links' => 'array',
    ];
}
