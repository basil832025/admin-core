<?php

namespace App\Filament\Resources\EstablishmentReviewResource\Pages;

use App\Filament\Resources\EstablishmentReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEstablishmentReview extends EditRecord
{
    protected static string $resource = EstablishmentReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
