<?php

namespace App\Filament\Resources\ReportResource\Pages;

use App\Filament\Resources\ReportResource;
use App\Models\PrintTemplate;
use App\Services\Reporting\ReportExecutionService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

class RunReport extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = ReportResource::class;

    protected static string $view = 'filament.resources.report-resource.pages.run-report';

    public array $data = [];

    public string $previewHtml = '';

    /** @var array<string, array<int|string, mixed>> */
    public array $datasets = [];

    public bool $hasResult = false;

    public bool $showParams = true;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        if ($this->record->type?->value !== 'report') {
            abort(404);
        }

        $this->form->fill([
            'params' => $this->defaultParams(),
        ]);

        $this->showParams = true;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Параметры отчета')
                    ->schema([
                        Grid::make(12)
                            ->schema($this->buildParamFields())
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('run')
                ->label('Сформировать')
                ->color('primary')
                ->icon('heroicon-o-play')
                ->action(fn () => $this->runReport()),

            Action::make('export_pdf')
                ->label('Экспорт PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->visible(fn (): bool => $this->hasResult)
                ->action(fn () => $this->exportPdf()),

            Action::make('export_excel')
                ->label('Экспорт Excel')
                ->icon('heroicon-o-table-cells')
                ->visible(fn (): bool => $this->hasResult)
                ->action(fn () => $this->exportExcel()),
        ];
    }

    public function runReport(): void
    {
        try {
            $template = $this->getTemplate();
            $params = $this->currentParams();

            $result = app(ReportExecutionService::class)->execute($template, $params);
            $this->previewHtml = (string) ($result['styled_html'] ?? '');
            $this->datasets = (array) ($result['datasets'] ?? []);
            $this->hasResult = true;
            $this->showParams = false;
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Ошибка формирования отчета')
                ->danger()
                ->body($exception->getMessage())
                ->send();
        }
    }

    public function exportPdf()
    {
        $template = $this->getTemplate();
        $params = $this->currentParams();
        $binary = app(ReportExecutionService::class)->exportPdf($template, $params);

        $filename = 'report-'.$template->code.'-'.now()->format('Ymd_His').'.pdf';

        return response()->streamDownload(function () use ($binary): void {
            echo $binary;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    public function exportExcel()
    {
        $template = $this->getTemplate();
        $params = $this->currentParams();
        $binary = app(ReportExecutionService::class)->exportExcel($template, $params);

        $filename = 'report-'.$template->code.'-'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($binary): void {
            echo $binary;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function getHeading(): string
    {
        return 'Отчет: '.$this->getTemplate()->name;
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    private function buildParamFields(): array
    {
        $template = $this->getTemplate();
        $schema = is_array($template->parameters_schema) ? $template->parameters_schema : [];
        $fields = [];
        $currentParams = $this->currentParams();

        foreach ($schema as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = trim((string) ($item['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            $type = mb_strtolower(trim((string) ($item['type'] ?? 'text')));
            $required = (bool) ($item['required'] ?? false);
            $default = $item['default'] ?? null;

            $fieldPath = 'params.'.$key;

            if ($type === 'date') {
                $fields[] = DatePicker::make($fieldPath)
                    ->label($label !== '' ? $label : $key)
                    ->default(now()->toDateString())
                    ->required($required)
                    ->native(false)
                    ->columnSpan(3);

                continue;
            }

            if ($type === 'number') {
                $fields[] = TextInput::make($fieldPath)
                    ->label($label !== '' ? $label : $key)
                    ->default(is_scalar($default) ? (string) $default : null)
                    ->required($required)
                    ->numeric()
                    ->columnSpan(3);

                continue;
            }

            if ($type === 'boolean') {
                $fields[] = Toggle::make($fieldPath)
                    ->label($label !== '' ? $label : $key)
                    ->default((bool) $default)
                    ->columnSpan(3);

                continue;
            }

            if ($type === 'select') {
                $options = $this->resolveSchemaSelectOptions($item);

                $fields[] = Select::make($fieldPath)
                    ->label($label !== '' ? $label : $key)
                    ->options($options)
                    ->default(is_scalar($default) ? (string) $default : null)
                    ->required($required)
                    ->columnSpan(3);

                continue;
            }

            if ($type === 'dictionary') {
                $fields[] = Select::make($fieldPath)
                    ->label($label !== '' ? $label : $key)
                    ->options($this->resolveDictionaryOptions($item, $currentParams))
                    ->default(is_scalar($default) ? (string) $default : null)
                    ->required($required)
                    ->searchable((bool) ($item['dictionary_searchable'] ?? true))
                    ->columnSpan(3);

                continue;
            }

            $fields[] = TextInput::make($fieldPath)
                ->label($label !== '' ? $label : $key)
                ->default(is_scalar($default) ? (string) $default : null)
                ->required($required)
                ->columnSpan(3);
        }

        if ($fields === []) {
            $fields[] = Forms\Components\Placeholder::make('report_no_params')
                ->label('Параметры')
                ->content('У этого отчета нет входных параметров.')
                ->columnSpanFull();
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultParams(): array
    {
        $params = [];
        $template = $this->getTemplate();
        $schema = is_array($template->parameters_schema) ? $template->parameters_schema : [];

        foreach ($schema as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = trim((string) ($item['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $type = mb_strtolower(trim((string) ($item['type'] ?? 'text')));

            $params[$key] = $type === 'date'
                ? now()->toDateString()
                : ($item['default'] ?? null);
        }

        return $params;
    }

    /**
     * @return array<string, mixed>
     */
    private function currentParams(): array
    {
        $params = data_get($this->data, 'params', []);

        return is_array($params) ? $params : [];
    }

    private function getTemplate(): PrintTemplate
    {
        /** @var PrintTemplate $template */
        $template = $this->record;

        return $template;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, string>
     */
    private function resolveSchemaSelectOptions(array $item): array
    {
        $raw = $item['options'] ?? null;
        if (is_array($raw)) {
            $options = [];
            foreach ($raw as $value => $label) {
                if (! is_scalar($value) || ! is_scalar($label)) {
                    continue;
                }
                $options[(string) $value] = (string) $label;
            }

            return $options;
        }

        $json = trim((string) ($item['options_json'] ?? ''));
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return [];
        }

        $options = [];
        foreach ($decoded as $value => $label) {
            if (! is_scalar($value) || ! is_scalar($label)) {
                continue;
            }
            $options[(string) $value] = (string) $label;
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $params
     * @return array<string, string>
     */
    private function resolveDictionaryOptions(array $item, array $params): array
    {
        $query = trim((string) ($item['dictionary_query'] ?? ''));
        if ($query === '' || ! $this->isSafeSelectQuery($query)) {
            return [];
        }

        $bindings = $this->buildSqlBindingsFromParams($query, $params);
        if ($bindings === null) {
            return [];
        }

        try {
            $connection = trim((string) ($item['dictionary_connection'] ?? ''));
            $db = $connection !== '' ? DB::connection($connection) : DB::connection();
            $rows = $db->select($query, $bindings);
        } catch (\Throwable) {
            return [];
        }

        $options = [];
        foreach ($rows as $row) {
            $data = (array) $row;
            if (! array_key_exists('value', $data) || ! array_key_exists('label', $data)) {
                continue;
            }

            $value = $data['value'];
            $label = $data['label'];
            if (! is_scalar($value) || ! is_scalar($label)) {
                continue;
            }

            $options[(string) $value] = (string) $label;
        }

        return $options;
    }

    private function isSafeSelectQuery(string $query): bool
    {
        $normalized = mb_strtolower(trim($query));

        if ($normalized === '' || ! str_starts_with($normalized, 'select') || str_contains($normalized, ';')) {
            return false;
        }

        foreach (['insert ', 'update ', 'delete ', 'drop ', 'alter ', 'truncate ', 'create ', 'grant ', 'revoke '] as $word) {
            if (str_contains($normalized, $word)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, scalar|null>|null
     */
    private function buildSqlBindingsFromParams(string $query, array $params): ?array
    {
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $query, $matches);
        $names = array_values(array_unique($matches[1] ?? []));
        $bindings = [];

        foreach ($names as $name) {
            if (! array_key_exists($name, $params) || $this->isBlank($params[$name])) {
                return null;
            }

            $value = $params[$name];
            if (! is_scalar($value) && $value !== null) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $bindings[$name] = $value;
        }

        return $bindings;
    }

    private function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }
}
