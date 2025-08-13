<?php

namespace App\Filament\Resources\Shop\OrderResource\Pages;

use App\Filament\Resources\Shop\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Shop\OrderResource\Widgets\OrderActivityWidget;
class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    protected function getFooterWidgets(): array
    {
        return [
            OrderActivityWidget::class,   // покажется над формой
        ];
    }
    protected function afterSave(): void
    {
        $this->record->recalculateTotalPrice();
    }
}
