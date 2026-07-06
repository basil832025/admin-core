<?php

namespace App\Filament\Clusters\Products\Resources\ProductPriceChangeBatchResource\Pages;

use App\Filament\Clusters\Products\Resources\ProductPriceChangeBatchResource;
use Filament\Resources\Pages\ListRecords;

class ListProductPriceChangeBatches extends ListRecords
{
    protected static string $resource = ProductPriceChangeBatchResource::class;
}
