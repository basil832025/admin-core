<?php

namespace App\Filament\Resources\SvgImageResource\Pages;

use App\Filament\Resources\SvgImageResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSvgImage extends CreateRecord
{
    protected static string $resource = SvgImageResource::class;
    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction()
                ->label(__('product.actions.cancel'))
                ->color('warning')
                ->url($this->getResource()::getUrl('index')),

            // Кнопка "Создать" в шапке, отправляет текущую форму
            $this->getCreateFormAction()
                ->label(__('product.actions.create'))
                ->color('primary')
                ->keyBindings(['mod+s'])
                ->formId('form'),

            // Опционально: "Создать и создать ещё"
            $this->getCreateAnotherFormAction()
                ->label(__('product.actions.create_another'))
                ->formId('form'),
        ];
    }
}
