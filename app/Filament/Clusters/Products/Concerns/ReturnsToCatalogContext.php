<?php

namespace App\Filament\Clusters\Products\Concerns;

use App\Filament\Clusters\Products\Pages\CatalogManager;
use App\Filament\Clusters\Products\Resources\ProductCategoryResource;
use App\Filament\Clusters\Products\Resources\ProductResource;
use Livewire\Attributes\Url;

trait ReturnsToCatalogContext
{
    #[Url(as: 'source')]
    public ?string $returnSource = null;

    #[Url(as: 'return_category')]
    public ?int $returnCategory = null;

    protected function hasExplicitReturnContext(): bool
    {
        return in_array($this->returnSource, ['catalog', 'products', 'categories'], true);
    }

    protected function getCatalogContextReturnUrl(string $fallback): string
    {
        $source = in_array($this->returnSource, ['catalog', 'products', 'categories'], true)
            ? $this->returnSource
            : $fallback;

        return match ($source) {
            'catalog' => CatalogManager::getUrl($this->returnCategory
                ? ['category' => $this->returnCategory]
                : []),
            'categories' => ProductCategoryResource::getUrl('index'),
            default => ProductResource::getUrl('index'),
        };
    }
}
