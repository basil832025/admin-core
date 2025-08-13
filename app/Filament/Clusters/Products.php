<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Products extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $slug = 'catalog/products';
    protected static ?string $navigationGroup = 'Магазин';
    public static function getNavigationLabel(): string
    {
        return __('Продукция');
    }
    // Пускаем/показываем кластер только если у юзера есть права хотя бы на ОДИН ресурс внутри
    public static function canAccess(): bool
    {
        $u = auth()->user();

        return $u?->can('view_any_product')           // Продукты
            || $u?->can('view_any_product_category'); // Категории (подставь свои ключи при необходимости)
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
