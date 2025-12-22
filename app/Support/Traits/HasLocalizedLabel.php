<?php

namespace App\Support\Traits;

trait HasLocalizedLabel
{
    protected function pickLabel($model, string $locale): ?string
    {
        if (!$model) return null;

        // кандидаты по приоритету
        $candidates = ['title', 'name', 'short_name', 'label', 'value'];

        // если модель использует Spatie\HasTranslations
        if (method_exists($model, 'isTranslatableAttribute')) {
            foreach ($candidates as $attr) {
                if ($model->isTranslatableAttribute($attr)) {
                    // 3-й параметр = fallback на дефолтный язык (true)
                    $t = $model->getTranslation($attr, $locale, true);
                    if ($t !== null && $t !== '') return (string) $t;
                }
            }
        }

        // обычные не-транслируемые поля
        foreach ($candidates as $attr) {
            if (isset($model->{$attr}) && $model->{$attr} !== '') {
                return (string) $model->{$attr};
            }
        }

        return isset($model->id) ? (string) $model->id : null;
    }
}
