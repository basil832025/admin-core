<?php

namespace App\Filament\Resources\BlogResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\BlogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;

class EditBlog extends EditRecord
{
    protected static string $resource = BlogResource::class;
    protected static ?string $navigationLabel = 'Редактировать блог';
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    /** JSON -> rows (когда открываем форму) */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['preview_image_i18n_rows'] = $this->mapJsonToRows($data['preview_image_i18n'] ?? []);
        $data['detail_image_i18n_rows']  = $this->mapJsonToRows($data['detail_image_i18n']  ?? []);
        return $data;
    }
    /** rows -> JSON (перед сохранением) */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $locales = \App\Models\Setting::getActiveLocales(); // или твоя функция получения локалей
        $allowed = array_flip($locales);

        $data['preview_image_i18n'] = $this->mapRowsToJson($data['preview_image_i18n_rows'] ?? [], $allowed);
        $data['detail_image_i18n']  = $this->mapRowsToJson($data['detail_image_i18n_rows']  ?? [], $allowed);

        unset($data['preview_image_i18n_rows'], $data['detail_image_i18n_rows']);

        return $data;
    }
    /** helpers */
    private function mapJsonToRows(array|string|null $raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }
        if (!is_array($raw)) {
            $raw = [];
        }

        $rows = [];
        foreach ($raw as $lang => $path) {
            if (is_string($lang) && $lang !== '' && is_string($path) && $path !== '') {
                $rows[] = ['lang' => $lang, 'file' => $path];
            }
        }
        return array_values($rows);
    }

    private function mapRowsToJson(array $rows, array $allowed): array
    {
        $map = [];
        foreach ($rows as $r) {
            $lang = (string)($r['lang'] ?? '');
            $val  = $r['file'] ?? null;

            if (is_array($val)) {
                $path = $val['path'] ?? $val['url'] ?? $val['file'] ?? (is_string(reset($val)) ? reset($val) : '');
            } else {
                $path = is_string($val) ? $val : '';
            }

            if ($lang !== '' && $path !== '' && isset($allowed[$lang])) {
                $map[$lang] = $path;
            }
        }
        return $map;
    }
}
