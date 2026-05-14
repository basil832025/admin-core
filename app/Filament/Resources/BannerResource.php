<?php

namespace App\Filament\Resources;

use AmidEsfahani\FilamentTinyEditor\TinyEditor;
use App\Filament\Resources\BannerResource\Pages;
use App\Models\Banner;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use SolutionForest\FilamentTranslateField\Forms\Component\Translate;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Language;


class BannerResource extends Resource
{
    use Translatable;
    protected static ?string $model = Banner::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationGroup = 'Контент';
    protected static ?string $navigationLabel = 'Баннери';
    protected static ?string $pluralLabel = 'Баннери';
    protected static ?string $modelLabel = 'Баннер';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        $locales = Setting::getActiveLocales();
        return $form
            ->schema([
                Section::make('Основне')
                    ->columns(2)
                    ->schema([
                        Translate::make()
                            ->locales($locales)
                            ->prefixLocaleLabel()
                            ->columns(1)
                            ->columnSpanFull()
                            ->schema(fn(string $locale) => [
                               TextInput::make('title')
                                    ->label('Заголовок')
                                    ->maxLength(255)
                                   ->required($locale === $defaultLocale),

                                TextInput::make('subtitle')
                                    ->label('Підзаголовок')
                                    ->maxLength(255),

                                TextInput::make('button_text')
                                    ->label('Текст кнопки')
                                    ->maxLength(255),

                            ]),






                        Forms\Components\TextInput::make('url')
                            ->label('Посилання')
                            ->maxLength(512),

                        Forms\Components\Select::make('target')
                            ->label('Відкрити посилання')
                            ->options([
                                '_self'  => 'В цьому вікні',
                                '_blank' => 'В новому вікні',
                            ])
                            ->default('_self'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Активний')
                            ->default(true),

                        Forms\Components\TextInput::make('sort')
                            ->label('Сортування')
                            ->numeric()
                            ->default(0),

                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Показувати з'),

                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('Показувати до'),

                        Section::make('Пріоритет по днях/часу')
                            ->description('Якщо поточний час попадає в інтервал — баннер стає першим (по найбільшому пріоритету).')
                            ->columnSpanFull()
                            ->schema([
                                Repeater::make('schedule')
                                    ->label('Розклад')
                                    ->default([])
                                    ->columns(12)
                                    ->schema([
                                        Grid::make(12)
                                            ->schema([
                                                Select::make('type')
                                                    ->label('Тип')
                                                    ->options([
                                                        'range' => 'Інтервал (день+час -> день+час)',
                                                        'daily' => 'По днях тижня (часи)',
                                                    ])
                                                    ->default('range')
                                                    ->columnSpan([
                                                        'default' => 12,
                                                        'md' => 6,
                                                    ]),

                                                TextInput::make('priority')
                                                    ->label('Пріоритет')
                                                    ->numeric()
                                                    ->default(100)
                                                    ->placeholder('100')
                                                    ->columnSpan([
                                                        'default' => 8,
                                                        'md' => 4,
                                                    ]),

                                                Forms\Components\Toggle::make('is_active')
                                                    ->hiddenLabel()
                                                    ->helperText('Активно')
                                                    ->default(true)
                                                    ->columnSpan([
                                                        'default' => 4,
                                                        'md' => 2,
                                                    ]),
                                            ])
                                            ->columnSpanFull(),

                                        Grid::make(12)
                                            ->schema([
                                                CheckboxList::make('days')
                                                    ->label('Дні')
                                                    ->options([
                                                        1 => 'Пн',
                                                        2 => 'Вт',
                                                        3 => 'Ср',
                                                        4 => 'Чт',
                                                        5 => 'Пт',
                                                        6 => 'Сб',
                                                        7 => 'Нд',
                                                    ])
                                                    ->columns(7)
                                                    ->visible(fn (Get $get) => ($get('type') ?? 'range') === 'daily')
                                                    ->columnSpan([
                                                        'default' => 12,
                                                        'md' => 12,
                                                    ]),

                                                // Range: 4 поля в один ряд (md)
                                                Select::make('dow_from')
                                                    ->label('З дня')
                                                    ->options([
                                                        1 => 'Пн',
                                                        2 => 'Вт',
                                                        3 => 'Ср',
                                                        4 => 'Чт',
                                                        5 => 'Пт',
                                                        6 => 'Сб',
                                                        7 => 'Нд',
                                                    ])
                                                    ->required(fn (Get $get) => ($get('type') ?? 'range') === 'range')
                                                    ->visible(fn (Get $get) => ($get('type') ?? 'range') === 'range')
                                                    ->columnSpan([
                                                        'default' => 12,
                                                        'md' => 3,
                                                    ]),

                                                TimePicker::make('time_from')
                                                    ->label('З часу')
                                                    ->seconds(false)
                                                    ->required()
                                                    ->visible(fn (Get $get) => ($get('type') ?? 'range') === 'range')
                                                    ->columnSpan([
                                                        'default' => 12,
                                                        'md' => 3,
                                                    ]),

                                                Select::make('dow_to')
                                                    ->label('По день')
                                                    ->options([
                                                        1 => 'Пн',
                                                        2 => 'Вт',
                                                        3 => 'Ср',
                                                        4 => 'Чт',
                                                        5 => 'Пт',
                                                        6 => 'Сб',
                                                        7 => 'Нд',
                                                    ])
                                                    ->required(fn (Get $get) => ($get('type') ?? 'range') === 'range')
                                                    ->visible(fn (Get $get) => ($get('type') ?? 'range') === 'range')
                                                    ->columnSpan([
                                                        'default' => 12,
                                                        'md' => 3,
                                                    ]),

                                                TimePicker::make('time_to')
                                                    ->label('По час')
                                                    ->seconds(false)
                                                    ->required()
                                                    ->visible(fn (Get $get) => ($get('type') ?? 'range') === 'range')
                                                    ->columnSpan([
                                                        'default' => 12,
                                                        'md' => 3,
                                                    ]),

                                                // Daily: тільки часи (в один ряд на md)
                                                TimePicker::make('time_from_daily')
                                                    ->label('З часу')
                                                    ->seconds(false)
                                                    ->required(fn (Get $get) => ($get('type') ?? 'range') === 'daily')
                                                    ->visible(fn (Get $get) => ($get('type') ?? 'range') === 'daily')
                                                    ->dehydrated(false)
                                                    ->afterStateHydrated(fn (TimePicker $component, $state, Get $get) => $component->state($get('time_from')))
                                                    ->afterStateUpdated(fn ($state, callable $set) => $set('time_from', $state))
                                                    ->columnSpan([
                                                        'default' => 12,
                                                        'md' => 6,
                                                    ]),

                                                TimePicker::make('time_to_daily')
                                                    ->label('По час')
                                                    ->seconds(false)
                                                    ->required(fn (Get $get) => ($get('type') ?? 'range') === 'daily')
                                                    ->visible(fn (Get $get) => ($get('type') ?? 'range') === 'daily')
                                                    ->dehydrated(false)
                                                    ->afterStateHydrated(fn (TimePicker $component, $state, Get $get) => $component->state($get('time_to')))
                                                    ->afterStateUpdated(fn ($state, callable $set) => $set('time_to', $state))
                                                    ->columnSpan([
                                                        'default' => 12,
                                                        'md' => 6,
                                                    ]),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->addActionLabel('Додати інтервал')
                                    ->reorderable(false)
                                    ->collapsed()
                                    ->itemLabel(function (array $state): ?string {
                                        $type = (string)($state['type'] ?? 'range');
                                        $p = (int)($state['priority'] ?? 0);
                                        if ($type === 'daily') {
                                            $tf = (string)($state['time_from'] ?? '');
                                            $tt = (string)($state['time_to'] ?? '');
                                            return trim("Щодня {$tf}-{$tt} (p={$p})");
                                        }
                                        $df = (int)($state['dow_from'] ?? 0);
                                        $dt = (int)($state['dow_to'] ?? 0);
                                        $tf = (string)($state['time_from'] ?? '');
                                        $tt = (string)($state['time_to'] ?? '');
                                        if ($df && $dt && $tf && $tt) {
                                            return trim("{$df} {$tf} -> {$dt} {$tt} (p={$p})");
                                        }
                                        return "Інтервал (p={$p})";
                                    }),
                            ]),
                    ]),

                Section::make('Зображення')
                    ->schema([
                        // Универсальные картинки (для всех языков)
                        FileUpload::make('image')
                            ->label('Універсальне зображення (desktop)')
                            ->image()
                            ->directory('banners')
                            ->disk('public')
                            ->required(),

                        FileUpload::make('image_mobile')
                            ->label('Універсальне зображення (mobile)')
                            ->image()
                            ->directory('banners')
                            ->disk('public'),

                        // Локализованные картинки по вкладкам языков
                        Translate::make()
                            ->locales($locales)          // те же языки, что в справочнике
                            ->prefixLocaleLabel()
                            ->columns(1)
                            ->columnSpanFull()
                            ->schema(fn (string $locale) => [
                                FileUpload::make("images")
                                    ->label('Зображення для мови (desktop)')
                                    ->image()
                                    ->directory('banners')
                                    ->disk('public')
                                    ->required(false)
                                    ->helperText(
                                        $locale === $defaultLocale
                                            ? 'Можна не заповнювати - тоді буде використовуватись універсальне зображення.'
                                            : 'Якщо не заповнено - буде використовуватись універсальне або українське зображення.'
                                    ),

                                FileUpload::make("images_mobile")
                                    ->label('Зображення для мови (mobile)')
                                    ->image()
                                    ->directory('banners')
                                    ->disk('public')
                                    ->required(false)
                                    ->helperText(
                                        $locale === $defaultLocale
                                            ? 'Можна не заповнювати - тоді буде використовуватись універсальне mobile-зображення або desktop.'
                                            : 'Якщо не заповнено - буде використовуватись універсальне mobile-зображення, локалізоване desktop або українське зображення.'
                                    ),
                            ]),
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Баннер')
                    ->disk('public')
                    ->height(50),

                Tables\Columns\TextColumn::make('title')
                    ->label('Заголовок')
                    ->limit(40)
                    ->searchable(),

                Tables\Columns\TextColumn::make('url')
                    ->label('Посилання')
                    ->limit(40),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активний')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort')
                    ->label('Сорт.')
                    ->sortable(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('З')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('До')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('schedule')
                    ->label('Розклад')
                    ->formatStateUsing(function ($state, Banner $record) {
                        $lines = $record->scheduleHumanLines('uk');
                        return $lines ? implode("\n", $lines) : '';
                    })
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort', 'asc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Активний'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit'   => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
