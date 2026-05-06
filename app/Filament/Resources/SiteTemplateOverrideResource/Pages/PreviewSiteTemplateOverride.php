<?php

namespace App\Filament\Resources\SiteTemplateOverrideResource\Pages;

use App\Filament\Resources\SiteTemplateOverrideResource;
use App\Models\SiteTemplateOverride;
use App\Services\SiteTemplates\TemplatePreviewFactory;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Blade;
use Throwable;

class PreviewSiteTemplateOverride extends Page
{
    protected static string $resource = SiteTemplateOverrideResource::class;

    protected static string $view = 'filament.site-templates.preview-page';

    public SiteTemplateOverride $record;

    public ?string $renderedHtml = null;

    public ?string $renderError = null;

    public function mount(int|string $record, TemplatePreviewFactory $previewFactory): void
    {
        $this->record = SiteTemplateOverride::query()->findOrFail($record);

        $body = (string) ($this->record->override_body ?: $this->record->original_snapshot ?: '');

        try {
            $this->renderedHtml = Blade::render($body, $previewFactory->make((string) $this->record->key), deleteCachedView: true);
        } catch (Throwable $e) {
            $this->renderError = $e->getMessage();
        }
    }

    public function getTitle(): string
    {
        return 'Предпросмотр: ' . $this->record->title;
    }
}
