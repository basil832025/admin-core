<?php

namespace App\Filament\Clusters\Products\Resources\ProductResource\Concerns;

use App\Models\Setting;
use App\Models\Shop\ProductCategory;

trait HasCharacteristicMeta
{
    public ?int $categoryId = null;
    public array $charMeta = [];

    public function updatedCategoryId($value): void
    {
        $this->charMeta = $this->loadCharMeta($value);
    }

    public function loadCharMeta(?int $categoryId): array
    {
        if (! $categoryId) return [];

        $category = ProductCategory::find($categoryId);
        if (! $category) return [];

        // Берём UI‑локаль для формы
        $locale   = Setting::value('default_language_code') ?: app()->getLocale() ?: config('app.locale');
        $fallback = config('app.fallback_locale');

        $resolveName = function ($c) use ($locale, $fallback): string {
            // 1) текущая локаль
            $name = trim((string)($c->getTranslation('name', $locale) ?? ''));
            if ($name !== '') return $name;

            // 2) fallback локаль, если отличается
            if ($fallback && $fallback !== $locale) {
                $name = trim((string)($c->getTranslation('name', $fallback) ?? ''));
                if ($name !== '') return $name;
            }

            // 3) первый непустой перевод из всех
            if (method_exists($c, 'getTranslations')) {
                $all = (array)$c->getTranslations('name');
                foreach ($all as $v) {
                    $v = trim((string)$v);
                    if ($v !== '') return $v;
                }
            }

            // 4) raw-значение
            $name = trim((string)($c->name ?? ''));
            return $name !== '' ? $name : ('Характеристика #' . $c->id);
        };

        return $category->getAllCharacteristicsWithInheritance(false)
            ->unique('id')
            ->values()
            ->map(function ($c) use ($resolveName) {
                return [
                    'id'           => (int) $c->id,
                    'name'         => $resolveName($c),                         // <— ГЛАВНОЕ
                    'field_type'   => (string) $c->field_type,
                    'pricing_type' => (int) ($c->pivot->pricing_type ?? $c->pricing_type ?? 0),
                    'is_required'  => (bool) ($c->pivot->is_required ?? $c->is_required ?? false),
                ];
            })
            ->all();
    }
}
