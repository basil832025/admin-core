<?php

namespace App\Filament\Clusters\Products\Resources\ProductCategoryResource\Pages;

use App\Filament\Clusters\Products\Resources\ProductCategoryResource;
use Filament\Actions;
//use App\Filament\Widgets\ProductCategory;
use Filament\Resources\Pages\ListRecords;

class ListProductCategories extends ListRecords
{
    use ListRecords\Concerns\Translatable;
    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make(),
        ];
    }
  /*  protected function getHeaderWidgets(): array
    {
        return [
            ProductCategory::class
        ];
    }*/
}
