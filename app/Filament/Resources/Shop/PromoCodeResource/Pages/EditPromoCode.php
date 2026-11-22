<?php

namespace App\Filament\Resources\Shop\PromoCodeResource\Pages;

use App\Filament\Resources\Shop\PromoCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPromoCode extends EditRecord
{
    protected static string $resource = PromoCodeResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['discount_type'] ?? 'percent') === 'fixed') {
            $data['percent'] = 0;
        } else {
            $data['amount'] = null;
        }

        if (($data['discount_type'] ?? 'percent') === 'gift') {
            $data['percent'] = 0;
        } elseif (config('project.name') === '3piroga') {
            $data['gift_product_id'] = null;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
