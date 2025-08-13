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
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Pages\SubNavigationPosition;
use Illuminate\Support\Str;
use SolutionForest\FilamentTranslateField\Forms\Component\Translate;
use App\Models\Setting;
use App\Models\Language;
use Filament\Resources\Concerns\Translatable;
use SolutionForest\FilamentTree\Pages\TreePage;
use App\Filament\Clusters\Products\Resources\ProductCategoryResource\Pages\ProductCategoryTree;
use Filament\Pages\Actions\Action;
use App\Filament\Clusters\Products\Resources\ProductCategoryResource\RelationManagers\ProductRelationManager;

class ProductCategoryResource extends Resource
{
    use Translatable;
    protected static ?string $model = ProductCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Категории товаров';
    protected static ?string $modelLabel = 'Категория товаров';
    protected static ?string $pluralModelLabel = 'Категории товаров';
    protected static ?string $cluster = Products::class;
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
    protected static ?int $navigationSort = 1;

    protected static function getMainTab(array $locales, string $defaultLocale): Tab
    {
        return  Tab::make('Основные')
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
                                            ->schema(fn(string $locale) => [
                                                Forms\Components\TextInput::make('title')
                                                    ->required($locale === $defaultLocale)
                                                    ->label('Название')
                                                    ->maxLength(255)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                                                Forms\Components\MarkdownEditor::make('description')->label('Описание'),
                                            ]),
                                        Forms\Components\TextInput::make('slug')
                                            ->disabled()
                                            ->dehydrated()
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(ProductCategory::class, 'slug', ignoreRecord: true),
                                    ]),

                                Forms\Components\Select::make('parent_id')
                                    ->label('Родительская категория')
                                    ->native(false)
                                    ->default(-1)
                                    ->options(function () use ($defaultLocale) {
                                        $locale = $defaultLocale;
                                        $options = ProductCategory::where('parent_id', -1)
                                            ->get()
                                            ->mapWithKeys(function ($cat) use ($locale) {
                                                $title = json_decode($cat->getRawOriginal('title'), true);
                                                return [
                                                    $cat->id => $title[$locale] ?? $title['uk'] ?? 'Без названия',
                                                ];
                                            })
                                            ->toArray();
                                        return [-1 => 'Корневая категория'] + $options;
                                    })
                                    ->searchable()
                                    ->placeholder('Выберите родительскую категорию'),

                                Forms\Components\Toggle::make('is_visible')
                                    ->label('Видима пользователям')
                                    ->default(true),
                            ]),

    ]);
    }
    public function characteristics()
    {
        return $this->belongsToMany(
            \App\Models\Shop\Characteristic::class,
            'category_characteristic',
            'category_id',
            'characteristic_id'
        )
            ->withPivot(['affects_price', 'is_required', 'expanded']) // если есть
            ->withTimestamps(); // если используете created_at/updated_at
    }

    protected static function getCharakTab(array $locales): Tab
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        // список активных языков из таблицы languages
        $locales = static::getActiveLocales();
        return Tab::make('Характеристики')
            ->schema([
                CheckboxList::make('characteristics')
                    ->label('Характеристики')
                    ->relationship('characteristics', 'name') // <== критично важно
                    ->options(function () use($defaultLocale)  {
                        $locale = $defaultLocale; // или $livewire->activeLocale если внутри компонента Filament
                        return Characteristic::all()->
                        mapWithKeys(function ($item) use ($locale) {
                            return [$item->id => $item->getTranslation('name', $locale)];
                        });
                    })
                    ->columns(2)
            ]);
     /*   ->mapWithKeys(fn($cat) => [
        $cat->id => (
            json_decode($cat->getRawOriginal('name'), true)[$defaultLocale]
            ?? json_decode($cat->getRawOriginal('name'), true)[config('app.locale')]
        ),
    ])*/
    }  protected static function getVariacTab(array $locales): Tab
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        return  Tab::make('Вариации')
                      ->schema([
                          CheckboxList::make('variations')
                              ->label('Название вариации')
                              ->relationship('variations', 'name') // <== критично важно
                              ->options(function () use($defaultLocale)  {
                                  $locale = $defaultLocale; // или $livewire->activeLocale если внутри компонента Filament
                                  return Variation::all()->
                                  mapWithKeys(function ($item) use ($locale) {
                                      return [$item->id => $item->name];
                                  });
                              })
                              ->columns(2)


                      ])
                  ;

    }
    public static function form(Form $form): Form
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        $locales = static::getActiveLocales();

        return $form->schema([

                Tabs::make('Категория')
                    ->columns(1)
                    ->tabs([
                        static::getMainTab($locales, $defaultLocale),
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
            ->map(fn($c) => strtolower($c))
            ->toArray();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->label('slug')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Родитель')
                    ->getStateUsing(function (ProductCategory $record, TextColumn $column, $livewire) {
                        $locale = $livewire->activeLocale;
                        return $record->parent ? $record->parent->getTranslation('name', $locale) : '—';
                    })
                    ->searchable()->sortable(),
                Tables\Columns\IconColumn::make('is_visible')->label('Visibility')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->label('Updated Date')->date()->sortable(),
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
