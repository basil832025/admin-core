<?php
// app/Models/Location.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Location extends Model
{
    use HasTranslations;
    protected $table = 'bs_locations';

    protected $fillable = [
        'title',
        'city',
        'address',
        'lat',
        'lng',
        'google_map_link',
        'svg_image_id',
        'phones',
        'emails',
        'schedule',
        'is_active',
        'sort',
        'slug',
    ];

    protected $casts = [
        'title'    => 'array',
        'city'     => 'array',
        'address'  => 'array',
        'phones'   => 'array',
        'emails'   => 'array',
        'schedule' => 'array',
        'is_active'=> 'boolean',
    ];
    public $translatable = [
        'title',
        'city',
        'address',
    ];
    public function getTitleForLocale(string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        return $this->title[$locale] ?? null;
    }
    public function svgImage()     { return $this->belongsTo(SvgImage::class, 'svg_image_id'); }
    public function reviews()      { return $this->hasMany(EstablishmentReview::class, 'location_id'); }
    public function scopeActive($q){ return $q->where('is_active', true)->orderBy('sort'); }


}
