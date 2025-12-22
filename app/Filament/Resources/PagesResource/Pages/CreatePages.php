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
use Illuminate\Support\Str;

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
        // Нормализуем slug (если нужно)
        $data['slug'] = Str::slug($data['slug'] ?? ($data['title']['uk'] ?? $data['title'] ?? ''));

        // По желанию: чистим «пустые» HTML-переводы, чтобы не хранить <p><br></p>
        if (isset($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $loc => $val) {
                $plain = trim(preg_replace('/\x{00A0}/u', ' ', strip_tags($val ?? '')));
                if ($plain === '') {
                    $data['content'][$loc] = null; // или unset($data['content'][$loc]);
                }
            }
        }

        // Проверка дублей slug в блоках
        $seen = [];
        foreach ($data['fields'] ?? [] as $b) {
            $slug = data_get($b, 'data.slug');
            if (!$slug) continue;
            if (isset($seen[$slug])) {
                throw ValidationException::withMessages([
                    'fields' => "Слаг «{$slug}» повторяется в блоках.",
                ]);
            }
            $seen[$slug] = true;
        }

        return $data;
    }
}
