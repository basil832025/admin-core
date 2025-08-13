<?php

namespace App\Filament\Clusters\Products\Resources;
use Filament\Forms\Components\Placeholder;

use App\Filament\Clusters\Products;
use App\Filament\Clusters\Products\Resources\ProductResource\Pages;
use App\Filament\Clusters\Products\Resources\ProductResource\RelationManagers;
use App\Models\Language;
use App\Models\Shop\Product;
use App\Models\Setting;
use App\Models\Shop\ProductCategory;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Concerns\Translatable;
use SolutionForest\FilamentTranslateField\Forms\Component\Translate;
use Illuminate\Support\Str;
use  App\Models\Shop\ProductImage;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Field;
//use Filament\Forms\Components\Field | Group;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Component;
use App\Models\Shop\Characteristic;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Get;
class ProductResource extends Resource
{
    use Translatable;
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Товары';
    protected static ?string $modelLabel = 'Товары';
    protected static ?string $pluralModelLabel = 'Товары';
    protected static ?string $cluster = Products::class;
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
    protected static ?int $navigationSort = 1;
    public static function form(Form $form): Form
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        // список активных языков из таблицы languages
        $locales = static::getActiveLocales();
        return $form->schema([
            Tabs::make()
                ->columns(1)
                ->tabs([
                    Tab::make('Основные')->schema(static::mainTab($locales, $defaultLocale))->columns(3),
                    Tab::make('Изображения')->schema(static::imageTab())->columns(1),
                    Tab::make('Характеристики')->schema(fn (Forms\Get $get, ?\App\Models\Shop\Product $record) => static::characteristicTab($get, $record))->columns(1),
                    Tab::make('Вариации')->schema(fn (Forms\Get $get, ?\App\Models\Shop\Product $record) => static::variationTab($get, $record))->columns(1),
                    Tab::make('SEO')->schema(static::seoTab($locales, $defaultLocale))->columns(1),
                ])->columns(1)->columnSpanFull(),
        ]);
    }
    public static function variationTab(Forms\Get $get, ?\App\Models\Shop\Product $record): array
    {
        $categoryIds = $get('category_ids') ?? $record?->categories?->pluck('id')->toArray() ?? [];

        if (empty($categoryIds)) {
            return [
                Placeholder::make('no_category')
                    ->content('Сначала выберите категорию'),
            ];
        }

        $variations = \App\Models\Shop\CategoryVariation::getVariationsFromManyCategories($categoryIds);

        // Загружаем ранее выбранные вариации с ценами
        $selected = collect($record?->productVariations ?? [])
            ->mapWithKeys(fn ($pv) => [$pv->variation_id => $pv->price]);
        //dd($selected);
        return [
            Group::make(
                $variations->map(function ($var) use ($selected) {
                    return Grid::make()
                        ->schema([
                            Checkbox::make("variation_flags.{$var->id}")
                                ->label($var->name)
                                ->reactive()
                                ->columns(1)
                                //->default(fn () => $selected->has($var->id)) // для новых записей
                                ->afterStateHydrated(function (Forms\Set $set) use ($selected, $var) {
                                  //  dump($selected);
                                    if ($selected->has($var->id)) {
                                        $set("variation_flags.{$var->id}", true);
                                        $set("variation_prices.{$var->id}", $selected->get($var->id));
                                    }else{
                                       if ($selected->isEmpty()) {
                                           $set("variation_flags.{$var->id}", true);
                                       }
                                    }
                                }),
                             //  ->default(fn () => $selected->has($var->id)), // ✅ сразу проставит
                               // ->default($selected->has($var->id)), // ✅ вот это работает с коллекцией

                            TextInput::make("variation_prices.{$var->id}")
                                ->numeric()
                                ->label(false)
                                ->placeholder('Цена')
                                ->dehydrated(true)
                                ->inputMode('decimal')
                                ->step('any')
                                ->columnSpan(1)
                                ->columns(1)
                                ->default($selected->get($var->id)) // безопасный доступ
                            //    ->visible(fn (Get $get) => $get("variation_flags.{$var->id}") === true)
                            ,
                        ])
                        ->columns(3);
                })->toArray()
            )
                ->columns(3)
                ->label('Вариации')
                ->reactive()
        ];
    }

    protected static function mainTab($locales, $defaultLocale): array
    {
        return [
            Forms\Components\Group::make()
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
                ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),

            RichEditor::make('description')
                ->label('Описание'),
            ]),


                    Forms\Components\Section::make('Цены')
                        ->schema([

            TextInput::make('price')->label('Цена')->numeric()->required(),
            TextInput::make('old_price')->label('Старая цена')->numeric()->nullable(),
            ]) ->columns(2),
                    Forms\Components\Section::make('Склад')
                        ->schema([
                            TextInput::make('sku')->label('Артикул'),
                            TextInput::make('quantity')->label('Остаток')->numeric()->default(0),
                            ])->columns(2),



                ]) ->columnSpan(['lg' => 2]),
            Forms\Components\Group::make()
                ->schema([
                    Forms\Components\Section::make('Статусы')
                        ->schema([
                            TextInput::make('slug')
                                ->label('Slug')
                                ->disabled()
                                ->dehydrated()
                                ->required()
                                ->maxLength(255)
                                ->unique(Product::class, 'slug', ignoreRecord: true),
                            Toggle::make('in_stock')->label('Есть в наличии')->default(true),
                            ]),

                    Forms\Components\Section::make('Ассоциации')
                        ->schema([
                            Select::make('categories')
                                ->label('Категории')
                                ->relationship('categories', 'title')
                               ->required()
                                ->options(function () use ($defaultLocale) {
                                    return ProductCategory::query()
                                        ->where('is_visible', 1)
                                        ->get()
                                        ->mapWithKeys(fn($cat) => [
                                            $cat->id => (
                                                json_decode($cat->getRawOriginal('title'), true)[$defaultLocale]
                                                ?? json_decode($cat->getRawOriginal('title'), true)[config('app.locale')]
                                            ),
                                        ])
                                        ->toArray();
                                })
                                ->multiple()
                                ->searchable()
                                ->preload(),
                            ])
                    ])->columnSpan(['lg' => 1]),

        ];
    }

    protected static function imageTab(): array
    {
        return [
            FileUpload::make('images')
                ->label('Галерея')
                ->multiple()
                ->image()
                ->reorderable()
                ->preserveFilenames()
                ->directory('products')
                ->disk('public') // или другое хранилище
                ->storeFiles()   // <-- обязательно!
                ->imageEditor()
                ->default(fn ($record) => $record?->images->pluck('path')->toArray())
                ->formatStateUsing(function ($state, $record) {
                    if (!$record) return [];

                    return $record->images->pluck('path')->toArray();
                })
              /*  ->dehydrateStateUsing(function ($state) {
                    // Мы не хотим, чтобы Filament сам сохранял, т.к. мы это делаем вручную в handleRecordUpdate
                    return null;
                })*/
               // ->live() // важно, чтобы сохранить live-поведение
                ->dehydrated() // ⬅ важно! чтобы Filament не пытался писать в поле "images"
                ->storeFiles(false), // ⬅ чтобы не сохранял сам
        ];
    }

    public static function characteristicTab(Forms\Get $get, ?\App\Models\Shop\Product $record): array
    {
        $category = $record?->categories?->first() ?? \App\Models\Shop\ProductCategory::find($get('category_id'));

        if (!$category) {
            return [
                Forms\Components\Placeholder::make('no_category')
                    ->label('No category')
                    ->content('Сначала выберите категорию'),
            ];
        }

        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        $characteristics = $category->getAllCharacteristicsWithInheritance();
      //  dd($characteristics);
        return $characteristics
            ->map(fn($char) => self::buildCharacteristicField($char, $defaultLocale, $record))
            ->toArray();
    }
// где-то внизу файла ProductResource.php
    public static function buildCharacteristicField(Characteristic $char, string $locale, ?Product $record = null): Component
    {
      //  dd($char);
        $fieldType = match ($char->field_type) {
            'text', 'number', 'decimal' => TextInput::class,
            'textarea' => Textarea::class,
            'toggle' => Toggle::class,
            'select' => Select::class,
            'radio' => Radio::class,
            'multiselect' => MultiSelect::class,
            'checkbox' => CheckboxList::class,
            'color' => ColorPicker::class,
            'file' => FileUpload::class,
            'datetime' => DateTimePicker::class,
            default => TextInput::class,
        };

        // Основное поле
        $field = $field_select = $fieldType::make("characteristics.{$char->id}")
            ->label($char->getTranslation('name', $locale) ?? '—')
            ->required((bool) ($char->pivot?->is_required));

        // Настройка числовых полей
        if (in_array($char->field_type, ['number', 'decimal'])) {
            $field = $field->numeric()->inputMode('decimal')->step($char->field_type === 'decimal' ? 'any' : '1');
        }

        // Выбор значений (select, checkbox и т.п.)
        if (in_array($char->field_type, ['select', 'radio', 'multiselect', 'checkbox'])) {
            $options = $char->values->mapWithKeys(function ($item) use ($locale) {
                $raw = $item->getAttributes()['value'];
                $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                return [$item->id => $decoded[$locale] ?? reset($decoded)];
            });
          //  dd($options);
            $field = $field->options($options);
        }

        // Установка сохраненного значения
        // Значение при редактировании
        $field = $field->afterStateHydrated(function (Component $component) use ($char, $record) {
            if (!$record) return;

            $entries = $record->characteristicValues
                ->where('characteristic_id', $char->id);
         //   $entry = $entries->first();
            $entry = \App\Models\Shop\ProductCharacteristicValue::where('product_id', $record->id)
                ->where('characteristic_id', $char->id)
                ->first();

            //  dd($entry?->value_datetime);
            $value = match ($char->field_type) {
                'text', 'textarea','color'    => $entry?->value_text,
                'number', 'decimal'    => $entry?->value_number,
                'toggle' => (bool) $entry?->value_text,
                'datetime'             => optional($entry?->value_datetime)?->format('Y-m-d H:i:s'),
                'select', 'radio'      => $entry?->characteristic_value_id,
                'file' => $entry && $entry->value_text
                    ? [$entry->value_text] // ← в массив
                    : [],
                'multiselect', 'checkbox' => $entries
                    ->pluck('id') // не id, а characteristic_value_id!
                    ->filter()
                    ->values()
                    ->toArray(),
                default => null,
            };
            $component->state($value);
        });

        // 💵 Доп. поля для цен на каждое значение (если pricing_type влияет)
        if (in_array($char->field_type, ['checkbox', 'multiselect']) && in_array($char->pricing_type, [1, 2])) {


         //   dd($entries);
           // dump($char->values);
            $is_new=true;
            // пройдем массви по значениям чтобы понять это первый разли заполняются характеристики, чтобы потом можно было птички проставить
            $items_t = $char->values->map(function ($value) use (&$is_new,$char, $locale, $record) {
                $id = $value->id; // 👈 теперь переменная $id существует
                $entries = $record->characteristicValues
                    // ->where('characteristic_id', $char->id)
                    ->filter(fn($item) => $item->pivot->characteristic_id === $char->id &&
                        $item->pivot->product_id === $record->id &&
                        $item->pivot->characteristic_value_id === $id
                    )
                    ->map(fn($item) => [
                        'characteristic_value_id' => $item->pivot->characteristic_value_id,
                        'price_modifier' => $item->pivot->price_modifier,
                    ])
                    ->filter()
                    ->values()
                    ->toArray();
                if (!empty($entries)) $is_new=false;
           //     dump($entries);
            });
          //  dump($is_new);
            $items = $char->values->map(function ($value) use (&$is_new,$char, $locale, $record) {
                $id = $value->id;

                $raw = $value->getAttributes()['value'];
                $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                $name = $decoded[$locale] ?? reset($decoded);

             /*   $isChecked = $entries
                    ->first(fn($v) => $v->characteristic_value_id == $id && $v->characteristic_id == $char->id);*/
             //dd();
                $entries = $record->characteristicValues
                    // ->where('characteristic_id', $char->id)
                    ->filter(fn($item) => $item->pivot->characteristic_id === $char->id &&
                        $item->pivot->product_id === $record->id &&
                        $item->pivot->characteristic_value_id === $id
                    )
                    ->map(fn($item) => [
                        'characteristic_value_id' => $item->pivot->characteristic_value_id,
                        'price_modifier' => $item->pivot->price_modifier,
                    ])
                    ->filter()
                    ->values()
                    ->toArray();
              //  dump($entries);
                $isChecked=false;
                $price=0;
              //  if ($id==11)                dd($char->id,$entries,$id,$record->id);
                 if (!empty($entries) && !empty($entries[0]['characteristic_value_id'])){
                     $isChecked = true;
                     $price = $entries[0]['price_modifier'];
                    // dd($isChecked,$price,$id);
                 }

                $valueName = $value->getTranslation('value', $locale);
                return Grid::make(12)->schema([
                    Checkbox::make("characteristics.{$char->id}.{$id}")
                        ->label($name)
                      //  ->dehydrated(true)
                     // ->afterStateHydrated(fn ($component) => $component->state((bool) $isChecked))
                      ->afterStateHydrated(function ($component) use ($isChecked,$is_new) {
                       //   dump($is_new);
                             if ($is_new==true){

                                 $component->state(true);
                             }

                             else
                                 $component->state((bool) $isChecked);
                      })
                        ->columnSpan(3),

                  //  TextInput::make("price_modifiers.{$char->id}.{$id}")
                    TextInput::make("characteristics_price.{$char->id}.{$id}")
                        ->placeholder("Ціна: {$valueName}")
                        ->afterStateHydrated(fn ($component, $state) => $component->state($price))
                        ->numeric()
                        ->label(false)
                        ->dehydrated(true)
                        ->inputMode('decimal')
                        ->step('any')
                        ->columnSpan(3),
                ]); // ⬅️ Обязательно;
            });

            return Fieldset::make($char->getTranslation('name', $locale) ?? '—')
                ->schema([Group::make($items->toArray())
                ->columnSpanFull()]);
        }
//->dehydrated(true)
        return $field;
    }


    protected static function seoTab($locales, $defaultLocale): array
    {
        return [
            Translate::make()
                ->locales($locales)
                ->prefixLocaleLabel()
                ->columns(1)
                ->columnSpanFull()
                ->schema(fn(string $locale) => [
            TextInput::make('seo_title')
                ->label('SEO-заголовок')
                ->maxLength(255),

            TextInput::make('seo_description')
                ->label('SEO-описание')
                ->maxLength(255),

            TextInput::make('seo_keywords')
                ->label('SEO-ключевые слова')
                ->maxLength(255),
                    ])
        ];
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')->label('SKU')->sortable(),
                Tables\Columns\TextColumn::make('title')->label('Название')->sortable()->searchable()->limit(30),
                Tables\Columns\TextColumn::make('price')->label('Цена')->money('UAH'),
                Tables\Columns\IconColumn::make('in_stock')->label('В наличии')->boolean(),
                Tables\Columns\TextColumn::make('quantity')->label('Остаток'),
                Tables\Columns\TextColumn::make('updated_at')->label('Обновлено')->dateTime('d.m.Y H:i'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->filters([
                //
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
    protected static function getActiveLocales(): array
    {
        return Language::where('active', true)
            ->orderBy('position')
            ->pluck('code')
            ->map(fn($c) => strtolower($c))
            ->toArray();
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
