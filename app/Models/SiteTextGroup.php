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
        $loc = app()->getLocale();
        return (string)($this->title[$loc] ?? $this->title['uk'] ?? $this->slug);
    }
}
