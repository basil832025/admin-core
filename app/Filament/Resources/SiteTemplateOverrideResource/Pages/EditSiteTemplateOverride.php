<?php

namespace App\Filament\Resources\SiteTemplateOverrideResource\Pages;

use App\Filament\Resources\SiteTemplateOverrideResource;
use App\Models\SiteTemplateOverride;
use App\Models\SiteTemplateOverrideVersion;
use App\Services\SiteTemplates\TemplatePreviewFactory;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Throwable;

class EditSiteTemplateOverride extends EditRecord
{
    protected static string $resource = SiteTemplateOverrideResource::class;

    protected function handleRecordUpdate($record, array $data): SiteTemplateOverride
    {
        $note = trim((string) ($this->data['change_note'] ?? ''));
        $previous = (string) ($record->override_body ?? '');
        $next = (string) ($data['override_body'] ?? '');

        $record->fill($data);
        $record->updated_by = auth()->id();
        $record->save();

        if ($previous !== $next && $note !== '') {
            $version = SiteTemplateOverrideVersion::query()
                ->where('site_template_override_id', $record->getKey())
                ->latest('id')
                ->first();

            if ($version && $version->body === $previous && $version->change_note === null) {
                $version->update(['change_note' => $note]);
            }
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('previewTemplate')
                ->label('Предпросмотр')
                ->icon('heroicon-o-eye')
                ->modalSubmitAction(false)
                ->modalWidth('7xl')
                ->modalContent(function (TemplatePreviewFactory $previewFactory) {
                    $body = (string) ($this->data['override_body'] ?? $this->record->override_body ?? $this->record->original_snapshot ?? '');
                    $html = null;
                    $error = null;

                    try {
                        $html = Blade::render($body, $previewFactory->make((string) $this->record->key), deleteCachedView: true);
                    } catch (Throwable $e) {
                        $error = $e->getMessage();
                    }

                    return view('filament.site-templates.preview-template-modal', [
                        'html' => $html,
                        'error' => $error,
                    ]);
                }),
            Actions\Action::make('openLivePreview')
                ->label('Открыть живой preview')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => route('admin.site-template-overrides.preview', ['record' => $this->record]))
                ->openUrlInNewTab(),
            Actions\Action::make('showOriginal')
                ->label('Посмотреть оригинал')
                ->icon('heroicon-o-eye')
                ->modalSubmitAction(false)
                ->modalContent(fn () => view('filament.site-templates.original-template-modal', ['record' => $this->record])),
            Actions\Action::make('restoreOriginal')
                ->label('Сбросить к оригиналу')
                ->icon('heroicon-o-arrow-uturn-left')
                ->requiresConfirmation()
                ->action(function (): void {
                    $sourcePath = trim((string) ($this->record->source_path ?? ''));
                    $absolutePath = $sourcePath !== '' ? base_path($sourcePath) : '';

                    if ($absolutePath === '' || ! File::exists($absolutePath)) {
                        Notification::make()
                            ->title('Файл оригинального шаблона не найден')
                            ->body($sourcePath !== '' ? $sourcePath : 'Для шаблона не задан source_path.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $body = File::get($absolutePath);

                    $this->record->update([
                        'override_body' => $body,
                        'original_snapshot' => $body,
                        'original_hash' => sha1($body),
                        'last_synced_at' => now(),
                        'is_active' => true,
                        'updated_by' => auth()->id(),
                    ]);

                    Notification::make()->title('Оригинал из файла восстановлен')->success()->send();
                    $this->refreshFormData(['override_body', 'original_snapshot', 'original_hash', 'last_synced_at', 'is_active']);
                }),
            Actions\Action::make('restoreVersion')
                ->label('Восстановить версию')
                ->icon('heroicon-o-clock')
                ->form([
                    Select::make('version_id')
                        ->label('Версия')
                        ->options(fn () => $this->record->versions()
                            ->latest('created_at')
                            ->get()
                            ->mapWithKeys(fn (SiteTemplateOverrideVersion $version) => [
                                $version->getKey() => sprintf(
                                    '%s%s',
                                    optional($version->created_at)->format('d.m.Y H:i:s') ?? '-',
                                    $version->change_note ? ' - ' . $version->change_note : ''
                                ),
                            ])
                            ->all())
                        ->required()
                        ->searchable(),
                ])
                ->action(function (array $data): void {
                    $version = $this->record->versions()->findOrFail($data['version_id']);

                    $this->record->update([
                        'override_body' => $version->body,
                        'is_active' => true,
                        'updated_by' => auth()->id(),
                    ]);

                    Notification::make()->title('Версия восстановлена')->success()->send();
                    $this->refreshFormData(['override_body', 'is_active']);
                }),
            Actions\Action::make('compareOriginal')
                ->label('Сравнить с оригиналом')
                ->icon('heroicon-o-arrows-right-left')
                ->modalSubmitAction(false)
                ->modalWidth('7xl')
                ->modalContent(fn () => view('filament.site-templates.diff-template-modal', ['record' => $this->record])),
            ...parent::getHeaderActions(),
        ];
    }

    public function restoreVersionFromHistory(int $versionId): void
    {
        $version = $this->record->versions()->findOrFail($versionId);

        $this->record->update([
            'override_body' => $version->body,
            'is_active' => true,
            'updated_by' => auth()->id(),
        ]);

        Notification::make()->title('Версия восстановлена')->success()->send();
        $this->refreshFormData(['override_body', 'is_active']);
    }
}
