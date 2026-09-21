<?php
// app/Models/SiteTextGroup.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteTextGroup extends Model
{
    protected $table = 'bs_site_text_groups';
    protected $fillable = ['slug','title','description','position','active'];
    protected $casts = ['title' => 'array', 'active' => 'bool'];

    public function siteTexts()
    {
        return $this->hasMany(SiteText::class, 'group_id');
    }

    // удобный аксессор для текущего языка
    public function getTitleCurrentAttribute(): string
    {
        $title = $this->title;

        if (is_string($title)) {
            $decodedTitle = json_decode($title, true);
            $title = json_last_error() === JSON_ERROR_NONE ? $decodedTitle : $title;
        }

        $toString = static function (mixed $value): ?string {
            while (is_array($value)) {
                $value = $value['title']
                    ?? $value['value']
                    ?? $value['name']
                    ?? array_values($value)[0]
                    ?? null;
            }

            if (! is_string($value) && ! is_numeric($value)) {
                return null;
            }

            $value = trim((string) $value);

            return $value !== '' ? $value : null;
        };

        if (is_array($title)) {
            $locale = app()->getLocale();
            $localizedTitle = $toString(
                $title[$locale]
                    ?? $title['uk']
                    ?? $title['ru']
                    ?? $title['en']
                    ?? null,
            );

            if ($localizedTitle !== null) {
                return $localizedTitle;
            }
        }

        return $toString($title) ?? (string) $this->slug;
    }
}
