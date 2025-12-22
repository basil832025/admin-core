<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuResource\Pages;
use App\Models\Language;
use App\Models\Menu;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use SolutionForest\FilamentTranslateField\Forms\Component\Translate;
use Illuminate\Support\Str;
use App\Filament\Resources\MenuItemResource\Pages\ItemsTree as MenuItemsTreePage;
class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;
    protected static ?string $navigationIcon = 'heroicon-o-bars-3';
    protected static ?string $navigationGroup = 'Контент';
    protected static ?string $modelLabel = null;
    protected static ?string $pluralModelLabel = null;

    public static function getModelLabel(): string
    {
        return __('menu.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('menu.nav.plural_model_label');
    }
    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        $locales        = \App\Models\Setting::getActiveLocales();;
        $defaultLocale  = Setting::value('default_language_code') ?: config('app.locale');

        return $form->schema([
            // === Ряд 1: основные поля ===
            Forms\Components\Grid::make(12)->schema([
                Forms\Components\TextInput::make('slug')
                    ->label(__('menu.fields.slug'))
                    ->unique(ignoreRecord: true)
                    ->required()
                    ->helperText(__('menu.helpers.slug'))
                    ->maxLength(64)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('slug', \Illuminate\Support\Str::slug((string)$state, '-')))
                    ->columnSpan(4),

                Forms\Components\Select::make('locale')
                    ->label(__('menu.fields.locale'))
                    ->options(array_combine($locales, $locales))
                    ->searchable()
                    ->preload()
                    ->helperText(__('menu.helpers.locale'))
                    ->columnSpan(3),

                Forms\Components\TextInput::make('sort')
                    ->numeric()
                    ->default(100)
                    ->label(__('menu.fields.sort'))
                    ->columnSpan(2),


            ]),

// === Ряд 2: параметры структуры ===
            Forms\Components\Grid::make(12)->schema([
                Forms\Components\TextInput::make('max_depth')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->label(__('menu.fields.max_depth'))
                    ->helperText(__('menu.helpers.max_depth'))
                    ->columnSpan(3),
                Forms\Components\Toggle::make('is_active')
                    ->label(__('menu.fields.is_active'))
                    ->default(true)
                    ->columnSpan(2),
            ]),


            Translate::make()
                ->locales($locales)
                ->prefixLocaleLabel()
                ->columns(1)
                ->columnSpanFull()
                    ->schema(fn (string $locale) => [
                        Forms\Components\TextInput::make("title.$locale")
                            ->label(__('menu.fields.title'))
                            ->required($locale === $defaultLocale)
                            ->maxLength(255),
                    ]),

            Forms\Components\ViewField::make('hint')
                ->view('filament.partials.menu-hint')
                ->columnSpanFull(),
        ])->columns(12);
    }

    public static function table(Table $table): Table
    {
        $locales = Language::query()->pluck('code')->values()->all() ?: ['uk','en','ru'];
        $appLocale = app()->getLocale();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make("title.$appLocale")
                    ->label(__('menu.columns.title'))
                    ->formatStateUsing(function ($state, Menu $record) use ($locales) {
                        // 1. Нормализуем текущее состояние
                        if (is_array($state)) {
                            // пробуем взять первый скалярный элемент
                            $state = collect($state)->first(fn ($v) => is_scalar($v) && $v !== '');
                        }

                        if (is_scalar($state) && $state !== '') {
                            return (string) $state;
                        }

                        // 2. Пытаемся найти хоть какой-то перевод в других локалях
                        $fallback = collect($locales)
                            ->map(fn ($l) => data_get($record->title, $l))
                            ->first(fn ($v) => is_scalar($v) && $v !== '');

                        return $fallback !== null ? (string) $fallback : '—';
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('slug')->label(__('menu.columns.slug'))->searchable(),

                Tables\Columns\TextColumn::make('locale')->label(__('menu.columns.locale'))->sortable()->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('menu.columns.is_active'))
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('max_depth')
                    ->label(__('menu.columns.max_depth'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort')->label(__('menu.columns.sort'))->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime('Y-m-d H:i')->label(__('menu.columns.updated_at'))->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('locale')
                    ->label(__('menu.filters.locale'))
                    ->options(array_combine($locales, $locales)),
                Tables\Filters\TernaryFilter::make('is_active')->label(__('menu.filters.is_active'))->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('itemsTree')
                    ->label(__('menu.actions.items_tree'))
                    ->icon('heroicon-o-rectangle-group')
                    ->url(fn (\App\Models\Menu $record) =>
                        // ГЕНЕРИМ ПО ИМЕНИ РОУТА и передаём КЛЮЧ 'menu'
                    route('filament.admin.resources.menu-items.items-tree', [
                        'menu' => $record->getKey(),
                    ])
                    )
                    /*->url(fn (\App\Models\Menu $record) =>
                        // важен ключ record!
                    route('filament.admin.resources.menu-items.items-tree', [
                        'record' => $record->getKey(),
                    ])
                    )*/
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort');
    }

    public static function getRelations(): array
    {
        return [
            // можно добавить RelationManager для плоского режима,
            // но в этом проекте основной сценарий — через TreePage
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMenus::route('/'),
            'create' => Pages\CreateMenu::route('/create'),
            'edit'   => Pages\EditMenu::route('/{record}/edit'),
            // древовидные пункты для конкретного меню:
            // ВАЖНО: страница дерева как страница РЕСУРСА MenuResource
            // параметр {record} — это id Menu, он прилетит в ItemsTree::mount($record)
         //   'items'  => Pages\ItemsTree::route('/{record}/items'),
        ];
    }
}
