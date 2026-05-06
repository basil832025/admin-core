<?php

namespace App\Filament\Resources\SiteTemplateOverrideResource\Pages;

use App\Filament\Resources\SiteTemplateOverrideResource;
use App\Services\SiteTemplates\TemplateSyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSiteTemplateOverrides extends ListRecords
{
    protected static string $resource = SiteTemplateOverrideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync')
                ->label('Синхронизировать из файлов')
                ->icon('heroicon-o-arrow-path')
                ->action(function (TemplateSyncService $service): void {
                    $count = $service->sync();

                    Notification::make()
                        ->title('Шаблоны синхронизированы')
                        ->body("Обновлено шаблонов: {$count}")
                        ->success()
                        ->send();
                }),
        ];
    }
}
