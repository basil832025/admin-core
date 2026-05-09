<?php

namespace App\Filament\Clusters\Products\Resources\ProductCategoryResource\Pages;

use App\Filament\Clusters\Products\Resources\ProductCategoryResource;
use App\Models\Shop\ProductCategory;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProductCategory extends EditRecord
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (ProductCategory $record, Actions\DeleteAction $action): void {
                    if (! $record->hasDeleteDependencies()) {
                        return;
                    }

                    Notification::make()
                        ->danger()
                        ->title('Категорію не можна видалити')
                        ->body($record->getDeleteDependencyMessage())
                        ->persistent()
                        ->send();

                    $action->halt();
                }),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
