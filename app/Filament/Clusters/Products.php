<?php

namespace App\Filament\Clusters;

use App\Filament\Clusters\Products\Pages\CatalogManager;
use Filament\Clusters\Cluster;

class Products extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $slug = 'catalog/products';
    protected static ?string $navigationGroup = null;
    // НЕ хардкодим строкой — вернём перевод
    protected static ?string $navigationLabel = null;

    public static function getClusteredComponents(): array
    {
        return array_values(array_unique([
            CatalogManager::class,
            ...parent::getClusteredComponents(),
        ]));
    }

    public function mount(): void
    {
        $this->redirect(CatalogManager::getUrl(), navigate: true);
    }

    public static function getNavigationLabel(): string
    {
        return __('product.nav.cluster');            // «Продукти» / «Продукты» / «Products»
    }

    public static function getClusterBreadcrumb(): string
    {
        return __('product.nav.cluster');
    }




    // Группа меню тоже через перевод
    public static function getNavigationGroup(): ?string
    {
        return __('product.nav.group');              // добавь ключ в lang: «Магазин»
    }
}
