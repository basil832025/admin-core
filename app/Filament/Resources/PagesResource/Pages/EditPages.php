<?php

namespace App\Filament\Resources\PagesResource\Pages;

use App\Filament\Resources\PagesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\Actions\SaveAction;
use Filament\Resources\Pages\Actions\DeleteAction;
use Filament\Icons\Heroicons;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\Setting;
class EditPages extends EditRecord
{
    protected static string $resource = PagesResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $default = Setting::value('default_language_code') ?: config('app.locale');
       // $data['slug'] = Str::slug($data['slug'] ?? ($data['title']['uk'] ?? $data['title'] ?? ''));

        // По желанию: чистим «пустые» HTML-переводы, чтобы не хранить <p><br></p>
    /*    if (isset($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $loc => $val) {
                $plain = trim(preg_replace('/\x{00A0}/u', ' ', strip_tags($val ?? '')));
                if ($plain === '') {
                    $data['content'][$loc] = null; // или unset($data['content'][$loc]);
                }
            }
        }*/
        $seen = [];
        foreach ($data['fields'] ?? [] as $b) {
            $slug = $b['data']['slug'] ?? null;
            if (!$slug) continue;
            if (isset($seen[$slug])) {
                throw \Filament\Support\Exceptions\Halt::make()
                    ->withMessage("Слаг «{$slug}» повторяется в блоках.");
            }
            $seen[$slug] = true;
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
 /*   protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }*/

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
