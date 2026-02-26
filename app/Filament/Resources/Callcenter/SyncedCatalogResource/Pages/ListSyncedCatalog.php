<?php

namespace App\Filament\Resources\Callcenter\SyncedCatalogResource\Pages;

use App\Filament\Resources\Callcenter\SyncedCatalogResource;
use App\Models\Callcenter\Source;
use App\Services\Callcenter\ExternalSyncService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class ListSyncedCatalog extends Page
{
    protected static string $resource = SyncedCatalogResource::class;
    protected static string $view = 'filament.resources.callcenter.synced-catalog-resource.pages.list-synced-catalog';

    public int $defaultSourceId = 0;

    public function mount(): void
    {
        $this->defaultSourceId = (int) (Source::query()
            ->where('is_active', true)
            ->where('sync_enabled', true)
            ->value('id') ?? 0);
    }

    protected function getViewData(): array
    {
        return [
            'defaultSourceId' => $this->defaultSourceId,
            'fetchUrl' => route('admin.callcenter.synced-catalog.data', absolute: false),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncCatalogCurrentSource')
                ->label('Синхронизировать сайт')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->form([
                    Select::make('source_id')
                        ->label('Сайт')
                        ->options(fn () => Source::query()->orderBy('name')->pluck('name', 'id')->toArray())
                        ->default($this->defaultSourceId)
                        ->required()
                        ->searchable()
                        ->preload(),
                ])
                ->action(function (array $data): void {
                    $source = Source::query()->find((int) ($data['source_id'] ?? 0));

                    if (! $source) {
                        Notification::make()->danger()->title('Сайт не найден')->send();
                        return;
                    }

                    $stats = app(ExternalSyncService::class)->syncCatalogForSource($source);

                    Notification::make()
                        ->success()
                        ->title('Синхронизация каталога завершена')
                        ->body("Сайт: {$source->name}. Обработано: {$stats['processed']}. Создано: {$stats['created']}. Обновлено: {$stats['updated']}.")
                        ->send();
                }),

            Action::make('syncCatalogAllSources')
                ->label('Синхронизировать все сайты')
                ->icon('heroicon-m-squares-2x2')
                ->color('primary')
                ->action(function (): void {
                    $stats = app(ExternalSyncService::class)->syncCatalogFromAllSources();

                    Notification::make()
                        ->success()
                        ->title('Синхронизация каталога завершена')
                        ->body("Источников: {$stats['sources']}. Обработано: {$stats['processed']}. Создано: {$stats['created']}. Обновлено: {$stats['updated']}. Ошибок: {$stats['failed']}.")
                        ->send();
                }),
        ];
    }
}
