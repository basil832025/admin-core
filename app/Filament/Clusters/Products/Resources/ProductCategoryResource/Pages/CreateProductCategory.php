<?php

namespace App\Filament\Clusters\Products\Resources\ProductCategoryResource\Pages;

use App\Filament\Clusters\Products\Resources\ProductCategoryResource;
use App\Filament\Clusters\Products\Concerns\ReturnsToCatalogContext;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateProductCategory extends CreateRecord
{
    use ReturnsToCatalogContext;

    protected static string $resource = ProductCategoryResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getCatalogContextReturnUrl('categories');
    }
}
