<?php

namespace App\Filament\Clusters\Products\Resources\ProductResource\RelationManagers;

use App\Models\Language;
use App\Models\Setting;
use App\Models\Shop\Characteristic;
use App\Models\Shop\Product;
use App\Models\Shop\ProductCategory;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;

use Filament\Forms\Get;                               // +++ правильные импорты
use Filament\Forms\Set;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;

use Filament\Tables;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Filament\Actions;
use SolutionForest\FilamentTranslateField\Forms\Component\Translate;


class VariantsRelationManager extends RelationManager
{
    /** hasMany self: Product::class, 'parent_id' */
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Варианты';
    protected static ?string $navigationIcon  = 'heroicon-m-rectangle-stack';
    /** тут будем временно хранить payload характеристик между mutateFormDataUsing() и after() */
    protected array $charsPayload = [];
    /** Показывать менеджер только у «родительских» товаров */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return is_null($ownerRecord->parent_id);
    }

    /** В v4 вместо form() используется schema() */
    public  function form(Form $form): Form
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        // список активных языков из таблицы languages
        $locales = static::getActiveLocales();
        return $form
            ->columns(12)
            ->components([
                // +++ АГРЕГАТОР состояния для всех динамических характеристик
                Hidden::make('characteristics_payload')
                    ->default(fn ($state) => is_array($state) ? $state : [])
                    ->dehydrated(),
                Section::make('Название и Slug')
                    ->columns(12)
                    ->schema([
                        // Группа для переводимого title (укладываем в JSON 'title')
                        Group::make()
                            //->statePath('title')      // => ['uk'=>..., 'en'=>..., 'ru'=>...]
                            ->columns(3)
                            ->schema([

                                Translate::make()
                                    ->locales($locales)
                                    ->prefixLocaleLabel()
                                    ->columns(1)
                                    ->columnSpanFull()
                                    ->schema(fn(string $locale) => [
                                        TextInput::make('title')
                                            ->label('Название')
                                            ->required($locale === $defaultLocale)
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            // +++ правильная сигнатура для v3
                                            ->afterStateUpdated(function ($state, Set $set, Get $get, ?string $operation) use ($defaultLocale) {
                                                if ($operation === 'create' && blank($get('slug'))) {
                                                    $set('slug', Str::slug((string) $state));
                                                }
                                            }),

                                           // ->afterStateUpdated(fn (string $operation, $state, \Filament\Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null)

                                ]),
                                TextInput::make('short_name')
                                    ->label('Короткое название')
                                    // ->disabled()
                                    ->dehydrated()
                                    //   ->required()
                                    ->maxLength(255)
                                    ->unique(Product::class, 'short_name', ignoreRecord: true),
                                ]) ->columns(1)
                            ->columnSpan(9),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->helperText('Оставьте пустым — сгенерируется автоматически.')
                            ->maxLength(255)
                            ->unique(\App\Models\Shop\Product::class, 'slug', ignoreRecord: true)
                            ->columnSpan(3),
                    ])->columnSpanFull(),

                Section::make('Цены')
                    ->columns(2)
                    ->schema([
                        TextInput::make('price')
                            ->label('Цена')
                            ->numeric()->inputMode('decimal')->step('any')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Set $set, Get $get) => \App\Filament\Clusters\Products\Resources\ProductResource::syncDiscountPercentField($set, $get))
                            ->required(),

                        TextInput::make('old_price')
                            ->label('Старая цена')
                            ->numeric()->inputMode('decimal')->step('any')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Set $set, Get $get) => \App\Filament\Clusters\Products\Resources\ProductResource::syncDiscountPercentField($set, $get)),

                        TextInput::make('manual_discount_percent')
                            ->label('Скидка %')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(99.99)
                            ->step(1)
                            ->live(onBlur: true)
                            ->afterStateHydrated(function (TextInput $component, $state, ?Product $record): void {
                                $component->state($state !== null && $state !== '' ? round(\App\Filament\Clusters\Products\Resources\ProductResource::normalizeDecimal($state)) : null);
                            })
                            ->afterStateUpdated(fn ($state, Set $set, Get $get) => \App\Filament\Clusters\Products\Resources\ProductResource::applyDiscountPercentToPrices($set, $get, $state)),

                        TextInput::make('variant_display_sort')
                            ->label('Сортування в картці')
                            ->numeric()
                            ->helperText('Порядок відображення варіантів у картці товару.'),
                    ])->columnSpanFull(),

                // --- Характеристики только с is_main_tab = 1 ---
                Section::make('Свойства товара')
                    ->schema(function (?Product $record) {
                        $owner  = $this->getOwnerRecord();         // родитель
                        $locale = config('app.locale');
                        //  dump($owner->category_id);
                        // берём только is_main_tab = 1
                        $category = ProductCategory::find($owner->category_id);
                        // dump($category);
                        $chars = $category->getAllCharacteristicsWithInheritance(true);
                        //   dump($chars);
                        // при создании варианта $record ещё null → заглушка
                        $product = $record ?? new Product([
                                'parent_id'   => $owner->getKey(),
                                'category_id' => $owner->category_id,
                            ]);
                        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
                        // Используй твой билдер полей
                        return $chars->map(fn ($char) =>
                        \App\Filament\Clusters\Products\Resources\ProductResource::buildCharacteristicField(
                            $char,
                            $defaultLocale  ,
                            $product
                        )
                        )->values()->all();
                    })
                    ->columnSpanFull(),
                Section::make('Склад')
                    ->schema([
                        TextInput::make('sku')
                            ->label('Артикул')
                            ->maxLength(64)
                            ->default(fn (string $operation): ?int => $operation === 'create' ? \App\Filament\Clusters\Products\Resources\ProductResource::nextAvailableSku() : null)
                            ->helperText(fn (): string => 'Следующий свободный артикул: ' . \App\Filament\Clusters\Products\Resources\ProductResource::nextAvailableSku())
                            ->rules([
                                fn (?Product $record): \Closure => \App\Filament\Clusters\Products\Resources\ProductResource::uniqueSkuRule($record),
                            ])
                            ->columnSpan(3),
                         TextInput::make('quantity')
                             ->label('Кол-во')
                             ->numeric()->minValue(0)->default(0)
                             ->columnSpan(3),
                    ])->columnSpanFull(),
                Section::make('Дополнительно')
                    ->schema([
                        RichEditor::make('dop_info')
                            ->label('Калькуляция/Доп. информация')
                            // ТУТ ВЫВОДИМ ХАРАКТЕРИСТИКИ is_main_tab
                            ->columnSpanFull(),
                    ])->columnSpanFull(),


            ]);
    }
    protected static function getActiveLocales(): array
    {
        return Language::where('active', true)
            ->orderBy('position')
            ->pluck('code')
            ->map(fn($c) => strtolower($c))
            ->toArray();
    }
    public function table(Table $table): Table
    {    $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');

        $sizeCharIds = Characteristic::query()
            ->whereIn('slug', ['rozmir-pirogiv', 'rozmiri-insi'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $resolveSizeLabel = function (Product $record) use ($defaultLocale, $sizeCharIds): ?string {
            if (empty($sizeCharIds)) {
                return null;
            }

            $size = $record->characteristicValues
                ->filter(fn ($value) => in_array((int) ($value->pivot->characteristic_id ?? 0), $sizeCharIds, true))
                ->map(fn ($value) => $value->getTranslation('value', $defaultLocale))
                ->filter()
                ->implode(', ');

            if ($size === '') {
                return null;
            }

            $size = str_replace(' ', '', $size);
            $size = str_replace('.', ',', $size);

            return $size;
        };

        $locales = static::getActiveLocales();
        return $table
            ->query(function () {
                $owner = $this->getOwnerRecord();

                return Product::query()
                    ->where(function ($query) use ($owner): void {
                        $query
                            ->whereKey($owner->getKey())
                            ->orWhere('parent_id', $owner->getKey());
                    });
            })
            ->modifyQueryUsing(function ($query) {
                return $query
                    ->orderByRaw('CASE WHEN variant_display_sort IS NULL THEN 1 ELSE 0 END asc')
                    ->orderBy('variant_display_sort')
                    ->orderBy('sort')
                    ->orderBy('id');
            })
            ->reorderable('variant_display_sort')
            ->defaultSort('variant_display_sort', 'asc')
            ->recordTitleAttribute('title')
            ->columns([
                TextInputColumn::make('variant_display_sort')
                    ->label('Сорт в картці')
                    ->type('number')
                    ->step('1')
                    ->rules(['nullable', 'integer', 'min:0'])
                    ->alignRight()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable(),
                Tables\Columns\TextColumn::make('variant_kind')
                    ->label('Тип')
                    ->getStateUsing(fn (Product $record): string => $record->parent_id === null ? 'Родитель' : 'Вариант')
                    ->badge()
                    ->color(fn (Product $record): string => $record->parent_id === null ? 'warning' : 'gray'),
                TextColumn::make('title')->label('Название')->sortable()->searchable()
                    ->getStateUsing(function (\App\Models\Shop\Product $record, TextColumn $column, $livewire) use ($resolveSizeLabel, $defaultLocale) {
                        $size = $resolveSizeLabel($record);

                        if (!empty($record->short_name)) {
                            return $record->parent_id === null && $size
                                ? $record->short_name . ' [' . $size . ']'
                                : $record->short_name;
                        }

                        $title = $record->getTranslation('title', $defaultLocale);

                        return $record->parent_id === null && $size
                            ? $title . ' [' . $size . ']'
                            : $title;

                    }),
               // Tables\Columns\TextColumn::make('price')->label('Цена')->numeric(2),
                TextInputColumn::make('price')
                    ->type('number')
                    ->step('1')
                    ->rules(['numeric','min:0'])
                    ->alignRight()
                    ->label(__('product.columns.price'))
                    ->updateStateUsing(function (Product $record, $state, $livewire): float {
                        $record->price = (float) $state;
                        $record->manual_discount_percent = null;
                        $record->save();

                        if (is_object($livewire) && method_exists($livewire, 'dispatch')) {
                            $livewire->dispatch('$refresh');
                        }

                        return (float) $record->price;
                    })
                    ->sortable(),
                TextInputColumn::make('old_price')
                    ->type('number')
                    ->step('1')
                    ->rules(['nullable', 'numeric', 'min:0'])
                    ->alignRight()
                    ->label('Старая цена')
                    ->updateStateUsing(function (Product $record, $state, $livewire): ?float {
                        $record->old_price = $state === null || $state === '' ? null : (float) $state;
                        $record->manual_discount_percent = null;
                        $record->save();

                        if (is_object($livewire) && method_exists($livewire, 'dispatch')) {
                            $livewire->dispatch('$refresh');
                        }

                        return $record->old_price !== null ? (float) $record->old_price : null;
                    })
                    ->sortable(),
                TextInputColumn::make('discount_percent')
                    ->type('number')
                    ->step('1')
                    ->rules(['nullable', 'numeric', 'min:0', 'max:99.99'])
                    ->label('Скидка %')
                    ->getStateUsing(function (Product $record): ?float {
                        if ($record->manual_discount_percent !== null) {
                            return round((float) $record->manual_discount_percent);
                        }

                        return \App\Filament\Clusters\Products\Resources\ProductResource::calculatedDiscountPercent($record->old_price, $record->price);
                    })
                    ->updateStateUsing(function (Product $record, $state, $livewire): ?float {
                        $discountPercent = \App\Filament\Clusters\Products\Resources\ProductResource::normalizeDecimal($state);

                        if ($discountPercent <= 0) {
                            $record->manual_discount_percent = null;
                            $record->save();
                            app(\App\Services\CatalogCacheService::class)->bump();

                            if (is_object($livewire) && method_exists($livewire, 'dispatch')) {
                                $livewire->dispatch('$refresh');
                            }

                            return null;
                        }

                        $currentPrice = (float) ($record->price ?? 0);
                        $existingOldPrice = (float) ($record->old_price ?? 0);
                        $hasExistingOldPrice = $existingOldPrice > 0 && $existingOldPrice > $currentPrice;
                        $basePrice = $hasExistingOldPrice ? $existingOldPrice : $currentPrice;

                        if ($basePrice <= 0) {
                            return null;
                        }

                        if (! $hasExistingOldPrice) {
                            $record->old_price = round($basePrice);
                        }

                        $record->manual_discount_percent = round($discountPercent);
                        $record->price = round($basePrice * (1 - ($discountPercent / 100)));
                        $record->save();

                        app(\App\Services\CatalogCacheService::class)->bump();

                        if (is_object($livewire) && method_exists($livewire, 'dispatch')) {
                            $livewire->dispatch('$refresh');
                        }

                        return round((float) $record->manual_discount_percent);
                    })
                    ->alignRight()
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderByRaw("
                            CASE
                                WHEN manual_discount_percent IS NOT NULL THEN manual_discount_percent
                                WHEN old_price > 0 AND price > 0 AND old_price > price
                                THEN ROUND(((old_price - price) / old_price) * 100)
                                ELSE 0
                            END {$direction}
                        ");
                    }),
                Tables\Columns\TextColumn::make('quantity')->label('Остаток'),
                // 👇 РОЗМІР ПИРОГІВ
                Tables\Columns\TextColumn::make('pie_size')
                    ->label('Розмір пирогів')
                    ->getStateUsing(function (\App\Models\Shop\Product $record) use ($resolveSizeLabel) {
                        return $resolveSizeLabel($record);
                    })
                    ->toggleable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('updated_at')->label('Обновлено')->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Добавить вариант')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data = \App\Filament\Clusters\Products\Resources\ProductResource::applyDiscountPercentToData($data);

                        // забираем характеристики из формы
                        $this->charsPayload = [
                            'values' => $data['characteristics'] ?? [],
                            'prices' => $data['characteristics_price'] ?? [],
                        ];
                        unset($data['characteristics'], $data['characteristics_price']);

                        $owner = $this->getOwnerRecord();
                        $data['parent_id']   = $owner->getKey();
                        $data['category_id'] = $owner->category_id;

                        if (! isset($data['sort']) || $data['sort'] === null || $data['sort'] === '') {
                            $maxSort = Product::query()
                                ->where('parent_id', $owner->getKey())
                                ->max('sort');
                            $data['sort'] = ((int) $maxSort) + 10;
                        }

                        if (! isset($data['variant_display_sort']) || $data['variant_display_sort'] === null || $data['variant_display_sort'] === '') {
                            $maxVariantDisplaySort = Product::query()
                                ->where(function ($query) use ($owner): void {
                                    $query->whereKey($owner->getKey())
                                        ->orWhere('parent_id', $owner->getKey());
                                })
                                ->max('variant_display_sort');
                            $data['variant_display_sort'] = ((int) $maxVariantDisplaySort) + 10;
                        }

                        return $data;
                    })
                    ->after(function (\App\Models\Shop\Product $record) {
                        \App\Filament\Clusters\Products\Resources\ProductResource::syncCharacteristicsFromForm($record, $this->charsPayload);
                    }),
            ])
            ->actions([
                // в v4 — используем Actions\EditAction
                EditAction::make() ->mutateFormDataUsing(function (array $data): array {
                    $data = \App\Filament\Clusters\Products\Resources\ProductResource::applyDiscountPercentToData($data);

                    $this->charsPayload = [
                        'values' => $data['characteristics'] ?? [],
                        'prices' => $data['characteristics_price'] ?? [],
                    ];
                    unset($data['characteristics'], $data['characteristics_price']);
                    return $data;
                })
                    ->after(function (\App\Models\Shop\Product $record) {
                        \App\Filament\Clusters\Products\Resources\ProductResource::syncCharacteristicsFromForm($record, $this->charsPayload);
                    }),

                // «Клонировать» — кастомным экшеном таблицы
                Action::make('clone')
                    ->label('Клонировать')
                    ->icon('heroicon-m-rectangle-stack')
                    ->visible(fn (Product $record): bool => $record->parent_id !== null)
                    ->requiresConfirmation()
                    ->action(function (Product $record) {
                        $copy = $record->replicate(['id','slug','created_at','updated_at']);
                        $copy->title     = ($record->title ?: $record->parent?->title).' (копия)';
                        $copy->slug      = null;          // сгенерируется хук/валидатором
                        $copy->parent_id = $record->parent_id;
                        $copy->save();

                        // при необходимости — скопируй связи:
                        // $copy->categories()->sync($record->categories()->pluck('id'));
                        // и пр.
                    }),

                DeleteAction::make()
                    ->visible(fn (Product $record): bool => $record->parent_id !== null)
                    ->disabled(fn (Product $record): bool => $record->hasDeleteDependencies())
                    ->tooltip(fn (Product $record): ?string => $record->hasDeleteDependencies()
                        ? $record->getDeleteDependencyMessage()
                        : null),
            ]);
    }
    // чтобы гидраторы в полях видели текущие значения
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record?->loadMissing('characteristicValues');
        return $data;
    }
}
