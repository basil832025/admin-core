<?php

namespace App\Models;

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
    public function group()
    {
        return $this->belongsTo(\App\Models\SiteTextGroup::class, 'group_id');
    }
}
