<?php
namespace App\Filament\Clusters\Reference\Resources;

use App\Filament\Clusters\Reference;
use App\Filament\Clusters\Reference\Resources\LocationResource\Pages;
use App\Filament\Clusters\Reference\Resources\LocationResource\RelationManagers;
use App\Models\Language;
use App\Models\Location;
use App\Models\Setting;
use App\Models\SvgImage;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Pages\SubNavigationPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use SolutionForest\FilamentTranslateField\Forms\Component\Translate;


class LocationResource extends Resource
{
    use Translatable;
    protected static ?string $model = Location::class;
    protected static ?string $cluster = Reference::class;
 //   protected static ?string $navigationGroup = 'Справочники';
    protected static ?string $navigationLabel = null;
    protected static ?string $modelLabel = null;
    protected static ?string $pluralModelLabel = null;
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?int $navigationSort = 10;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getNavigationLabel(): string
    {
        return __('location.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('location.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('location.nav.plural_model_label');
    }
    public static function form(Form $form): Form
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        $locales = static::getActiveLocales();
        $weekDays = [
            'mon' => 'Понеділок',
            'tue' => 'Вівторок',
            'wed' => 'Середа',
            'thu' => 'Четвер',
            'fri' => 'Пʼятниця',
            'sat' => 'Субота',
            'sun' => 'Неділя',
        ];


        return $form->schema([
            Forms\Components\Section::make(__('location.sections.main'))->schema([
                // Переводы через Translate => пишем в JSON title/city/address
                Translate::make()
                    ->locales($locales)
                    ->prefixLocaleLabel()
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(fn (string $locale) => [
                        Forms\Components\TextInput::make('title')
                            ->label(__('location.fields.title'))
                            ->required($locale === $defaultLocale),
                        Forms\Components\TextInput::make('city')
                            ->label(__('location.fields.city')),
                        Forms\Components\TextInput::make('address')
                            ->label(__('location.fields.address')),
                    ]),

                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\TextInput::make('slug')
                        ->label(__('location.fields.slug'))
                        ->required()
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('lat')
                        ->label(__('location.fields.lat'))
                        ->numeric()
                        ->step('0.0000001'),
                    Forms\Components\TextInput::make('lng')
                        ->label(__('location.fields.lng'))
                        ->numeric()
                        ->step('0.0000001'),
                    Forms\Components\TextInput::make('google_map_link')
                        ->label(__('location.fields.google_map_link'))
                        ->placeholder('https://maps.google.com/...')
                        ->columnSpan(2),
                ]),

                Forms\Components\Select::make('svg_image_id')
                    ->label(__('location.fields.svg_image_id'))
                    ->relationship('svgImage', 'title')
                    ->getOptionLabelFromRecordUsing(fn (SvgImage $rec) => $rec->title)
                    ->searchable()->preload()->live()
                    ->helperText(__('location.helpers.svg_icon_map')),

                Forms\Components\Placeholder::make('svg_preview')
                    ->label(__('location.fields.svg_preview'))
                    ->content(function (callable $get) {
                        $id = $get('svg_image_id');
                        if (!$id) return '—';
                        $svg = SvgImage::query()->find($id)?->svg_code;
                        return $svg ? new \Illuminate\Support\HtmlString($svg) : '—';
                    })
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('map_preview')
                    ->label(__('location.fields.map_preview'))
                    ->content(function (callable $get) {
                        $lat = $get('lat'); $lng = $get('lng');
                        if (!$lat || !$lng) return '—';
                        $src = "https://maps.google.com/maps?q={$lat},{$lng}&z=15&output=embed";
                        return new \Illuminate\Support\HtmlString(
                            "<div class='w-full aspect-video rounded-lg overflow-hidden border'>
                                <iframe src='{$src}' class='w-full h-full' loading='lazy'></iframe>
                             </div>"
                        );
                    })
                    ->columnSpanFull(),
            ])->columns(2),

            // Контакты в виде билдера с slug + is_active

Section::make(__('location.sections.contacts'))

    ->schema([

        /** =======================
         *  Телефоны
         *  =======================*/
        Repeater::make('phones')
            ->label(__('location.fields.phones'))
            ->addActionLabel(__('location.actions.add_phone'))
            ->reorderableWithButtons()
            ->collapsed()
            ->schema([
                // Ряд 1: основные поля
                Grid::make(['default' => 1, 'md' => 12])->schema([
                    Forms\Components\TextInput::make('slug')
                        ->label(__('location.fields.slug'))
                        ->required()
                        ->maxLength(64)
                        ->columnSpan(['md' => 3]),

                    Forms\Components\TextInput::make('phone')
                        ->label(__('location.fields.phone'))
                        ->tel()
                        // ->mask('+38 (000) 000-00-00')
                        ->required()
                        ->columnSpan(['md' => 4]),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('location.fields.is_active'))
                        ->default(true)
                        ->inline(false)
                        ->columnSpan(['md' => 2]),

                    Forms\Components\TextInput::make('note')
                        ->label(__('location.fields.note'))
                        ->columnSpan(['md' => 3]),
                ]),

                // Ряд 2: SVG + превью
                Grid::make(['default' => 1, 'md' => 12])->schema([
                    Forms\Components\Select::make('svg_image_id')
                        ->label(__('location.fields.svg_icon'))
                        ->options(fn () => SvgImage::query()->orderBy('title')->pluck('title', 'id'))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->columnSpan(['md' => 3]),

                    Forms\Components\Placeholder::make('svg_preview')
                        ->label(__('location.fields.preview'))
                        ->content(function (Get $get) {
                            $id = $get('data.svg_image_id') ?? $get('svg_image_id');
                            if (!$id) return '—';
                            $svg = SvgImage::find($id)?->svg_code;
                            return $svg
                                ? new \Illuminate\Support\HtmlString("<div style='width:28px;height:28px;display:inline-block'>{$svg}</div>")
                                : '—';
                        })
                        ->dehydrated(false)
                        ->reactive()
                        ->columnSpan(['md' => 1])
                        ->extraAttributes(['class' => 'pt-2']),
                ]),
            ])
            ->columns(1)
            ->itemLabel(fn (array $state) =>
            trim(($state['slug'] ?? '') .
                (!empty($state['phone']) ? ' — ' . $state['phone'] : '')
            ) ?: 'Phone'
            ),

        /** =======================
         *  Emails
         *  =======================*/
        Repeater::make('emails')
            ->label(__('location.fields.emails'))
            ->addActionLabel(__('location.actions.add_email'))
            ->reorderableWithButtons()
            ->collapsed()
            ->schema([
                // Ряд 1: основные поля
                Grid::make(['default' => 1, 'md' => 12])->schema([
                    Forms\Components\TextInput::make('slug')
                        ->label(__('location.fields.slug'))
                        ->required()
                        ->maxLength(64)
                        ->columnSpan(['md' => 3]),

                    Forms\Components\TextInput::make('email')
                        ->label(__('location.fields.email'))
                        ->email()
                        ->required()
                        ->maxLength(191)
                        ->columnSpan(['md' => 4]),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('location.fields.is_active'))
                        ->default(true)
                        ->inline(false)
                        ->columnSpan(['md' => 2]),

                    Forms\Components\TextInput::make('note')
                        ->label(__('location.fields.note'))
                        ->columnSpan(['md' => 3]),
                ]),

                // Ряд 2: SVG + превью
                Grid::make(['default' => 1, 'md' => 12])->schema([
                    Forms\Components\Select::make('svg_image_id')
                        ->label(__('location.fields.svg_icon'))
                        ->options(fn () => SvgImage::query()->orderBy('title')->pluck('title', 'id'))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->columnSpan(['md' => 3]),

                    Forms\Components\Placeholder::make('svg_preview')
                        ->label(__('location.fields.preview'))
                        ->content(function (Get $get) {
                            $id = $get('data.svg_image_id') ?? $get('svg_image_id');
                            if (!$id) return '—';
                            $svg = SvgImage::find($id)?->svg_code;
                            return $svg
                                ? new \Illuminate\Support\HtmlString("<div style='width:28px;height:28px;display:inline-block'>{$svg}</div>")
                                : '—';
                        })
                        ->dehydrated(false)
                        ->reactive()
                        ->columnSpan(['md' => 1])
                        ->extraAttributes(['class' => 'pt-2']),
                ]),
            ])
            ->columns(1)
            ->itemLabel(fn (array $state) => $state['email'] ?? ($state['slug'] ?? 'Email')),
    ])
    ->collapsible(),



            Forms\Components\Section::make(__('location.sections.schedule'))->schema([
                Forms\Components\Repeater::make('schedule')
                    ->label(__('location.fields.schedule'))
                    ->reorderable()
                    ->collapsible()
                    ->defaultItems(0)
                    ->schema([


                        Forms\Components\TextInput::make('slug')
                            ->label(__('location.helpers.slug_schedule'))
                            ->required()
                            ->unique(ignorable: fn ($record) => $record) // если нужно уникально внутри записи — убери
                            ->helperText(__('location.helpers.slug_helper')),

                        // Заголовок (переводимый)
                        Translate::make()
                            ->locales($locales)
                            ->prefixLocaleLabel()
                            ->columns(1)
                            ->schema(fn (string $locale) => [
                                Forms\Components\TextInput::make("title.$locale")
                                    ->label(match ($locale) {
                                        'uk' => __('location.helpers.title_ua'),
                                        'ru' => __('location.helpers.title_ru'),
                                        default => __('location.helpers.title_en'),
                                    }),
                            ])
                            ->columnSpanFull(),

                        // Часы (переводимые)
                        Translate::make()
                            ->locales($locales)
                            ->prefixLocaleLabel()
                            ->columns(1)
                            ->schema(fn (string $locale) => [
                                Forms\Components\TextInput::make("time.$locale")
                                    ->label(match ($locale) {
                                        'uk' => __('location.helpers.time_ua'),
                                        'ru' => __('location.helpers.time_ru'),
                                        default => __('location.helpers.time_en'),
                                    }),
                            ])
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('location.helpers.working'))
                            ->default(true),

                    ])
                    ->itemLabel(fn (array $state) => $state['title.ru'] ?? $state['time.'.$defaultLocale] ?? ($state['slug'] ?? 'Графік'))
                    ->columns(2),
            ]),

            Forms\Components\Section::make('Графік v2 (по точці)')
                ->schema([
                    Forms\Components\Toggle::make('schedule_v2_enabled')
                        ->label('Увімкнути новий графік v2 для цієї точки')
                        ->inline(false)
                        ->default(false),

                    Forms\Components\Tabs::make('schedule_v2_tabs')
                        ->tabs([
                            Forms\Components\Tabs\Tab::make('Самовивіз v2')
                                ->schema([
                                    Forms\Components\Repeater::make('schedule_v2.pickup.days')
                                        ->label('Дні тижня')
                                        ->afterStateHydrated(function (Repeater $component, $state) use ($weekDays) {
                                            $rows = is_array($state) ? array_values($state) : [];

                                            if (count($rows) > 0) {
                                                $byKey = [];
                                                foreach ($rows as $row) {
                                                    $k = (string) ($row['day_key'] ?? '');
                                                    if ($k !== '') {
                                                        $byKey[$k] = $row;
                                                    }
                                                }

                                                $normalized = [];
                                                foreach ($weekDays as $key => $label) {
                                                    $existing = $byKey[$key] ?? [];
                                                    $normalized[] = [
                                                        'day_key' => $key,
                                                        'day_label' => $label,
                                                        'is_working' => (bool) ($existing['is_working'] ?? !in_array($key, ['sat', 'sun'], true)),
                                                        'open_time' => (string) ($existing['open_time'] ?? '08:30'),
                                                        'close_time' => (string) ($existing['close_time'] ?? '20:00'),
                                                        'today_cutoff_time' => (string) ($existing['today_cutoff_time'] ?? '19:59'),
                                                        'interval_step_minutes' => (int) ($existing['interval_step_minutes'] ?? 15),
                                                    ];
                                                }

                                                $component->state($normalized);
                                                return;
                                            }

                                            $component->state(
                                                collect($weekDays)->map(fn ($label, $key) => [
                                                    'day_key' => $key,
                                                    'day_label' => $label,
                                                    'is_working' => !in_array($key, ['sat', 'sun'], true),
                                                    'open_time' => '08:30',
                                                    'close_time' => '20:00',
                                                    'today_cutoff_time' => '19:59',
                                                    'interval_step_minutes' => 15,
                                                ])->values()->all()
                                            );
                                        })
                                        ->default(function () use ($weekDays) {
                                            return collect($weekDays)->map(fn ($label, $key) => [
                                                'day_key' => $key,
                                                'day_label' => $label,
                                                'is_working' => !in_array($key, ['sat', 'sun'], true),
                                                'open_time' => '08:30',
                                                'close_time' => '20:00',
                                                'today_cutoff_time' => '19:59',
                                                'interval_step_minutes' => 15,
                                            ])->values()->all();
                                        })
                                        ->addable(false)
                                        ->deletable(false)
                                        ->reorderable(false)
                                        ->schema([
                                            Forms\Components\Hidden::make('day_key'),
                                            Forms\Components\TextInput::make('day_label')->label('День')->disabled()->dehydrated(false),
                                            Forms\Components\Toggle::make('is_working')->label('Робочий день')->inline(false),
                                            Forms\Components\TimePicker::make('open_time')->label('Відкриття')->seconds(false),
                                            Forms\Components\TimePicker::make('close_time')->label('Закриття')->seconds(false),
                                            Forms\Components\TimePicker::make('today_cutoff_time')->label('Крайній час прийому')->seconds(false),
                                            Forms\Components\Select::make('interval_step_minutes')
                                                ->label('Крок інтервалів, хв')
                                                ->options([5 => '5', 10 => '10', 15 => '15', 20 => '20', 30 => '30'])
                                                ->default(15)
                                                ->native(false),
                                        ])
                                        ->columns(7)
                                        ->itemLabel(fn (array $state) => (string) ($state['day_label'] ?? 'День')),

                                    Forms\Components\Repeater::make('schedule_v2.pickup.closed_dates')
                                        ->label('Неробочі дати (самовивіз)')
                                        ->default([])
                                        ->schema([
                                            Forms\Components\DatePicker::make('date')->label('Дата')->native(false),
                                        ])
                                        ->columns(1)
                                        ->addActionLabel('Додати дату'),
                                ]),

                            Forms\Components\Tabs\Tab::make('Доставка v2')
                                ->schema([
                                    Forms\Components\Repeater::make('schedule_v2.delivery.days')
                                        ->label('Дні тижня')
                                        ->afterStateHydrated(function (Repeater $component, $state) use ($weekDays) {
                                            $rows = is_array($state) ? array_values($state) : [];

                                            if (count($rows) > 0) {
                                                $byKey = [];
                                                foreach ($rows as $row) {
                                                    $k = (string) ($row['day_key'] ?? '');
                                                    if ($k !== '') {
                                                        $byKey[$k] = $row;
                                                    }
                                                }

                                                $normalized = [];
                                                foreach ($weekDays as $key => $label) {
                                                    $existing = $byKey[$key] ?? [];
                                                    $normalized[] = [
                                                        'day_key' => $key,
                                                        'day_label' => $label,
                                                        'is_working' => (bool) ($existing['is_working'] ?? !in_array($key, ['sat', 'sun'], true)),
                                                        'open_time' => (string) ($existing['open_time'] ?? '09:00'),
                                                        'close_time' => (string) ($existing['close_time'] ?? '21:00'),
                                                        'today_cutoff_time' => (string) ($existing['today_cutoff_time'] ?? '20:00'),
                                                        'interval_step_minutes' => (int) ($existing['interval_step_minutes'] ?? 15),
                                                    ];
                                                }

                                                $component->state($normalized);
                                                return;
                                            }

                                            $component->state(
                                                collect($weekDays)->map(fn ($label, $key) => [
                                                    'day_key' => $key,
                                                    'day_label' => $label,
                                                    'is_working' => !in_array($key, ['sat', 'sun'], true),
                                                    'open_time' => '09:00',
                                                    'close_time' => '21:00',
                                                    'today_cutoff_time' => '20:00',
                                                    'interval_step_minutes' => 15,
                                                ])->values()->all()
                                            );
                                        })
                                        ->default(function () use ($weekDays) {
                                            return collect($weekDays)->map(fn ($label, $key) => [
                                                'day_key' => $key,
                                                'day_label' => $label,
                                                'is_working' => !in_array($key, ['sat', 'sun'], true),
                                                'open_time' => '09:00',
                                                'close_time' => '21:00',
                                                'today_cutoff_time' => '20:00',
                                                'interval_step_minutes' => 15,
                                            ])->values()->all();
                                        })
                                        ->addable(false)
                                        ->deletable(false)
                                        ->reorderable(false)
                                        ->schema([
                                            Forms\Components\Hidden::make('day_key'),
                                            Forms\Components\TextInput::make('day_label')->label('День')->disabled()->dehydrated(false),
                                            Forms\Components\Toggle::make('is_working')->label('Робочий день')->inline(false),
                                            Forms\Components\TimePicker::make('open_time')->label('Відкриття')->seconds(false),
                                            Forms\Components\TimePicker::make('close_time')->label('Закриття')->seconds(false),
                                            Forms\Components\TimePicker::make('today_cutoff_time')->label('Крайній час прийому')->seconds(false),
                                            Forms\Components\Select::make('interval_step_minutes')
                                                ->label('Крок інтервалів, хв')
                                                ->options([5 => '5', 10 => '10', 15 => '15', 20 => '20', 30 => '30'])
                                                ->default(15)
                                                ->native(false),
                                        ])
                                        ->columns(7)
                                        ->itemLabel(fn (array $state) => (string) ($state['day_label'] ?? 'День')),

                                    Forms\Components\Repeater::make('schedule_v2.delivery.closed_dates')
                                        ->label('Неробочі дати (доставка)')
                                        ->default([])
                                        ->schema([
                                            Forms\Components\DatePicker::make('date')->label('Дата')->native(false),
                                        ])
                                        ->columns(1)
                                        ->addActionLabel('Додати дату'),
                                ]),
                        ]),
                ])
                ->collapsible(),

            Forms\Components\Section::make(__('location.sections.publish'))->schema([
                Forms\Components\Toggle::make('is_active')->label(__('location.fields.is_active'))->default(true),
                Forms\Components\TextInput::make('sort')->label(__('location.fields.sort'))->numeric()->default(100),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        $locale = app()->getLocale();

        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('location.columns.title'))
                   // ->formatStateUsing(fn ($state, Location $record) => $record->t('title', $locale))
                   /* ->searchable(
                        query: fn (Builder $q, string $search) =>
                    $q->where("title->$locale", 'like', "%{$search}%")
                    )
                    ->sortable(
                        query: fn (Builder $q, string $dir) =>
                    $q->orderBy("title->$locale", $dir)
                    )*/,

                Tables\Columns\TextColumn::make('city')
                    ->label(__('location.columns.city'))
                  ,

                Tables\Columns\TextColumn::make('address')
                    ->label(__('location.columns.address'))
                    ->limit(40),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('location.columns.is_active')),

                Tables\Columns\TextColumn::make('reviews_count')
                    ->counts('reviews')
                    ->label(__('location.columns.reviews_count')),
                TextColumn::make('phones_preview')
                    ->label(__('location.columns.phones'))
                    ->getStateUsing(function (Location $record) {
                        $items = (array) ($record->phones ?? []);
                        if (! $items) return '—';

                        $lines = [];
                        foreach ($items as $it) {
                            $label = $it['label'] ?? ($it['slug'] ?? null);
                            $val   = $it['phone'] ?? null;
                            if (! $label && ! $val) continue;

                            $lines[] = trim(
                            //    ($label ? e($label) : '') .
                                ($val   ?  e($val) : '')
                            );
                        }

                        return $lines ? implode('<br>', $lines) : '—';
                    })
                    ->html()
                    ->wrap()
                    ->extraAttributes(['class' => 'text-xs text-gray-500 whitespace-normal leading-5']),

                TextColumn::make('emails_preview')
                    ->label(__('location.columns.emails'))
                    ->getStateUsing(function (Location $record) {
                        $items = (array) ($record->emails ?? []);
                        if (! $items) return '—';

                        $lines = [];
                        foreach ($items as $it) {
                            $label = $it['label'] ?? ($it['slug'] ?? null);
                            $val   = $it['email'] ?? null;
                            if (! $label && ! $val) continue;

                            $lines[] = trim(
                              //  ($label ? e($label) : '') .
                                ($val   ?  e($val) : '')
                            );
                        }

                        return $lines ? implode('<br>', $lines) : '—';
                    })
                    ->html()
                    ->wrap()
                    ->extraAttributes(['class' => 'text-xs text-gray-500 whitespace-normal leading-5']),

                TextColumn::make('schedule_preview')
                    ->label(__('location.columns.schedule'))
                    ->getStateUsing(function (\App\Models\Location $record) {
                        $items = (array) ($record->schedule ?? []);
                        if (! $items) return '—';

                        $loc      = app()->getLocale();
                        $fallback = 'uk';

                        // Нормализатор в строку
                        $toText = function ($val): string {
                            if (is_null($val)) return '';
                            if (is_scalar($val)) return (string) $val;
                            if (is_array($val)) {
                                // возьмём первый непустой скаляр
                                foreach ($val as $v) {
                                    if (is_scalar($v) && $v !== '') return (string) $v;
                                }
                                return '';
                            }
                            return (string) $val;
                        };

                        $lines = [];

                        foreach ($items as $it) {
                            // достаём по локали, затем fallback, затем сырьё
                            $rawTitle = data_get($it, "title.$loc")
                                ?? data_get($it, "title.$fallback")
                                ?? ($it['title'] ?? null);

                            $rawTime  = data_get($it, "time.$loc")
                                ?? data_get($it, "time.$fallback")
                                ?? ($it['time'] ?? null);

                            $title = $toText($rawTitle);
                            $time  = $toText($rawTime);

                            // пропускаем пустое
                            if ($title === '' && $time === '') continue;

                            $isActive = (bool) ($it['is_active'] ?? true);

                            // экранируем только текстовые части
                            $line = trim(
                                ($title !== '' ? e($title) : '') .
                                ($time  !== '' ? ' — ' . e($time) : '')
                            );

                            if ($line === '') continue;

                            // неактивные — полупрозрачно
                            if (! $isActive) {
                                $line = '<span class="opacity-60">' . $line . '</span>';
                            }

                            $lines[] = $line;
                        }

                        return $lines ? implode('<br>', $lines) : '—';
                    })
                    ->html()
                    ->wrap()
                    ->extraAttributes(['class' => 'text-xs text-gray-500 whitespace-normal leading-5'])  ,
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d.m.Y H:i')
                    ->label(__('location.columns.updated_at'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label(__('location.filters.is_active')),
                Tables\Filters\SelectFilter::make('city_json')
                    ->label(__('location.filters.city'))
                    ->options(function () use ($locale) {
                        return Location::query()
                            ->select("city->$locale as name")
                            ->whereNotNull('city')
                            ->distinct()
                            ->orderBy("city->$locale")
                            ->pluck('name', 'name')
                            ->filter()
                            ->toArray();
                    })
                    ->query(function (Builder $q, array $data) use ($locale) {
                        if (!empty($data['value'])) {
                            $q->where("city->$locale", $data['value']);
                        }
                    }),
            ])
            ->actions([ Tables\Actions\EditAction::make() ])
            ->bulkActions([ Tables\Actions\DeleteBulkAction::make() ])
            ->defaultSort('sort');

    }

    public static function getRelations(): array
    {
        return [ RelationManagers\ReviewsRelationManager::class ];
    }
    public static function getActiveLocales(): array
    {
        return Language::where('active', true)
            ->orderBy('position')
            ->pluck('code')
            ->map(fn($c) => strtolower($c))
            ->toArray();
    }
    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit'   => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}
