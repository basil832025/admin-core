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
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
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

                        Forms\Components\Grid::make(12)->schema([
                            ViewField::make('icon')
                                ->label('Іконка')
                                ->default(\App\Support\ProductCategoryIcons::DEFAULT)
                                ->rules([fn () => \Illuminate\Validation\Rule::in(array_keys(\App\Support\ProductCategoryIcons::options() + \App\Support\ProductCategoryIcons::customOptions()))])
                                ->view('filament.forms.components.product-category-icon-picker')
                                ->columnSpan(9),
                            ColorPicker::make('icon_color')
                                ->label('Колір іконки')
                                ->helperText('HEX, наприклад #EF4444. Якщо не вказано — колір інтерфейсу.')
                                ->regex('/^#[0-9A-Fa-f]{6}$/')
                                ->live()
                                ->columnSpan(3),
                            FileUpload::make('category_icon_upload')
                                ->label('Додати власну SVG-іконку до колекції')
                                ->helperText('SVG до 256 КБ. Файл буде безпечно перевірено, додано в колекцію та вибрано для цієї категорії.')
                                ->acceptedFileTypes(['image/svg+xml', 'image/svg'])
                                ->maxSize(256)
                                ->previewable(false)
                                ->storeFiles(false)
                                ->dehydrated(false)
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                    $upload = is_array($state) ? reset($state) : $state;
                                    if (! $upload instanceof TemporaryUploadedFile) {
                                        return;
                                    }
                                    try {
                                        $icon = \App\Support\ProductCategoryIcons::installUploaded(
                                            $upload->getRealPath(),
                                            $upload->getClientOriginalName(),
                                        );
                                        $set('icon', $icon);
                                        Notification::make()->success()->title('Іконку додано до колекції')->send();
                                    } catch (\Illuminate\Validation\ValidationException $exception) {
                                        Notification::make()->danger()->title('Не вдалося додати SVG')
                                            ->body(collect($exception->errors())->flatten()->first() ?? 'Некоректний SVG-файл.')
                                            ->send();
                                    } catch (\Throwable $exception) {
                                        report($exception);
                                        Notification::make()->danger()->title('Не вдалося додати SVG')->body($exception->getMessage())->send();
                                    } finally {
                                        $set('category_icon_upload', null);
                                    }
                                })
                                ->columnSpan(9),
                            Forms\Components\Section::make('Додати SVG-кодом')
                                ->description('Вставте повний код, починаючи з <svg ...>. Код буде перевірено та збережено як файл колекції.')
                                ->schema([
                                    TextInput::make('category_icon_name')
                                        ->label('Назва іконки')
                                        ->placeholder('Наприклад: хачапурі')
                                        ->maxLength(80)
                                        ->dehydrated(false),
                                    Textarea::make('category_icon_svg')
                                        ->label('SVG код')
                                        ->rows(12)
                                        ->autosize()
                                        ->placeholder('<svg viewBox="0 0 24 24">...</svg>')
                                        ->helperText('Колірні значення fill/stroke будуть перетворені на currentColor.')
                                        ->live(debounce: 500)
                                        ->dehydrated(false)
                                        ->columnSpanFull(),
                                    Placeholder::make('category_icon_svg_preview')
                                        ->label('Попередній перегляд')
                                        ->content(function (Forms\Get $get): \Illuminate\Support\HtmlString {
                                            $code = trim((string) $get('category_icon_svg'));
                                            if ($code === '') {
                                                return new \Illuminate\Support\HtmlString('<span class="text-sm text-gray-500">Вставте SVG-код для перегляду.</span>');
                                            }
                                            $svg = \App\Support\ProductCategoryIcons::preview($code);
                                            if ($svg === null) {
                                                return new \Illuminate\Support\HtmlString('<span class="text-sm text-danger-600">SVG містить помилку або небезпечні елементи.</span>');
                                            }
                                            $color = (string) $get('icon_color');
                                            $color = preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : '#f4511e';
                                            return new \Illuminate\Support\HtmlString('<div style="color:' . e($color) . ';width:64px;height:64px">' . $svg . '</div>');
                                        })
                                        ->columnSpanFull(),
                                    Forms\Components\Actions::make([
                                        Forms\Components\Actions\Action::make('add_category_icon_svg')
                                            ->label('Додати SVG до колекції')
                                            ->icon('heroicon-o-plus')
                                            ->color('primary')
                                            ->action(function (Forms\Get $get, Forms\Set $set): void {
                                                try {
                                                    $name = trim((string) $get('category_icon_name'));
                                                    $code = trim((string) $get('category_icon_svg'));
                                                    if ($name === '' || $code === '') {
                                                        throw \Illuminate\Validation\ValidationException::withMessages([
                                                            'category_icon_svg' => 'Вкажіть назву та вставте SVG-код.',
                                                        ]);
                                                    }
                                                    $icon = \App\Support\ProductCategoryIcons::installCode($code, $name);
                                                    $set('icon', $icon);
                                                    $set('category_icon_name', null);
                                                    $set('category_icon_svg', null);
                                                    Notification::make()->success()->title('SVG додано до колекції')->send();
                                                } catch (\Illuminate\Validation\ValidationException $exception) {
                                                    Notification::make()->danger()->title('Не вдалося додати SVG')
                                                        ->body(collect($exception->errors())->flatten()->first() ?? 'Некоректний SVG-код.')
                                                        ->send();
                                                } catch (\Throwable $exception) {
                                                    report($exception);
                                                    Notification::make()->danger()->title('Не вдалося додати SVG')->body($exception->getMessage())->send();
                                                }
                                            }),
                                    ])->columnSpanFull(),
                                ])
                                ->columns(2)
                                ->collapsible()
                                ->collapsed()
                                ->columnSpanFull(),
                        ])->columnSpanFull(),

                        Forms\Components\Toggle::make('is_visible')
                            ->label(__('category.fields.is_visible'))
                            ->default(true),

                        Forms\Components\Toggle::make('is_main_group')
                            ->label(__('category.fields.is_main_group'))
                            ->helperText(__('category.fields.is_main_group_help')),
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
                Tables\Columns\ViewColumn::make('icon')
                    ->label('Іконка')
                    ->view('filament.tables.columns.product-category-icon'),
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
                        return $record->parent ? $record->parent->getTranslation('title', $locale) : '—';
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label(__('category.columns.is_visible'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_main_group')
                    ->label(__('category.columns.is_main_group'))
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('category.columns.updated_at'))
                    ->date()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->url(fn (ProductCategory $record): string => static::getUrl('edit', [
                        'record' => $record,
                        'source' => 'categories',
                    ])),
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
