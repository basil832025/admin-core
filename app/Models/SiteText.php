<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class SiteText extends Model
{
    use HasTranslations;

    protected $table = 'bs_site_texts';

    protected $fillable = ['group','slug','value','description'];

    public $translatable = ['value'];

    protected $casts = [
        'value' => 'array',
    ];

    protected static function booted(): void
    {
        $clearSiteTextCache = static function (): void {
            foreach (['uk', 'ru', 'en'] as $locale) {
                Cache::forget("st:all:$locale");
            }
        };

        static::saved($clearSiteTextCache);
        static::deleted($clearSiteTextCache);
    }

    public function group()
    {
        return $this->belongsTo(\App\Models\SiteTextGroup::class, 'group_id');
    }
}
