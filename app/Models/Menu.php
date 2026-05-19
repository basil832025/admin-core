<?php

namespace App\Models;

use App\Services\MenuCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    protected $table = 'bs_menus';

    protected $fillable = [
        'title',
        'slug',
        'locale',
        'max_depth',
        'is_active',
        'sort',
    ];

    protected $casts = [
        'title'     => 'array',
        'is_active' => 'boolean',
        'max_depth' => 'integer',
        'sort'      => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(function (): void {
            app(MenuCacheService::class)->bump();
        });

        static::deleted(function (): void {
            app(MenuCacheService::class)->bump();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'menu_id')
            ->orderBy('parent_id')
            ->orderBy('sort')
            ->orderBy('id');
    }

    /* ==== Скоупы ==== */

    public function scopeBySlug($q, string $slug)
    {
        return $q->where('slug', $slug);
    }

    public function scopeForLocale($q, ?string $locale)
    {
        // если передан null — считаем "универсальным" (неязыковым)
        return $locale
            ? $q->where('locale', $locale)
            : $q->whereNull('locale');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /* ==== Утилиты ==== */

    /**
     * Возвращает простую иерархию пунктов (children внутри) — готово для Blade.
     * Можно навесить кеширование в репозитории, если нужно.
     */
    public function buildTree(): array
    {
        $flat = $this->items()
            ->where('is_active', true)
            ->get()
            ->map(fn (MenuItem $i) => [
                'id'         => $i->id,
                'label'      => $i->label,
                'link_type'  => $i->link_type,
                'target_id'  => $i->target_id,
                'url'        => $i->url,
                'icon'       => $i->icon,
                'parent_id'  => $i->parent_id,
                'sort'       => $i->sort,
            ])
            ->toArray();

        // Собираем дерево in-memory
        $byId = [];
        foreach ($flat as &$row) {
            $row['children'] = [];
            $byId[$row['id']] = &$row;
        }
        unset($row);

        $roots = [];
        foreach ($byId as &$row) {
            if ($row['parent_id']) {
                $byId[$row['parent_id']]['children'][] = &$row;
            } else {
                $roots[] = &$row;
            }
        }
        unset($row);

        // Сортировку уже держим в запросе, но перестрахуемся
        $sortChildren = function (&$nodes) use (&$sortChildren) {
            usort($nodes, fn ($a, $b) => ($a['sort'] <=> $b['sort']) ?: ($a['id'] <=> $b['id']));
            foreach ($nodes as &$n) {
                if (!empty($n['children'])) {
                    $sortChildren($n['children']);
                }
            }
        };
        $sortChildren($roots);

        return $roots;
    }
    private function currentMenuId(): int
    {
        $param = request()->route('menu');

        if ($param instanceof Menu) {
            return $param->getKey();
        }

        if (is_array($param)) {
            $param = $param['menu'] ?? reset($param);
        }

        return (int) $param;
    }

    public function mount($menu = null): void
    {
        $this->menu = Menu::findOrFail($this->currentMenuId());
    }
}
