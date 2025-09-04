<?php

namespace App\Filament\Resources\PagesResource\Pages;

use App\Filament\Resources\PagesResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Pages\Actions\CancelAction;
use Filament\Pages\Actions\SaveAction;
use Filament\Pages\Actions\CreateAction;
use App\Models\Setting;
use Illuminate\Validation\ValidationException;
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
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $default = Setting::value('default_language_code') ?: config('app.locale');
        $raw = $data['content'][$default] ?? '';

        // вычищаем «пустой» HTML: <p></p>, <p><br></p>, NBSP и т.п.
        $plain = trim(preg_replace('/\xc2\xa0/u', ' ', strip_tags($raw)));

        if ($plain === '') {
            throw ValidationException::withMessages([
                "content.$default" => 'Поле Контент обязательно.',
            ]);
        }

        return $data;
    }
}
