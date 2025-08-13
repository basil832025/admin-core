<?php

namespace App\Filament\Clusters\Products\Resources\ProductResource\Pages;

use App\Filament\Clusters\Products\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Shop\Product;
use  App\Models\Shop\ProductImage;
class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    protected function mutateFormDataBeforeFill(array $data): array
    {
            $this->record->loadMissing('characteristicValues');

        return $data;
    }
    // после сохранения Работа с доп. логикой (связи, логи и т.п.)

    protected function handleRecordUpdate($record, array $data): Product
    {
        $record->update($data);
        $record->syncFromFormState($data);
        return $record;
    }
    // до сохранения Кастомное обновление модели

}
