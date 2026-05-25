<?php

namespace App\Filament\Resources\Shop\PromoCodeResource\Pages;

use App\Filament\Resources\Shop\PromoCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePromoCode extends CreateRecord
{
    protected static string $resource = PromoCodeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['discount_type'] ?? 'percent') === 'fixed') {
            $data['percent'] = 0;
        } else {
            $data['amount'] = null;
        }

        return $data;
    }
}
