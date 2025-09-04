<?php

namespace App\Filament\Resources\PagesResource\Pages;

use App\Filament\Resources\PagesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\Actions\SaveAction;
use Filament\Resources\Pages\Actions\DeleteAction;
use Filament\Icons\Heroicons;
use Illuminate\Validation\ValidationException;
use App\Models\Setting;
class EditPages extends EditRecord
{
    protected static string $resource = PagesResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $default = Setting::value('default_language_code') ?: config('app.locale');
        $raw = $data['content'][$default] ?? '';
        $plain = trim(preg_replace('/\xc2\xa0/u', ' ', strip_tags($raw)));

        if ($plain === '') {
            // у Resource-страниц Filament v4 state path = 'data'
            $prefix = 'data';
            throw ValidationException::withMessages([
                "{$prefix}.content.{$default}" => 'Поле Контент обязательно.',
            ]);
        }

        return $data;
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
                ->url($this->getResource()::getUrl('index')),

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
