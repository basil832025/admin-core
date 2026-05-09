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
                            ->required(),

                        TextInput::make('old_price')
                            ->label('Старая цена')
                            ->numeric()->inputMode('decimal')->step('any'),
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
                            ->unique(ignoreRecord: true)
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

        // размер пирогов
        $sizeCharId = Characteristic::query()->where('slug', 'rozmir-pirogiv')->value('id'); // ← поменяй slug на свой

        $locales = static::getActiveLocales();
        return $table
            ->reorderable('sort')
            ->defaultSort('sort', 'asc')
            ->recordTitleAttribute('title')
            ->columns([
                TextInputColumn::make('sort')
                    ->label('Сорт')
                    ->type('number')
                    ->step('1')
                    ->rules(['integer', 'min:0'])
                    ->alignRight()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable(),
                TextColumn::make('title')->label('Название')->sortable()->searchable()
                    ->getStateUsing(function (\App\Models\Shop\Product $record, TextColumn $column, $livewire) use ($defaultLocale) {
                        if (!empty($record->short_name)) {
                            return $record->short_name;
                        }
                        //  dd($lang);
                        return $record->getTranslation('title', $defaultLocale);

                    }),
               // Tables\Columns\TextColumn::make('price')->label('Цена')->numeric(2),
                TextInputColumn::make('price')
                    ->type('number')   // HTML5 number
                    ->step('1')
                    ->rules(['numeric','min:0']) // валидация на сохранение
                    ->alignRight()
                    ->label(__('product.columns.price'))
                    ->sortable(),
                TextInputColumn::make('old_price')
                    ->type('number')   // HTML5 number
                    ->step('1')
                    ->rules(['numeric','min:0']) // валидация на сохранение
                    ->alignRight()
                    ->label('Старая цена')
                    ->sortable(),
                Tables\Columns\TextColumn::make('discount_percent')
                    ->label('Скидка %')
                    ->getStateUsing(function (\App\Models\Shop\Product $record) {
                        $oldPrice = $record->old_price ?? null;
                        $price = $record->price ?? 0;
                        
                        if (!$oldPrice || $oldPrice <= 0 || $price <= 0 || $oldPrice <= $price) {
                            return 0;
                        }
                        
                        return round((($oldPrice - $price) / $oldPrice) * 100);
                    })
                    ->formatStateUsing(fn ($state) => $state > 0 ? "–{$state}%" : '0%')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                    ->alignRight()
                    ->sortable(query: function ($query, string $direction) {
                        // Сортировка по вычисленному проценту скидки
                        return $query->orderByRaw("
                            CASE 
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
                    ->getStateUsing(function (\App\Models\Shop\Product $record) use ($sizeCharId, $defaultLocale) {
                        if (!$sizeCharId) return null;
                        return $record->characteristicValues
                            ->where('pivot.characteristic_id', $sizeCharId)
                            ->map(fn ($v) => $v->getTranslation('value', $defaultLocale)) // или свой способ получения текста
                            ->filter()
                            ->implode(', ');
                    })
                    ->toggleable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('updated_at')->label('Обновлено')->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Добавить вариант')
                    ->mutateFormDataUsing(function (array $data): array {
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

                        return $data;
                    })
                    ->after(function (\App\Models\Shop\Product $record) {
                        \App\Filament\Clusters\Products\Resources\ProductResource::syncCharacteristicsFromForm($record, $this->charsPayload);
                    }),
            ])
            ->actions([
                // в v4 — используем Actions\EditAction
                EditAction::make() ->mutateFormDataUsing(function (array $data): array {
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
