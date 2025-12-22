<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Session;
use Spatie\Translatable\HasTranslations;

class MenuItem extends Model
{
    use HasTranslations;
    protected $table = 'bs_menu_items';

    protected $fillable = [
        'menu_id',
        'parent_id',
        'title',
        'link_type',
        'target_id',
        'url',
        'icon',
        'is_active',
        'auth_only',
        'sort',
        'visible_from',
        'visible_to',
    ];

    protected $casts = [
        'title'        => 'array',
        'is_active'    => 'boolean',
        'sort'         => 'integer',
        'visible_from' => 'datetime',
        'visible_to'   => 'datetime',
    ];
    /** === ВАЖНО: сообщаем плагину имена колонок === */
    public function determineParentColumnName(): string { return 'parent_id'; }
    public function determineOrderColumnName(): string  { return 'sort'; }
    public function determineTitleColumnName(): string  { return 'title'; }
    public static function defaultParentKey()           { return -1; }
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /* ==== Скоупы ==== */

    public function scopeActiveNow($q)
    {
        $now = now();
        return $q->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('visible_from')->orWhere('visible_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('visible_to')->orWhere('visible_to', '>=', $now);
            });
    }



    /**
     * Быстрая проверка “попадает ли сейчас в окно видимости”.
     */
    public function isVisibleNow(): bool
    {
        $now = now();
        if (!$this->is_active) return false;
        if ($this->visible_from && $this->visible_from->isFuture()) return false;
        if ($this->visible_to && $this->visible_to->isPast()) return false;
        return true;
    }
    // Модельный дефолт на тот случай, если из формы пришло null
    protected $attributes = [
        'parent_id' => -1,
    ];

    protected static function booted(): void
    {
        // При СОЗДАНИИ
        static::creating(function (self $m) {
            // menu_id: из страницы/сессии/роута (что найдётся)
            if (empty($m->menu_id)) {
                $m->menu_id = (int) (Session::get('current_menu_id')
                    ?? (function () {
                        $p = request()->route('menu');
                        if ($p instanceof Menu) return $p->getKey();
                        if (is_array($p))     return (int) ($p['menu'] ?? reset($p));
                        return (int) $p;
                    })()
                );
            }

            // parent_id: если не задан — корень = -1
            if ($m->parent_id === null || $m->parent_id === '') {
                $m->parent_id = -1;
            }
        });

        // При ЛЮБОМ сохранении (в т.ч. reorder)
        static::saving(function (self $m) {
            if ($m->parent_id === null || $m->parent_id === '') {
                $m->parent_id = -1;
            }
        });
    }
    public function children()
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort');
    }

    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }
}

