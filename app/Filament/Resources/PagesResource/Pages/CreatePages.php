<?php

namespace App\Filament\Resources\PagesResource\Pages;

use App\Filament\Resources\PagesResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Pages\Actions\CancelAction;
use Filament\Pages\Actions\SaveAction;
use Filament\Pages\Actions\CreateAction;
class CreatePages extends CreateRecord
{
    protected static string $resource = PagesResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    protected function getHeaderActions(): array
    {
        return [
            // наша новая кнопка «Сохранить» в шапке
            //  SaveAction::make(),
            // $this->getSaveFormAction(),
            // встроенная кнопка "Сохранить"
            $this->getCancelFormAction()
                ->label('Отмена')
                ->color('warning')
                // можно задать куда вести, по умолчанию вернёт на index-роту
                ->url($this->getResource()::getUrl('index'))

            // кнопка "Создать" для страницы создания
    ];
    }
}
