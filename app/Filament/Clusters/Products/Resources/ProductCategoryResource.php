<?php

namespace App\Filament\Clusters\Products\Resources;

use App\Filament\Clusters\Products;
use App\Filament\Clusters\Products\Resources\ProductCategoryResource\Pages;
use App\Filament\Clusters\Products\Resources\ProductCategoryResource\RelationManagers;
use App\Models\Shop\Characteristic;
use App\Models\Shop\ProductCategory;
use App\Models\Shop\Variation;
use Filament\Forms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Pages\SubNavigationPosition;
use Illuminate\Support\Str;
use SolutionForest\FilamentTranslateField\Forms\Component\Translate;
use App\Models\Setting;
use App\Models\Language;
use Filament\Resources\Concerns\Translatable;
use App\Filament\Clusters\Products\Resources\ProductCategoryResource\RelationManagers\ProductRelationManager;

class ProductCategoryResource extends Resource
{
    use Translatable;

    protected static ?string $model = ProductCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // ⚠️ не хардкодим строки — отдадим через методы ниже
    protected static ?string $navigationLabel = null;
    protected static ?string $modelLabel = null;
    protected static ?string $pluralModelLabel = null;

    protected static ?string $cluster = Products::class;
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
    protected static ?int $navigationSort = 1;

    /** Навигация/лейблы ресурса */
    public static function getNavigationLabel(): string
    {
        return __('category.nav.navigation_label');
    }
    public static function getModelLabel(): string
    {
        return __('category.nav.model_label');
    }
    public static function getPluralModelLabel(): string
    {
        return __('category.nav.plural_model_label');
    }
    public static function getBreadcrumb(): string
    {
        return __('category.nav.navigation_label');
    }

    /** Вкладка «Основные» */
    protected static function getMainTab(array $locales, string $defaultLocale): Tab
    {
        return Tab::make(__('category.tabs.main'))
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Grid::make()
                            ->schema([
                                Translate::make()
                                    ->locales($locales)
                                    ->prefixLocaleLabel()
                                    ->columns(1)
                                    ->columnSpanFull()
                                    ->schema(fn (string $locale) => [
                                        TextInput::make('title')
                                            ->required($locale === $defaultLocale)
                                            ->label(__('category.fields.title'))
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(
                                                fn (string $operation, $state, Forms\Set $set) =>
                                                $operation === 'create' ? $set('slug', Str::slug($state)) : null
                                            ),
                                        TextInput::make('description_title')
                                            ->label('Название описания')
                                            ->maxLength(255),
                                        MarkdownEditor::make('description')
                                            ->label(__('category.fields.description')),
                                    ]),

                                Forms\Components\TextInput::make('slug')
                                    ->label(__('category.fields.slug'))
                                    ->disabled()
                                    ->dehydrated()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ProductCategory::class, 'slug', ignoreRecord: true),
                            ]),

                        Forms\Components\Select::make('parent_id')
                            ->label(__('category.fields.parent_id'))
                            ->native(false)
                            ->default(-1)
                            ->options(function () use ($defaultLocale) {
                                $locale = $defaultLocale;
                                $options = ProductCategory::where('parent_id', -1)
                                    ->get()
                                    ->mapWithKeys(function ($cat) use ($locale) {
                                        $title = json_decode($cat->getRawOriginal('title'), true);
                                        return [
                                            $cat->id => $title[$locale] ?? $title['uk'] ?? __('category.misc.untitled'),
                                        ];
                                    })
                                    ->toArray();

                                // «Корневая/Базовая» категория
                                return [-1 => __('category.fields.parent_root')] + $options;
                            })
                            ->searchable()
                            ->placeholder(__('category.placeholders.parent_id')),

                        Forms\Components\Toggle::make('is_visible')
                            ->label(__('category.fields.is_visible'))
                            ->default(true),
                    ]),
            ]);
    }

    /** Вкладка «SEO» */
    protected static function getSeoTab(array $locales, string $defaultLocale): Tab
    {
        return Tab::make('SEO')
            ->schema([
                Translate::make()
                    ->locales($locales)
                    ->prefixLocaleLabel()
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(fn (string $locale) => [
                        TextInput::make('seo_title')
                            ->label('SEO-заголовок')
                            ->maxLength(255),
                        TextInput::make('seo_description')
                            ->label('SEO-описание')
                            ->maxLength(255),
                        TextInput::make('seo_keywords')
                            ->label('SEO-ключевые слова')
                            ->maxLength(255),
                    ]),
            ]);
    }

    /** Вкладка «Характеристики» */
    protected static function getCharakTab(array $locales): Tab
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        $locales = static::getActiveLocales();

        return Tab::make(__('category.tabs.characteristics'))
            ->schema([
                CheckboxList::make('characteristics')
                    ->label(__('category.fields.characteristics'))
                    ->relationship('characteristics', 'name')
                    ->options(function () use ($defaultLocale) {
                        $locale = $defaultLocale;
                        return Characteristic::all()
                            ->mapWithKeys(function ($item) use ($locale) {
                                return [$item->id => $item->getTranslation('name', $locale)];
                            });
                    })
                    ->columns(2),
            ]);
    }

    /** Вкладка «Вариации» */
    protected static function getVariacTab(array $locales): Tab
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');

        return Tab::make(__('category.tabs.variations'))
            ->schema([
                CheckboxList::make('variations')
                    ->label(__('category.fields.variation_name'))
                    ->relationship('variations', 'name')
                    ->options(function () use ($defaultLocale) {
                        $locale = $defaultLocale;
                        return Variation::all()
                            ->mapWithKeys(function ($item) use ($locale) {
                                return [$item->id => $item->name];
                            });
                    })
                    ->columns(2),
            ]);
    }

    public static function form(Form $form): Form
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        $locales = static::getActiveLocales();

        return $form->schema([
            Tabs::make()
                ->columns(1)
                ->tabs([
                    static::getMainTab($locales, $defaultLocale),
                    static::getSeoTab($locales, $defaultLocale),
                    static::getCharakTab($locales),
                    static::getVariacTab($locales),
                ]),
        ])->columns(1);
    }

    public static function model(): string
    {
        return \App\Models\Shop\ProductCategory::class;
    }

    protected static function getActiveLocales(): array
    {
        return Language::where('active', true)
            ->orderBy('position')
            ->pluck('code')
            ->map(fn ($c) => strtolower($c))
            ->toArray();
    }

    public static function table(Table $table): Table
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('category.columns.title'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label(__('category.columns.slug'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label(__('category.columns.parent'))
                    ->getStateUsing(function (ProductCategory $record) use ($defaultLocale) {
                        $locale = $defaultLocale;
                        return $record->parent ? $record->parent->getTranslation('name', $locale) : '—';
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label(__('category.columns.is_visible'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('category.columns.updated_at'))
                    ->date()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ProductRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ProductCategoryTree::route('/'),
            'create' => Pages\CreateProductCategory::route('/create'),
            'edit' => Pages\EditProductCategory::route('/{record}/edit'),
            'tree-list' => Pages\ProductCategoryTree::route('/tree-list'),
        ];
    }
}
