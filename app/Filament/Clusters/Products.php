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

}
