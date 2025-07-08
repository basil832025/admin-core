<?php

namespace App\Filament\Resources\PagesResource\Pages;

use App\Filament\Resources\PagesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\Actions\SaveAction;
use Filament\Resources\Pages\Actions\DeleteAction;
use Filament\Icons\Heroicons;
class EditPages extends EditRecord
{
    protected static string $resource = PagesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // наша новая кнопка «Сохранить» в шапке
          //  SaveAction::make(),
           // $this->getSaveFormAction(),
            // встроенная кнопка "Сохранить"
            $this->getSaveFormAction()
                ->label('Сохранить')
             //   ->icon(Heroicons::class, 'outline-save') // указываем класс Filament\Icons\Heroicons
                ->formId('form'), // <-- должно совпадать с ID вашей формы
           /* Actions\SaveAction::make()
                ->label('Сохранить')
                ->color('primary'),*/
            Actions\DeleteAction::make(),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public static function getResource(): string
    {
        return PagesResource::class;
    }
    /**
     * Footer-действия (те же самые, что по-умолчанию внизу формы)
     */
    protected function getFooterActions(): array
    {
        return [
            Actions\SaveAction::make()
                ->label('Сохранить')
                ->color('primary'),
        ];
    }
}
