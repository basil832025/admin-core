<?php

namespace App\Filament\Clusters\Products\Resources\ProductReviewResource\Pages;

use App\Filament\Clusters\Products\Resources\ProductReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateProductReview extends CreateRecord
{
    protected static string $resource = ProductReviewResource::class;
}
