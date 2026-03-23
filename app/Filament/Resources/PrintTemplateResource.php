<?php

namespace App\Filament\Resources;

use App\Enums\PrintTemplateType;
use App\Filament\Resources\PrintTemplateResource\Pages;
use App\Models\PrintTemplate;
use App\Models\ReportGroup;
use App\Models\Setting;
use App\Services\Printing\TwigTemplateRenderService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Kahusoftware\FilamentCkeditorField\CKEditor;
use Wiebenieuwenhuis\FilamentCodeEditor\Components\CodeEditor;

class PrintTemplateResource extends Resource
{
    protected static ?string $model = PrintTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Настройки';

    protected static ?string $navigationLabel = 'Шаблоны печати';

    protected static ?string $modelLabel = 'Шаблон печати';

    protected static ?string $pluralModelLabel = 'Шаблоны печати';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(12)->schema([
                Tabs::make('editor_tabs')
                    ->tabs([
                        Tabs\Tab::make('Основное')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Section::make('Основное')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Название')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(4),

                                        TextInput::make('code')
                                            ->label('Код шаблона')
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(120)
                                            ->columnSpan(4),

                                        Select::make('type')
                                            ->label('Тип')
                                            ->options([
                                                PrintTemplateType::Receipt->value => 'Чек',
                                                PrintTemplateType::Report->value => 'Отчет',
                                            ])
                                            ->default(PrintTemplateType::Receipt->value)
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                                if ($state === PrintTemplateType::Report->value) {
                                                    $set('editor_mode', 'code');
                                                }
                                            })
                                            ->required()
                                            ->columnSpan(2),

                                        Select::make('report_group_id')
                                            ->label('Направление отчета')
                                            ->relationship(
                                                name: 'reportGroup',
                                                titleAttribute: 'name',
                                                modifyQueryUsing: fn ($query) => $query->orderBy('sort')->orderBy('name')
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->label('Название направления')
                                                    ->required()
                                                    ->maxLength(120),
                                                TextInput::make('slug')
                                                    ->label('Код (slug)')
                                                    ->maxLength(140)
                                                    ->helperText('Можно не заполнять, будет сгенерировано автоматически.'),
                                                TextInput::make('sort')
                                                    ->label('Сортировка')
                                                    ->numeric()
                                                    ->default(0),
                                                Toggle::make('is_active')
                                                    ->label('Активно')
                                                    ->default(true),
                                            ])
                                            ->createOptionUsing(function (array $data): int {
                                                $slug = trim((string) ($data['slug'] ?? ''));
                                                if ($slug === '') {
                                                    $slug = Str::slug((string) ($data['name'] ?? ''), '-');
                                                }

                                                $group = ReportGroup::create([
                                                    'name' => (string) ($data['name'] ?? ''),
                                                    'slug' => $slug !== '' ? $slug : null,
                                                    'sort' => (int) ($data['sort'] ?? 0),
                                                    'is_active' => (bool) ($data['is_active'] ?? true),
                                                ]);

                                                return (int) $group->getKey();
                                            })
                                            ->visible(fn (Get $get): bool => (string) ($get('type') ?? '') === PrintTemplateType::Report->value)
                                            ->columnSpan(3),

                                        Select::make('engine')
                                            ->label('Движок')
                                            ->options(['twig' => 'Twig'])
                                            ->default('twig')
                                            ->required()
                                            ->columnSpan(1),

                                        Select::make('output_format')
                                            ->label('Формат вывода')
                                            ->options(['pdf' => 'PDF'])
                                            ->default('pdf')
                                            ->required()
                                            ->columnSpan(1),

                                        Select::make('default_paper_preset')
                                            ->label('Тип бумаги по умолчанию')
                                            ->options([
                                                'a4' => 'A4 (210 x 297 мм)',
                                                'a5' => 'A5 (148 x 210 мм)',
                                                'thermal_80' => 'Термобумага 80 мм (рулон)',
                                                'thermal_58' => 'Термобумага 58 мм (рулон)',
                                                'custom' => 'Ручной ввод (мм)',
                                            ])
                                            ->default('a4')
                                            ->live()
                                            ->required()
                                            ->columnSpan(3),

                                        TextInput::make('default_paper_width_mm')
                                            ->label('Ширина (мм)')
                                            ->numeric()
                                            ->step(0.1)
                                            ->minValue(20)
                                            ->visible(fn (Get $get): bool => (string) ($get('default_paper_preset') ?? 'a4') === 'custom')
                                            ->required(fn (Get $get): bool => (string) ($get('default_paper_preset') ?? 'a4') === 'custom')
                                            ->columnSpan(2),

                                        TextInput::make('default_paper_height_mm')
                                            ->label('Высота (мм)')
                                            ->numeric()
                                            ->step(0.1)
                                            ->minValue(20)
                                            ->visible(fn (Get $get): bool => (string) ($get('default_paper_preset') ?? 'a4') === 'custom')
                                            ->required(fn (Get $get): bool => (string) ($get('default_paper_preset') ?? 'a4') === 'custom')
                                            ->columnSpan(2),

                                        TextInput::make('default_margin_top_mm')
                                            ->label('Отступ сверху (мм)')
                                            ->numeric()
                                            ->step(0.1)
                                            ->minValue(0)
                                            ->default(3)
                                            ->columnSpan(2),

                                        TextInput::make('default_margin_right_mm')
                                            ->label('Отступ справа (мм)')
                                            ->numeric()
                                            ->step(0.1)
                                            ->minValue(0)
                                            ->default(2)
                                            ->columnSpan(2),

                                        TextInput::make('default_margin_bottom_mm')
                                            ->label('Отступ снизу (мм)')
                                            ->numeric()
                                            ->step(0.1)
                                            ->minValue(0)
                                            ->default(3)
                                            ->columnSpan(2),

                                        TextInput::make('default_margin_left_mm')
                                            ->label('Отступ слева (мм)')
                                            ->numeric()
                                            ->step(0.1)
                                            ->minValue(0)
                                            ->default(2)
                                            ->columnSpan(2),

                                        Radio::make('editor_mode')
                                            ->label('Режим редактора')
                                            ->options(fn (Get $get): array => (string) ($get('type') ?? '') === PrintTemplateType::Report->value
                                                ? ['code' => 'Code (Twig + HTML)']
                                                : [
                                                    'code' => 'Code (Twig + HTML)',
                                                    'visual' => 'Visual (drag-and-drop)',
                                                ])
                                            ->default('visual')
                                            ->afterStateHydrated(function (Get $get, Set $set): void {
                                                if ((string) ($get('type') ?? '') === PrintTemplateType::Report->value) {
                                                    $set('editor_mode', 'code');

                                                    return;
                                                }

                                                if (! in_array((string) ($get('editor_mode') ?? ''), ['code', 'visual'], true)) {
                                                    $set('editor_mode', 'visual');
                                                }

                                                if ((string) ($get('editor_mode') ?? 'visual') === 'visual') {
                                                    $set('template_body_visual', (string) ($get('template_body') ?? ''));
                                                }
                                            })
                                            ->live()
                                            ->inline()
                                            ->helperText('Для отчетов доступен только Code-режим, чтобы не ломать Twig-логику.')
                                            ->required()
                                            ->columnSpan(6),

                                        Toggle::make('is_active')
                                            ->label('Активен')
                                            ->default(true)
                                            ->columnSpan(2),

                                        Textarea::make('description')
                                            ->label('Описание')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(12),
                            ]),

                        Tabs\Tab::make('Шаблон')
                            ->icon('heroicon-o-code-bracket')
                            ->schema([
                                Section::make('Шаблон Twig')
                                    ->schema([
                                        CodeEditor::make('template_body')
                                            ->key('template_body_code_editor')
                                            ->label('HTML шаблон (Twig)')
                                            ->default('')
                                            ->formatStateUsing(fn (mixed $state): string => is_string($state) ? $state : '')
                                            ->required()
                                            ->helperText('Code editor с подсветкой для Twig/HTML. Доступно: params.*, context.*, datasets.*, а также переменные из контекста напрямую.')
                                            ->visible(fn (Get $get): bool => (string) ($get('type') ?? '') === PrintTemplateType::Report->value
                                                || (string) ($get('editor_mode') ?? 'code') === 'code')
                                            ->columnSpanFull(),

                                        ...static::buildVisualEditorComponents(),

                                        Select::make('css_preset')
                                            ->label('CSS пресет')
                                            ->options([
                                                'none' => 'Без пресета',
                                                'report_table_default' => 'Таблица отчета (стандарт)',
                                                'report_table_dense' => 'Таблица отчета (компакт)',
                                                'receipt_compact' => 'Чек (компакт)',
                                            ])
                                            ->default('none')
                                            ->helperText('Стили применяются в Preview/PDF отдельно от HTML шаблона.')
                                            ->columnSpan(4),

                                        CodeEditor::make('custom_css')
                                            ->key('custom_css_code_editor')
                                            ->label('Дополнительный CSS')
                                            ->default('')
                                            ->formatStateUsing(fn (mixed $state): string => is_string($state) ? $state : '')
                                            ->helperText('Используйте классы в HTML шаблоне, а стили пишите здесь. TinyEditor не будет их удалять.')
                                            ->columnSpanFull(),

                                        Placeholder::make('twig_snippets')
                                            ->label('Сниппеты для отчетов')
                                            ->dehydrated(false)
                                            ->content(new HtmlString(
                                                '<div style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, &quot;Liberation Mono&quot;, &quot;Courier New&quot;, monospace; font-size: 12px; line-height: 1.35;">'
                                                .'<div><strong>Переменная:</strong> {{ params.date_from|default(&quot;-&quot;) }}</div>'
                                                .'<div><strong>Цикл:</strong> {% for row in datasets.items %}...{% endfor %}</div>'
                                                .'<div><strong>Таблица:</strong> используйте table + thead/tbody/tfoot для стабильного PDF.</div>'
                                                .'</div>'
                                            ))
                                            ->columnSpanFull(),

                                        Placeholder::make('editor_mode_badge')
                                            ->label('Текущий режим')
                                            ->content(fn (Get $get): string => (string) ($get('editor_mode') ?? 'code') === 'visual' ? 'Visual' : 'Code')
                                            ->dehydrated(false)
                                            ->columnSpanFull(),

                                        Actions::make([
                                            Action::make('switch_to_code_mode')
                                                ->label('Code')
                                                ->color('gray')
                                                ->action(function (Get $get, Set $set): void {
                                                    $visualHtml = (string) ($get('template_body_visual') ?? '');
                                                    if ($visualHtml !== '') {
                                                        $set('template_body', static::formatVisualHtmlForCode($visualHtml));
                                                    }

                                                    $set('editor_mode', 'code');
                                                }),
                                            Action::make('switch_to_visual_mode')
                                                ->label('Visual')
                                                ->color('info')
                                                ->visible(fn (Get $get): bool => (string) ($get('type') ?? '') !== PrintTemplateType::Report->value)
                                                ->action(function (Get $get, Set $set): void {
                                                    $set('template_body_visual', (string) ($get('template_body') ?? ''));
                                                    $set('editor_mode', 'visual');
                                                }),
                                        ])
                                            ->label('Быстрый переключатель')
                                            ->columnSpanFull(),

                                        Actions::make([
                                            Action::make('insert_var_snippet')
                                                ->label('Вставить переменную')
                                                ->color('gray')
                                                ->action(function (Get $get, Set $set): void {
                                                    static::insertSnippet($get, $set, "\n{{ params.date_from|default('-') }}\n");
                                                }),
                                            Action::make('insert_loop_snippet')
                                                ->label('Вставить цикл')
                                                ->color('gray')
                                                ->action(function (Get $get, Set $set): void {
                                                    static::insertSnippet($get, $set, "\n{% for row in datasets.items %}\n<div>{{ row.name|default('-') }}</div>\n{% endfor %}\n");
                                                }),
                                            Action::make('insert_table_snippet')
                                                ->label('Вставить таблицу')
                                                ->color('gray')
                                                ->action(function (Get $get, Set $set): void {
                                                    static::insertSnippet(
                                                        $get,
                                                        $set,
                                                        "\n<table style=\"width:100%;border-collapse:collapse;font-size:10pt;\">\n"
                                                        ."<thead><tr><th style=\"border:1px solid #d1d5db;padding:4px;\">#</th><th style=\"border:1px solid #d1d5db;padding:4px;\">Назва</th><th style=\"border:1px solid #d1d5db;padding:4px;\">Сума</th></tr></thead>\n"
                                                        ."<tbody>\n{% for row in datasets.items %}\n<tr><td style=\"border:1px solid #d1d5db;padding:4px;\">{{ loop.index }}</td><td style=\"border:1px solid #d1d5db;padding:4px;\">{{ row.name|default('-') }}</td><td style=\"border:1px solid #d1d5db;padding:4px;text-align:right;\">{{ row.total|default(0)|number_format(2, '.', ' ') }}</td></tr>\n{% endfor %}\n</tbody>\n"
                                                        ."</table>\n"
                                                    );
                                                }),
                                            Action::make('validate_pdf_html')
                                                ->label('Validate for PDF')
                                                ->color('warning')
                                                ->action(function (Get $get, Set $set): void {
                                                    $issues = static::validatePdfCompatibility((string) ($get('template_body') ?? ''), $set);

                                                    if ($issues === []) {
                                                        Notification::make()
                                                            ->title('PDF validation: OK')
                                                            ->body('Критичных проблем не найдено.')
                                                            ->success()
                                                            ->send();

                                                        return;
                                                    }

                                                    Notification::make()
                                                        ->title('PDF validation: есть замечания')
                                                        ->body(implode(' | ', $issues))
                                                        ->warning()
                                                        ->send();
                                                }),
                                        ])
                                            ->columnSpanFull(),

                                        Placeholder::make('template_vars_catalog')
                                            ->label('Каталог переменных')
                                            ->dehydrated(false)
                                            ->content(fn (Get $get): HtmlString => static::buildVariablesHtml($get))
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(12),
                            ]),

                        Tabs\Tab::make('Предпросмотр')
                            ->icon('heroicon-o-eye')
                            ->schema([
                                Section::make('Предпросмотр Twig')
                                    ->schema([
                                        Hidden::make('preview_html')
                                            ->dehydrated(false)
                                            ->default(''),

                                        Hidden::make('preview_pdf_src')
                                            ->dehydrated(false)
                                            ->default(''),

                                        Section::make('Параметры preview (форма)')
                                            ->schema(fn (Get $get): array => static::buildPreviewParamFields($get))
                                            ->visible(fn (Get $get): bool => static::hasPreviewParamsSchema($get))
                                            ->columns(12)
                                            ->columnSpanFull(),

                                        Textarea::make('preview_params_json')
                                            ->label('Параметры preview (JSON)')
                                            ->rows(6)
                                            ->default('{\n  "order_id": 1\n}')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                                static::syncPreviewParamsFromJsonToForm($get, $set);
                                                if ((bool) ($get('preview_auto_refresh') ?? false)) {
                                                    static::renderPreview($get, $set);
                                                }
                                            })
                                            ->helperText('Можно редактировать вручную или использовать форму выше.')
                                            ->dehydrated(false)
                                            ->columnSpan(6),

                                        Textarea::make('preview_context_json')
                                            ->label('Контекст preview (JSON)')
                                            ->rows(6)
                                            ->default('{\n  "order_number": "T-001",\n  "operator": "Админ",\n  "printed_at": "'.now()->format('d.m.Y H:i').'"\n}')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                                if ((bool) ($get('preview_auto_refresh') ?? false)) {
                                                    static::renderPreview($get, $set);
                                                }
                                            })
                                            ->dehydrated(false)
                                            ->columnSpan(6),

                                        Toggle::make('preview_auto_refresh')
                                            ->label('Обновлять превью при изменении шаблона')
                                            ->default(false)
                                            ->dehydrated(false)
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set, bool $state): void {
                                                if ($state) {
                                                    static::renderPreview($get, $set);
                                                }
                                            })
                                            ->columnSpan(6),

                                        Toggle::make('preview_reset_on_error')
                                            ->label('Очищать превью при ошибке')
                                            ->default(false)
                                            ->dehydrated(false)
                                            ->columnSpan(6),

                                        Placeholder::make('preview_actions_hint')
                                            ->label('Как обновить')
                                            ->content('Нажмите кнопку "Обновить превью" или включите автообновление.')
                                            ->dehydrated(false)
                                            ->columnSpanFull(),

                                        Actions::make([
                                            Action::make('refresh_preview')
                                                ->label('Обновить превью')
                                                ->icon('heroicon-o-eye')
                                                ->action(function (Get $get, Set $set): void {
                                                    static::renderPreview($get, $set);
                                                }),
                                        ])
                                            ->columnSpanFull(),

                                        Placeholder::make('preview_rendered')
                                            ->label('Preview HTML')
                                            ->content(fn (Get $get): HtmlString => new HtmlString((string) ($get('preview_html') ?? '')))
                                            ->dehydrated(false)
                                            ->columnSpanFull(),

                                        Placeholder::make('preview_pdf')
                                            ->label('Preview PDF')
                                            ->content(function (Get $get): HtmlString {
                                                $src = (string) ($get('preview_pdf_src') ?? '');
                                                if ($src === '') {
                                                    return new HtmlString('<div style="font-size:12px;color:#64748b;">PDF превью появится после рендера шаблона.</div>');
                                                }

                                                return new HtmlString('<iframe title="PDF preview" src="'.e($src).'" style="width:100%;height:560px;border:1px solid #d1d5db;border-radius:8px;background:#fff;"></iframe>');
                                            })
                                            ->dehydrated(false)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(12),
                            ]),

                        Tabs\Tab::make('Параметры ввода')
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->schema([
                                Section::make('Параметры ввода')
                                    ->schema([
                                        Repeater::make('parameters_schema')
                                            ->label('')
                                            ->schema([
                                                TextInput::make('key')
                                                    ->label('Ключ')
                                                    ->required(),
                                                TextInput::make('label')
                                                    ->label('Название')
                                                    ->required(),
                                                Select::make('type')
                                                    ->label('Тип поля')
                                                    ->options([
                                                        'date' => 'Дата',
                                                        'date_range' => 'Период',
                                                        'select' => 'Список',
                                                        'dictionary' => 'Справочник (SQL)',
                                                        'text' => 'Текст',
                                                        'number' => 'Число',
                                                        'boolean' => 'Да/Нет',
                                                    ])
                                                    ->default('text')
                                                    ->required()
                                                    ->live(),
                                                TextInput::make('default')
                                                    ->label('По умолчанию'),
                                                Textarea::make('options_json')
                                                    ->label('Опции списка (JSON)')
                                                    ->rows(4)
                                                    ->placeholder('{"all":"Все","new":"Новые"}')
                                                    ->helperText('Используется для типа "Список". Формат: {"value":"label"}.')
                                                    ->visible(fn (Get $get): bool => (string) ($get('type') ?? '') === 'select')
                                                    ->columnSpanFull(),
                                                Textarea::make('dictionary_query')
                                                    ->label('SQL справочника (SELECT value, label)')
                                                    ->rows(4)
                                                    ->placeholder('SELECT id AS value, name AS label FROM bs_users WHERE is_active = 1 ORDER BY name')
                                                    ->helperText('Только SELECT без ";". Можно использовать биндинги (:company_id, :region_id).')
                                                    ->visible(fn (Get $get): bool => (string) ($get('type') ?? '') === 'dictionary')
                                                    ->columnSpanFull(),
                                                TextInput::make('dictionary_connection')
                                                    ->label('Подключение БД (необязательно)')
                                                    ->visible(fn (Get $get): bool => (string) ($get('type') ?? '') === 'dictionary')
                                                    ->columnSpan(2),
                                                Toggle::make('dictionary_searchable')
                                                    ->label('Поиск в списке')
                                                    ->default(true)
                                                    ->visible(fn (Get $get): bool => (string) ($get('type') ?? '') === 'dictionary')
                                                    ->columnSpan(1),
                                                Toggle::make('required')
                                                    ->label('Обязательный')
                                                    ->default(false),
                                            ])
                                            ->columns(5)
                                            ->collapsible()
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(12),
                            ]),

                        Tabs\Tab::make('Источники данных')
                            ->icon('heroicon-o-circle-stack')
                            ->schema([
                                Section::make(fn (Get $get): string => $get('type') === PrintTemplateType::Receipt->value
                                    ? 'Источники данных для чеков'
                                    : 'Источники данных для отчетов')
                                    ->schema([
                                        Repeater::make('data_sources')
                                            ->label('')
                                            ->helperText('Для чеков обычно используйте :order_id. Для отчетов — параметры из формы (:date_from, :date_to и т.д.).')
                                            ->schema([
                                                TextInput::make('key')
                                                    ->label('Ключ набора')
                                                    ->required(),
                                                Select::make('type')
                                                    ->label('Тип')
                                                    ->options([
                                                        'sql' => 'SQL',
                                                        'provider' => 'PHP provider',
                                                    ])
                                                    ->default('sql')
                                                    ->required()
                                                    ->live(),
                                                Textarea::make('query')
                                                    ->label('SQL запрос (SELECT only)')
                                                    ->rows(4)
                                                    ->visible(fn ($get): bool => $get('type') === 'sql')
                                                    ->columnSpanFull(),
                                                TextInput::make('connection')
                                                    ->label('Подключение БД (необязательно)')
                                                    ->visible(fn ($get): bool => $get('type') === 'sql'),
                                                TextInput::make('provider_class')
                                                    ->label('Класс провайдера')
                                                    ->placeholder('App\\Reports\\DataProviders\\SalesReportProvider')
                                                    ->visible(fn ($get): bool => $get('type') === 'provider')
                                                    ->columnSpanFull(),
                                                Toggle::make('enabled')
                                                    ->label('Включен')
                                                    ->default(true),
                                            ])
                                            ->columns(2)
                                            ->collapsible()
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(12),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]),
        ]);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    private static function buildVisualEditorComponents(): array
    {
        return [
            CKEditor::make('template_body_visual')
                ->key('template_body_ckeditor')
                ->label('HTML шаблон (Twig)')
                ->placeholder('Type or paste your content here...')
                ->uploadUrl(route('admin.print-templates.ckeditor-upload'))
                ->afterStateHydrated(function (Get $get, Set $set, mixed $state): void {
                    if (is_string($state) && trim($state) !== '') {
                        return;
                    }

                    $set('template_body_visual', (string) ($get('template_body') ?? ''));
                })
                ->live(onBlur: true)
                ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                    $set('template_body', is_string($state) ? static::formatVisualHtmlForCode($state) : '');

                    if ((bool) ($get('preview_auto_refresh') ?? false)) {
                        static::renderPreview($get, $set);
                    }
                })
                ->helperText('Visual-режим (CKEditor). Для сложной Twig-логики используйте Code.')
                ->visible(fn (Get $get): bool => (string) ($get('type') ?? '') !== PrintTemplateType::Report->value
                    && (string) ($get('editor_mode') ?? 'code') === 'visual')
                ->dehydrated()
                ->columnSpanFull(),
        ];
    }

    public static function formatVisualHtmlForCode(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $html = static::normalizeEditorHtmlForPrint($html);

        $formatted = preg_replace('/>\s+</', ">\n<", $html) ?? $html;
        $formatted = preg_replace('/\s*({%[^%]*%})\s*/', "\n$1\n", $formatted) ?? $formatted;
        $formatted = preg_replace('/\s*({{[^}]*}})\s*/', "\n$1\n", $formatted) ?? $formatted;
        $formatted = preg_replace('/\n{3,}/', "\n\n", $formatted) ?? $formatted;

        return trim($formatted);
    }

    private static function normalizeEditorHtmlForPrint(string $html): string
    {
        $normalized = $html;

        $normalized = preg_replace('/\s+storage=""/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+height="auto"/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+width="\d+(?:\.\d+)?px"/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+height="\d+(?:\.\d+)?px"/i', '', $normalized) ?? $normalized;

        $normalized = preg_replace_callback('/<img\b[^>]*>/i', static function (array $matches): string {
            $imgTag = (string) ($matches[0] ?? '');

            $imgTag = preg_replace_callback('/\sstyle="([^"]*)"/i', static function (array $styleMatches): string {
                $style = (string) ($styleMatches[1] ?? '');
                $parts = array_filter(array_map('trim', explode(';', $style)), static fn (string $part): bool => $part !== '');
                $filtered = [];

                foreach ($parts as $part) {
                    if (preg_match('/^aspect-ratio\s*:/i', $part) === 1) {
                        continue;
                    }

                    $filtered[] = $part;
                }

                if ($filtered === []) {
                    return '';
                }

                return ' style="'.implode('; ', $filtered).';"';
            }, $imgTag) ?? $imgTag;

            return $imgTag;
        }, $normalized) ?? $normalized;

        return $normalized;
    }


    private static function parsePreviewJson(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function insertSnippet(Get $get, Set $set, string $snippet): void
    {
        $current = (string) ($get('template_body') ?? '');
        $updated = rtrim($current)."\n".trim($snippet)."\n";
        $set('template_body', $updated);

        if ((bool) ($get('preview_auto_refresh') ?? false)) {
            static::renderPreview($get, $set);
        }
    }

    private static function hasPreviewParamsSchema(Get $get): bool
    {
        foreach ((array) ($get('parameters_schema') ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (trim((string) ($item['key'] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    private static function buildPreviewParamFields(Get $get): array
    {
        $components = [];
        $declaredKeys = [];
        $currentParams = static::collectPreviewParams($get);

        foreach ((array) ($get('parameters_schema') ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = trim((string) ($item['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            $type = mb_strtolower(trim((string) ($item['type'] ?? 'text')));
            $defaultValue = $item['default'] ?? null;
            $statePath = 'preview_params_form.'.$key;
            $declaredKeys[$key] = true;

            $onUpdate = function (Get $get, Set $set): void {
                static::syncPreviewParamsJsonFromForm($get, $set);

                if ((bool) ($get('preview_auto_refresh') ?? false)) {
                    static::renderPreview($get, $set);
                }
            };

            if ($type === 'boolean') {
                $components[] = Toggle::make($statePath)
                    ->label($label !== '' ? $label : $key)
                    ->default((bool) $defaultValue)
                    ->live()
                    ->afterStateUpdated($onUpdate)
                    ->columnSpan(3);

                continue;
            }

            if ($type === 'date') {
                $components[] = DatePicker::make($statePath)
                    ->label($label !== '' ? $label : $key)
                    ->default(is_scalar($defaultValue) ? (string) $defaultValue : null)
                    ->native(false)
                    ->live(onBlur: true)
                    ->afterStateUpdated($onUpdate)
                    ->columnSpan(3);

                continue;
            }

            if ($type === 'select') {
                $components[] = Select::make($statePath)
                    ->label($label !== '' ? $label : $key)
                    ->options(static::resolveSchemaSelectOptions($item))
                    ->default(is_scalar($defaultValue) ? (string) $defaultValue : null)
                    ->live()
                    ->afterStateUpdated($onUpdate)
                    ->searchable()
                    ->columnSpan(3);

                continue;
            }

            if ($type === 'dictionary') {
                $components[] = Select::make($statePath)
                    ->label($label !== '' ? $label : $key)
                    ->options(static::resolveDictionaryOptionsForPreview($item, $currentParams))
                    ->default(is_scalar($defaultValue) ? (string) $defaultValue : null)
                    ->live()
                    ->afterStateUpdated($onUpdate)
                    ->searchable((bool) ($item['dictionary_searchable'] ?? true))
                    ->columnSpan(3);

                continue;
            }

            $field = TextInput::make($statePath)
                ->label($label !== '' ? $label : $key)
                ->default(is_scalar($defaultValue) ? (string) $defaultValue : null)
                ->live(onBlur: true)
                ->afterStateUpdated($onUpdate)
                ->columnSpan(3);

            if ($type === 'number') {
                $field->numeric();
            }

            $components[] = $field;
        }

        foreach (static::extractDataSourceBindingKeys($get) as $bindingKey) {
            if (isset($declaredKeys[$bindingKey])) {
                continue;
            }

            $components[] = TextInput::make('preview_params_form.'.$bindingKey)
                ->label('SQL параметр: '.$bindingKey)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Get $get, Set $set): void {
                    static::syncPreviewParamsJsonFromForm($get, $set);

                    if ((bool) ($get('preview_auto_refresh') ?? false)) {
                        static::renderPreview($get, $set);
                    }
                })
                ->columnSpan(3);
        }

        return $components;
    }

    /**
     * @return array<int, string>
     */
    private static function extractDataSourceBindingKeys(Get $get): array
    {
        $keys = [];

        foreach ((array) ($get('data_sources') ?? []) as $source) {
            if (! is_array($source)) {
                continue;
            }

            $enabled = (bool) ($source['enabled'] ?? true);
            if (! $enabled) {
                continue;
            }

            $type = mb_strtolower(trim((string) ($source['type'] ?? 'sql')));
            if ($type !== 'sql') {
                continue;
            }

            $query = (string) ($source['query'] ?? '');
            preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $query, $matches);

            foreach ((array) ($matches[1] ?? []) as $name) {
                $name = trim((string) $name);
                if ($name !== '') {
                    $keys[] = $name;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, string>
     */
    private static function resolveSchemaSelectOptions(array $item): array
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
    private static function resolveDictionaryOptionsForPreview(array $item, array $params): array
    {
        $query = trim((string) ($item['dictionary_query'] ?? ''));
        if ($query === '' || ! static::isSafeSelectQuery($query)) {
            return [];
        }

        $bindings = static::buildSqlBindingsFromParams($query, $params);
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

    private static function isSafeSelectQuery(string $query): bool
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
    private static function buildSqlBindingsFromParams(string $query, array $params): ?array
    {
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $query, $matches);
        $names = array_values(array_unique($matches[1] ?? []));
        $bindings = [];

        foreach ($names as $name) {
            if (! array_key_exists($name, $params) || static::isBlankValue($params[$name])) {
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

    private static function collectPreviewParams(Get $get): array
    {
        $paramsFromJson = static::parsePreviewJson($get('preview_params_json'));
        $paramsFromForm = is_array($get('preview_params_form')) ? $get('preview_params_form') : [];

        foreach ($paramsFromForm as $key => $value) {
            if (! is_string($key) || trim($key) === '' || static::isBlankValue($value)) {
                continue;
            }

            $paramsFromJson[$key] = $value;
        }

        return $paramsFromJson;
    }

    private static function syncPreviewParamsJsonFromForm(Get $get, Set $set): void
    {
        $params = static::collectPreviewParams($get);
        $set('preview_params_json', json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private static function syncPreviewParamsFromJsonToForm(Get $get, Set $set): void
    {
        $params = static::parsePreviewJson($get('preview_params_json'));
        if ($params === []) {
            return;
        }

        $set('preview_params_form', $params);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<int, string>
     */
    private static function validateRequiredPreviewParams(Get $get, array $params): array
    {
        $missing = [];

        foreach ((array) ($get('parameters_schema') ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = trim((string) ($item['key'] ?? ''));
            $required = (bool) ($item['required'] ?? false);

            if ($key === '' || ! $required) {
                continue;
            }

            if (! array_key_exists($key, $params) || static::isBlankValue($params[$key])) {
                $missing[] = $key;
            }
        }

        foreach (static::extractDataSourceBindingKeys($get) as $bindingKey) {
            if (! array_key_exists($bindingKey, $params) || static::isBlankValue($params[$bindingKey])) {
                $missing[] = $bindingKey;
            }
        }

        return array_values(array_unique($missing));
    }

    private static function isBlankValue(mixed $value): bool
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

    private static function buildVariablesHtml(Get $get): HtmlString
    {
        $parameters = [];
        $datasets = [];

        foreach ((array) ($get('parameters_schema') ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = trim((string) ($item['key'] ?? ''));
            if ($key !== '') {
                $parameters[] = $key;
            }
        }

        foreach ((array) ($get('data_sources') ?? []) as $source) {
            if (! is_array($source)) {
                continue;
            }

            $key = trim((string) ($source['key'] ?? ''));
            if ($key !== '') {
                $datasets[] = $key;
            }
        }

        $parts = [];
        $parts[] = '<div style="font-size:12px;line-height:1.5;">';
        $parts[] = '<div><strong>Базовые:</strong> params.*, context.*, datasets.*, now</div>';

        if ($parameters !== []) {
            $parts[] = '<div><strong>params:</strong> '.e(implode(', ', array_map(fn (string $key): string => 'params.'.$key, $parameters))).'</div>';
        }

        if ($datasets !== []) {
            $parts[] = '<div><strong>datasets:</strong> '.e(implode(', ', array_map(fn (string $key): string => 'datasets.'.$key, $datasets))).'</div>';
        }

        $parts[] = '</div>';

        return new HtmlString(implode('', $parts));
    }

    private static function validatePdfCompatibility(string $html, Set $set): array
    {
        $issues = [];
        $checks = [
            '/<script\b/i' => 'Dompdf игнорирует JavaScript, удалите <script>.',
            '/display\s*:\s*grid/i' => 'CSS Grid может рендериться нестабильно в PDF.',
            '/position\s*:\s*fixed/i' => 'position: fixed часто ломает чековую верстку в PDF.',
            '/@font-face/i' => '@font-face может не сработать без системного шрифта.',
        ];

        foreach ($checks as $pattern => $message) {
            if (preg_match($pattern, $html) === 1) {
                $issues[] = $message;
            }
        }

        if ($issues === []) {
            $set('preview_html', '<div style="padding:10px;border:1px solid #86efac;background:#f0fdf4;color:#166534;border-radius:8px;">PDF validation: критичных проблем не найдено.</div>');

            return [];
        }

        $set(
            'preview_html',
            '<div style="padding:10px;border:1px solid #fcd34d;background:#fffbeb;color:#92400e;border-radius:8px;"><strong>PDF validation:</strong><br>'
            .e(implode(' | ', $issues))
            .'</div>'
        );

        return $issues;
    }

    private static function renderPreview(Get $get, Set $set): void
    {
        try {
            $templateBody = (string) ($get('template_body') ?? '');
            $params = static::collectPreviewParams($get);
            $context = static::parsePreviewJson($get('preview_context_json'));
            $dataSources = $get('data_sources');

            $set('preview_params_json', json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $missingRequired = static::validateRequiredPreviewParams($get, $params);
            if ($missingRequired !== []) {
                $set(
                    'preview_html',
                    '<div style="padding:10px;border:1px solid #fcd34d;background:#fffbeb;color:#92400e;border-radius:8px;">'
                    .'<strong>Не заполнены обязательные параметры:</strong> '
                    .e(implode(', ', $missingRequired))
                    .'</div>'
                );
                $set('preview_pdf_src', '');

                return;
            }

            $template = new PrintTemplate;
            $template->template_body = $templateBody;
            $template->data_sources = is_array($dataSources) ? $dataSources : [];

            $rendered = app(TwigTemplateRenderService::class)
                ->renderTemplate($template, $params, $context);

            $html = (string) ($rendered['html'] ?? '');
            $css = static::resolveTemplateCss(
                (string) ($get('type') ?? ''),
                (string) ($get('css_preset') ?? 'none'),
                (string) ($get('custom_css') ?? ''),
            );
            $styledHtml = static::injectCssIntoBodyHtml($html, $css);

            $set('preview_html', $styledHtml);
            [$paperWidthMm, $paperHeightMm] = static::resolvePreviewPaperSize($get);
            [$marginTopMm, $marginRightMm, $marginBottomMm, $marginLeftMm] = static::resolvePreviewMargins($get);
            $set('preview_pdf_src', static::buildPdfPreviewDataUri(
                $html,
                $css,
                $paperWidthMm,
                $paperHeightMm,
                $marginTopMm,
                $marginRightMm,
                $marginBottomMm,
                $marginLeftMm,
            ));
        } catch (\Throwable $exception) {
            if ((bool) ($get('preview_reset_on_error') ?? false)) {
                $set('preview_html', '');
                $set('preview_pdf_src', '');

                return;
            }

            $set(
                'preview_html',
                '<div style="padding:10px;border:1px solid #fca5a5;background:#fef2f2;color:#991b1b;border-radius:8px;">'
                .'<strong>Preview error:</strong> '
                .e($exception->getMessage())
                .'</div>'
            );
            $set('preview_pdf_src', '');
        }
    }

    /**
     * @return array{0: float, 1: float}
     */
    private static function resolvePreviewPaperSize(Get $get): array
    {
        $preset = (string) ($get('default_paper_preset') ?? 'a4');
        $customWidth = (float) ($get('default_paper_width_mm') ?? 0);
        $customHeight = (float) ($get('default_paper_height_mm') ?? 0);

        return static::paperPresetToSize($preset, $customWidth, $customHeight);
    }

    /**
     * @return array{0: float, 1: float}
     */
    private static function paperPresetToSize(string $preset, float $customWidth = 0, float $customHeight = 0): array
    {
        return match ($preset) {
            'a5' => [148.0, 210.0],
            'thermal_80' => [80.0, 3650.0],
            'thermal_58' => [58.0, 3650.0],
            'custom' => [
                $customWidth > 20 ? $customWidth : 210.0,
                $customHeight > 20 ? $customHeight : 297.0,
            ],
            default => [210.0, 297.0],
        };
    }

    private static function buildPdfPreviewDataUri(
        string $bodyHtml,
        string $templateCss,
        float $paperWidthMm,
        float $paperHeightMm,
        float $marginTopMm,
        float $marginRightMm,
        float $marginBottomMm,
        float $marginLeftMm,
    ): string {
        $bodyHtml = static::normalizeHtmlForPdf($bodyHtml);

        $html = '<!doctype html><html><head><meta charset="UTF-8">'
            .($templateCss !== '' ? '<style>'.$templateCss.'</style>' : '')
            .'<style>@page{margin:0;}body{margin:0;padding:0;} </style>'
            .'</head><body>'
            .'<div style="padding:'.$marginTopMm.'mm '.$marginRightMm.'mm '.$marginBottomMm.'mm '.$marginLeftMm.'mm;'
            .'font-family:\'DejaVu Sans\',sans-serif;">'
            .$bodyHtml
            .'</div></body></html>';

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', base_path());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $widthPt = $paperWidthMm * 2.834645669;
        $heightPt = $paperHeightMm * 2.834645669;
        $dompdf->setPaper([0, 0, $widthPt, $heightPt]);
        $dompdf->render();

        $pdfBinary = $dompdf->output();

        return 'data:application/pdf;base64,'.base64_encode($pdfBinary);
    }

    private static function injectCssIntoBodyHtml(string $bodyHtml, string $css): string
    {
        if (trim($css) === '') {
            return $bodyHtml;
        }

        return '<style>'.$css.'</style>'.$bodyHtml;
    }

    private static function resolveTemplateCss(string $type, string $preset, string $customCss): string
    {
        $globalReportCss = trim((string) Setting::admin('printservice.report_css_global', ''));
        $presetCss = static::presetCss($preset);
        $customCss = trim($customCss);

        $blocks = [];
        if ($type === PrintTemplateType::Report->value && $globalReportCss !== '') {
            $blocks[] = $globalReportCss;
        }

        if ($presetCss !== '') {
            $blocks[] = $presetCss;
        }

        if ($customCss !== '') {
            $blocks[] = $customCss;
        }

        return implode("\n\n", $blocks);
    }

    private static function presetCss(string $preset): string
    {
        return match ($preset) {
            'report_table_default' => 'table.report{width:100%;border-collapse:collapse;font-family:"DejaVu Sans",sans-serif;font-size:12px;line-height:1.35;color:#0f172a;} .report caption{caption-side:top;text-align:left;font-weight:700;font-size:13px;color:#1e3a8a;margin:0 0 8px;} .report th,.report td{border:1px solid #dbe5f3;padding:7px 10px;vertical-align:top;} .report thead th{background:#eaf2ff;color:#1e3a8a;font-weight:700;text-align:left;text-transform:uppercase;letter-spacing:.02em;} .report tbody tr:nth-child(even) td{background:#f8fbff;} .report tbody tr td:first-child{font-weight:600;color:#334155;} .report tfoot td{background:#eef2ff;font-weight:700;color:#1e3a8a;border-top:2px solid #93c5fd;} .num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;} .total-row td{font-weight:700;background:#eef2ff;border-top:2px solid #93c5fd;color:#1e3a8a;}',
            'report_table_dense' => 'table.report{width:100%;border-collapse:collapse;font-family:"DejaVu Sans",sans-serif;font-size:10.5px;line-height:1.25;color:#111827;} .report caption{caption-side:top;text-align:left;font-weight:700;font-size:11.5px;color:#1d4ed8;margin:0 0 6px;} .report th,.report td{border:1px solid #d7dfec;padding:4px 6px;vertical-align:top;} .report thead th{background:#eff6ff;color:#1e40af;font-weight:700;text-align:left;} .report tbody tr:nth-child(even) td{background:#f9fbff;} .report tbody tr td:first-child{font-weight:600;color:#374151;} .report tfoot td{background:#eef4ff;font-weight:700;color:#1e3a8a;border-top:2px solid #9db7ea;} .num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;} .total-row td{font-weight:700;background:#eef4ff;border-top:2px solid #9db7ea;color:#1e3a8a;}',
            'receipt_compact' => 'body{font-size:10pt;line-height:1.2;} table{width:100%;border-collapse:collapse;} td,th{padding:2px 0;} .center{text-align:center;} .right{text-align:right;}',
            default => '',
        };
    }

    private static function normalizeHtmlForPdf(string $html): string
    {
        return preg_replace_callback(
            '/<img\b[^>]*\bsrc=("|\')(.*?)\1[^>]*>/i',
            function (array $matches): string {
                $tag = $matches[0];
                $src = $matches[2];
                $resolved = static::resolvePdfImageSrc($src);

                return str_replace($src, $resolved, $tag);
            },
            $html
        ) ?? $html;
    }

    private static function resolvePdfImageSrc(string $src): string
    {
        $src = trim(html_entity_decode($src, ENT_QUOTES | ENT_HTML5));
        if ($src === '' || str_starts_with($src, 'data:') || str_starts_with($src, 'file://')) {
            return $src;
        }

        if (preg_match('~^https?://~i', $src) === 1) {
            $srcPath = (string) (parse_url($src, PHP_URL_PATH) ?? '');
            if ($srcPath !== '') {
                $candidate = static::resolvePublicOrStoragePath($srcPath);
                if ($candidate !== null) {
                    return static::toDataUriFromFile($candidate) ?? static::toFileUri($candidate);
                }
            }

            $appUrl = (string) config('app.url', '');
            $srcHost = parse_url($src, PHP_URL_HOST);
            $appHost = parse_url($appUrl, PHP_URL_HOST);

            if ($srcHost !== null && $appHost !== null && strcasecmp($srcHost, $appHost) === 0 && $srcPath !== '') {
                $candidate = static::resolvePublicOrStoragePath($srcPath);
                if ($candidate !== null) {
                    return static::toDataUriFromFile($candidate) ?? static::toFileUri($candidate);
                }
            }

            return $src;
        }

        if (str_starts_with($src, '/')) {
            $candidate = static::resolvePublicOrStoragePath($src);

            return $candidate !== null ? (static::toDataUriFromFile($candidate) ?? static::toFileUri($candidate)) : $src;
        }

        if (preg_match('~^[a-z][a-z0-9+\-.]*:~i', $src) === 1) {
            return $src;
        }

        $candidate = static::resolvePublicOrStoragePath('/'.ltrim($src, '/'));

        return $candidate !== null ? (static::toDataUriFromFile($candidate) ?? static::toFileUri($candidate)) : $src;
    }

    private static function toDataUriFromFile(string $absolutePath): ?string
    {
        try {
            if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
                return null;
            }

            $binary = file_get_contents($absolutePath);
            if (! is_string($binary) || $binary === '') {
                return null;
            }

            $extension = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));
            $mime = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                default => 'image/png',
            };

            return 'data:'.$mime.';base64,'.base64_encode($binary);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function resolvePublicOrStoragePath(string $publicPath): ?string
    {
        $relative = ltrim($publicPath, '/');
        $publicCandidate = public_path($relative);
        if (is_file($publicCandidate)) {
            return $publicCandidate;
        }

        if (str_starts_with($relative, 'storage/')) {
            $storageRelative = substr($relative, strlen('storage/'));
            $storageCandidate = storage_path('app/public/'.$storageRelative);
            if (is_file($storageCandidate)) {
                return $storageCandidate;
            }
        }

        return null;
    }

    private static function toFileUri(string $absolutePath): string
    {
        $normalized = str_replace('\\', '/', $absolutePath);

        if (preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            return 'file:///'.$normalized;
        }

        return 'file://'.$normalized;
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private static function resolvePreviewMargins(Get $get): array
    {
        $top = (float) ($get('default_margin_top_mm') ?? 3);
        $right = (float) ($get('default_margin_right_mm') ?? 2);
        $bottom = (float) ($get('default_margin_bottom_mm') ?? 3);
        $left = (float) ($get('default_margin_left_mm') ?? 2);

        return [
            $top >= 0 ? $top : 3.0,
            $right >= 0 ? $right : 2.0,
            $bottom >= 0 ? $bottom : 3.0,
            $left >= 0 ? $left : 2.0,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Код')
                    ->searchable(),
                TextColumn::make('reportGroup.name')
                    ->label('Направление')
                    ->placeholder('Без группы')
                    ->toggleable(),
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge(),
                TextColumn::make('engine')
                    ->label('Движок'),
                TextColumn::make('output_format')
                    ->label('Вывод')
                    ->badge(),
                TextColumn::make('is_active')
                    ->label('Статус')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Активен' : 'Отключен')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('updated_at')
                    ->label('Обновлен')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrintTemplates::route('/'),
            'create' => Pages\CreatePrintTemplate::route('/create'),
            'edit' => Pages\EditPrintTemplate::route('/{record}/edit'),
        ];
    }
}
