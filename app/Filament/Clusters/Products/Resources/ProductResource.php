<?php

namespace App\Filament\Clusters\Products\Resources;
use Filament\Forms\Components\DatePicker;
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
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Concerns\Translatable;
use Illuminate\Support\Arr;
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
use Filament\Forms\Set;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\ToggleColumn;


class ProductResource extends Resource
{
    use Translatable;
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel   = null;
    protected static ?string $modelLabel        = null ;
    protected static ?string $pluralModelLabel  = null;
    protected static ?string $cluster = Products::class;
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('product.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('product.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('product.nav.plural_model_label');
    }
    public static function form(Form $form): Form
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        // список активных языков из таблицы languages
        $locales = static::getActiveLocales();
        return $form->components([
           // Hidden::make('characteristics')->default([])->dehydrated(false),
            Tabs::make()
                ->columns(1)
                ->tabs([
                    Tab::make(__('product.tabs.main'))
                        ->schema(static::mainTab($locales, $defaultLocale))
                        ->columns(3),

                    Tab::make(__('product.tabs.images'))
                        ->schema(static::imageTab())
                        ->columns(1),

                    Tab::make(__('product.tabs.characteristics'))
                        ->schema(fn (Get $get, ?Product $record) => static::characteristicTab($get, $record))
                        ->columns(1),

                    Tab::make(__('product.tabs.seo'))
                        ->schema(static::seoTab($locales, $defaultLocale))
                        ->columns(1),

                    Tab::make(__('product.tabs.calculation'))
                        ->schema(fn (Get $get, ?Product $record) => static::calculationTab($get, $record))
                        ->columns(1),
                ])
                ->columns(1)
                ->columnSpanFull(),

        ]);
    }


    protected static function mainTab($locales, $defaultLocale): array
    {
        return [
            Group::make()
                ->schema([
                    Translate::make()
                        ->locales($locales)
                        ->prefixLocaleLabel()
                        ->columns(1)
                        ->columnSpanFull()
                        ->schema(fn(string $locale) => [
            TextInput::make('title')
                ->label(__('product.fields.title'))
                ->required($locale === $defaultLocale)
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (string $operation, $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null)
                ,

            RichEditor::make('description')
                ->label(__('product.fields.description')),
                ]),


                    Section::make(__('product.sections.prices'))
                        ->schema([
                            TextInput::make('price')->label(__('product.fields.price'))->numeric()->required(),
                            TextInput::make('old_price')->label(__('product.fields.old_price'))->numeric()->nullable(),

                        ]) ->columns(2),
                    Section::make(__('product.sections.stock'))
                        ->schema([
                            TextInput::make('sku')->label(__('product.fields.sku')),
                            TextInput::make('quantity')->label(__('product.fields.quantity'))->numeric()->default(0),
                        ])->columns(2),
                    Section::make(__('product.sections.extra'))
                        ->schema([
                            RichEditor::make('dop_info')->label(__('product.fields.dop_info'))->columnSpanFull(),
                            RichEditor::make('short_desc')->label(__('product.fields.short_desc'))->columnSpanFull()
                            // ТУТ ВЫВОДИМ ХАРАКТЕРИСТИКИ is_main_tab
                                ->columnSpanFull(),
                        ])->columns(1),
                    Section::make(__('product.sections.props'))
                        ->schema(function (Get $get, ?Product $record) {
                           // $locale = app()->getLocale();


                            $categoryId = $get('category_id') ?? $record?->category_id;
                         //   dd($categoryId);
                            if (!$categoryId) return [];
                            $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
                            $category = ProductCategory::find($categoryId);
                            if (!$category) return [];

                            // берём только is_main_tab = 1
                            $chars = $category->getAllCharacteristicsWithInheritance(true);
                          //  dd($chars);
                            return $chars
                                ->map(fn ($char) => static::buildCharacteristicField($char, $defaultLocale, $record))
                                ->values()
                                ->all();
                        })
                        ->columns(1)
                       ,
          ]) ->columnSpan(['lg' => 2]),
            Group::make()
                ->schema([
                    Section::make(__('product.sections.statuses'))
                        ->schema([
                            TextInput::make('short_name')
                                ->label(__('product.fields.short_name'))
                               // ->disabled()
                                ->dehydrated()
                             //   ->required()
                                ->maxLength(255)
                                ->unique(Product::class, 'short_name', ignoreRecord: true),
                            TextInput::make('code2')
                                ->label(__('product.fields.code2'))
                                // ->disabled()
                                ->dehydrated()
                                //   ->required()
                                ->maxLength(255)
                                ->unique(Product::class, 'code2', ignoreRecord: true),
                            TextInput::make('slug')
                                ->label(__('product.fields.slug'))
                                ->disabled()
                                ->dehydrated()
                                ->required()
                                ->maxLength(255)
                                ->unique(Product::class, 'slug', ignoreRecord: true),
                            Toggle::make('in_stock')->label(__('product.fields.in_stock'))->default(true),
                            Toggle::make('is_new')->label(__('product.fields.is_new'))->default(false),
                            Toggle::make('is_hit')->label(__('product.fields.is_hit'))->default(false),
                            Toggle::make('is_home')->label(__('product.fields.is_home'))->default(false),

                            ]),
                    Section::make(__('product.sections.associations'))
                        ->schema([
                            // 1) Главная категория
                            Select::make('category_id')
                                ->label(__('product.fields.category_id'))
                                ->required()
                                ->dehydrated(true)
                                ->live() // 👈 важно: чтобы ниже schema(fn $get) пересчитывалась
                                ->options(function () use ($defaultLocale) {
                                    return ProductCategory::query()
                                        ->where('is_visible', 1)
                                        ->get()
                                        ->mapWithKeys(function ($cat) use ($defaultLocale) {
                                            // если title закодирован JSON-ом
                                            $t = json_decode($cat->getRawOriginal('title'), true);
                                            $label = $t[$defaultLocale] ?? $t[config('app.locale')] ?? ($t['uk'] ?? $t['ru'] ?? $t['en'] ?? $cat->id);
                                            return [$cat->id => $label];
                                        })
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    $chars = \App\Models\Shop\ProductCategory::find($state)?->getAllCharacteristicsWithInheritance(false) ?? collect();

                                    // Инициализируем только те, что рисуем через CheckboxList на CREATE
                                    foreach ($chars as $char) {
                                        if ($char->field_type === 'multiselect' && ! in_array((int)($char->pricing_type ?? $char->pricing_type ?? 0), [1,2], true)) {
                                            $set("characteristics.char_{$char->id}", []);   // 👈 теперь это точно массив
                                        }
                                    }
                                    // гарантированно кладём значение в нужный ключ
                                    $set('category_id', $state);
                                    // schema() вкладки дернётся из-за ->live()
                                }),
                                // если это вариант — подставляем категорию родителя
                              //  ->default(fn (?Product $record) => $record?->category_id ?? $record?->parent?->category_id),

// 2) Доп. категории (pivot), НЕобязательные
                            Select::make('categories')
                                ->label(__('product.fields.categories'))
                                ->multiple()
                                ->relationship('categories', 'id') // пусть Filament синкает pivot
                                ->options(function (Get $get) use ($defaultLocale) {
                                    $primary = $get('category_id');

                                    return ProductCategory::query()
                                        ->where('is_visible', 1)
                                        ->when($primary, fn ($q) => $q->whereKeyNot($primary)) // не показываем главную
                                        ->get()
                                        ->mapWithKeys(function ($cat) use ($defaultLocale) {
                                            $t = json_decode($cat->getRawOriginal('title'), true);
                                            $label = $t[$defaultLocale] ?? $t[config('app.locale')] ?? ($t['uk'] ?? $t['ru'] ?? $t['en'] ?? $cat->id);
                                            return [$cat->id => $label];
                                        })
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->helperText(__('product.fields.categories_helper')),
                            Forms\Components\FileUpload::make('main_image')
                                ->label(__('product.fields.main_image'))
                                ->image()
                                ->directory('products/main') // картинки будут сохраняться сюда
                                ->maxSize(20048)              // ограничение по размеру (в Кб)
                                ->imageEditor()
                                ->disk('public')
                                ->required(false),
                            Forms\Components\FileUpload::make('main_image_small')
                                ->image()
                                ->disk('public')
                                ->directory('products/small')
                                ->preserveFilenames()
                                ->label(__('product.fields.main_image_small'))
                            ])

                    ])->columnSpan(['lg' => 1]),

        ];
    }
    protected static function calculationTab(Get $get, ?Product $record): array
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');

        return [
            Section::make(__('product.sections.calc_versions'))
                ->description('Склади, що діють з дати. Порожня «по дату» — безстроково.')
                ->schema([
                    Repeater::make('calculations')
                        ->relationship() // Product::calculations()
                        ->label(__('product.fields.calculations'))
                        ->defaultItems(0)
                        ->orderable(false)
                        ->columns(12)
                        ->schema([
                            TextInput::make('name')
                                ->label(__('product.fields.calc_name'))
                                ->columnSpan(4),

                            DatePicker::make('valid_from')
                                ->label(__('product.fields.valid_from'))
                                ->required()
                                ->default(today())
                                ->columnSpan(4),

                            DatePicker::make('valid_to')
                                ->label(__('product.fields.valid_to'))
                                ->helperText(__('product.fields.valid_to_helper'))
                                ->columnSpan(4),

                            Section::make(__('product.sections.calc_items'))
                                ->columnSpanFull()
                                ->schema([
                                    Repeater::make('items')
                                        ->relationship('items') // ProductCalculation->items()
                                        ->label(__('product.fields.calc_items'))
                                        ->defaultItems(0)
                                        ->columns(12)
                                        ->schema([
                                            Select::make('component_product_id')
                                                ->label(__('product.fields.component_product'))
                                                ->searchable()
                                                ->preload()

                                                ->options(function () use ($record, $defaultLocale) {
                                                    $currentId = $record?->id;

                                                    return \App\Models\Shop\Product::query()
                                                        ->when($currentId, fn($q) => $q->where('id', '!=', $currentId)) // без рекурсії
                                                        ->orderBy('id', 'desc')
                                                        ->get(['id', 'title', 'short_name'])
                                                        ->mapWithKeys(function ($p) use ($defaultLocale) {
                                                            $getTrans = function ($raw, $fallback = null) use ($defaultLocale) {
                                                                if (blank($raw)) return $fallback;
                                                                if (is_string($raw)) {
                                                                    $trim = ltrim($raw);
                                                                    if (strlen($trim) && ($trim[0] === '{' || $trim[0] === '[')) {
                                                                        $arr = json_decode($raw, true);
                                                                        if (json_last_error() === JSON_ERROR_NONE && is_array($arr)) {
                                                                            return $arr[$defaultLocale]
                                                                                ?? $arr[config('app.locale')]
                                                                                ?? $fallback;
                                                                        }
                                                                    }
                                                                    return $raw;
                                                                }
                                                                if (is_array($raw)) {
                                                                    return $raw[$defaultLocale]
                                                                        ?? $raw[config('app.locale')]
                                                                        ?? $fallback;
                                                                }
                                                                return $fallback;
                                                            };

                                                            $short = $getTrans($p->getRawOriginal('short_name'), $p->short_name);
                                                            $title = $getTrans($p->getRawOriginal('title'), $p->title);
                                                            $label = filled($short) ? $short : $title;

                                                            return [$p->id => $label];
                                                        })
                                                      /*  ->mapWithKeys(function ($p) use ($defaultLocale) {
                                                            // у тебя title — переводимое JSON-поле
                                                            $raw = json_decode($p->getRawOriginal('title'), true);
                                                            $label = $raw[$defaultLocale]
                                                                ?? $raw[config('app.locale')]
                                                                ?? ($raw['uk'] ?? $raw['ru'] ?? $raw['en'] ?? $p->sku ?? $p->id);
                                                            return [$p->id => $label];
                                                        })*/
                                                        ->toArray();
                                                })
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, Set $set) {
                                                    if (!$state) return;
                                                    // подставляем снимок себестоимости компонента (замени поле под себя)
                                                    $component = \App\Models\Shop\Product::find($state);
                                                    $unit = (float) ($component->cost_price ?? 0); // <-- ТВОЕ поле себестоимости
                                                    $set('unit_cost', $unit);
                                                })
                                                ->required()
                                                ->columnSpan(5),

                                            TextInput::make('qty')
                                                ->label(__('product.fields.qty'))
                                                ->numeric()
                                                ->default(1)
                                                ->reactive()
                                                ->afterStateUpdated(fn ($state, Set $set, Get $get)
                                                => $set('row_cost', (float)$state * (float)$get('unit_cost')))
                                                ->columnSpan(2),

                                            TextInput::make('unit_cost')
                                                ->label(__('product.fields.unit_cost'))
                                                ->numeric()
                                                ->default(0)
                                                ->reactive()
                                                ->afterStateUpdated(fn ($state, Set $set, Get $get)
                                                => $set('row_cost', (float)$state * (float)$get('qty')))
                                                ->columnSpan(2),

                                            TextInput::make('row_cost')
                                                ->label(__('product.fields.row_cost'))
                                                ->numeric()
                                                ->disabled()
                                                ->dehydrated(true) // сохраняем снимок
                                                ->columnSpan(3),
                                        ]),
                                ]),

                            Section::make(__('product.sections.calc_summary'))
                                ->schema([
                                    TextInput::make('total_cost')
                                        ->label(__('product.fields.total_cost'))
                                        ->disabled()
                                        ->helperText('Оновиться після збереження або командою перерахунку'),
                                ])
                                ->columnSpanFull(),
                        ]),
                ]),
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
                ->preserveFilenames()            // оставить оригинальные имена
                ->panelAspectRatio(null)         // убираем crop в фиксированное соотношение
                ->panelLayout('grid')            // сетка превьюшек
                ->extraAttributes(['class' => 'fi-upload-contain']) // класс на wrapper
                ->imagePreviewHeight('300')     // фиксированная высота блока превью
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
    /*public static function characteristicTab(Get $get, ?\App\Models\Shop\Product $record): array
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
    }*/
// где-то внизу файла ProductResource.php
   /* public static function buildCharacteristicField(Characteristic $char, string $locale, ?Product $record = null): FormsField
    {
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

        $field = $fieldType::make("characteristics.{$char->id}")
            ->label($char->getTranslation('name', $locale) ?? '—')
            ->required((bool) ($char->pivot?->is_required));

        // числа
        if (in_array($char->field_type, ['number', 'decimal'], true)) {
            $field = $field->numeric()->inputMode('decimal')->step($char->field_type === 'decimal' ? 'any' : '1');
        }

        // опции для select/radio/multiselect/checkbox
        if (in_array($char->field_type, ['select', 'radio', 'multiselect', 'checkbox'], true)) {
            // ✅ надёжнее брать перевод напрямую:
            $options = $char->values->mapWithKeys(function ($item) use ($locale) {
                $name = $item->getTranslation('value', $locale);
                if (!$name) {
                    // fallback на raw json value, как было у тебя
                    $raw = $item->getAttributes()['value'] ?? null;
                    $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
                    $name = $decoded[$locale] ?? reset($decoded) ?? $item->id;
                }
                return [$item->id => $name];
            });
            $field = $field->options($options);
        }

        // дефолты из записи при редактировании
        $field = $field->afterStateHydrated(function (Component $component) use ($char, $record) {
            if (!$record) return;

            // все значения по этой характеристике
            $entries = $record->characteristicValues
                ->where('characteristic_id', $char->id);

            // одно значение по связке (для select/radio/number/text/…)
            $entry = \App\Models\Shop\ProductCharacteristicValue::where('product_id', $record->id)
                ->where('characteristic_id', $char->id)
                ->first();

            $value = match ($char->field_type) {
                'text', 'textarea', 'color'   => $entry?->value_text,
                'number', 'decimal'           => $entry?->value_number,
                'toggle'                      => (bool) $entry?->value_text,
                'datetime'                    => optional($entry?->value_datetime)?->format('Y-m-d H:i:s'),
                'select', 'radio'             => $entry?->characteristic_value_id,

                // ✅ важный фикс: брать ID из pivot, а не из модели (во избежание путаницы)
                'multiselect', 'checkbox'     => $entries
                    ->pluck('pivot.characteristic_value_id')
                    ->filter()
                    ->values()
                    ->toArray(),

                'file' => $entry && $entry->value_text ? [$entry->value_text] : [],
                default => null,
            };

            $component->state($value);
        });

        // 💵 ценообразование для checkbox/multiselect при pricing_type = 1|2
        if (in_array($char->field_type, ['checkbox', 'multiselect'], true) && in_array($char->pricing_type, [1, 2], true)) {
            $is_new = true;

            // один проход — выяснить, есть ли уже записи для этой характеристики
            $char->values->each(function ($value) use (&$is_new, $char, $record) {
                $id = $value->id;
                $entries = $record->characteristicValues
                    ->filter(fn($item) =>
                        $item->pivot->characteristic_id === $char->id &&
                        $item->pivot->product_id === $record->id &&
                        $item->pivot->characteristic_value_id === $id
                    );
                if ($entries->isNotEmpty()) $is_new = false;
            });

            $items = $char->values->map(function ($value) use (&$is_new, $char, $locale, $record) {
                $id   = $value->id;
                $name = $value->getTranslation('value', $locale) ??
                    (function () use ($value, $locale) {
                        $raw = $value->getAttributes()['value'] ?? null;
                        $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
                        return $decoded[$locale] ?? reset($decoded) ?? $value->id;
                    })();

                $entries = $record->characteristicValues
                    ->filter(fn($item) =>
                        $item->pivot->characteristic_id === $char->id &&
                        $item->pivot->product_id === $record->id &&
                        $item->pivot->characteristic_value_id === $id
                    )
                    ->map(fn($item) => [
                        'characteristic_value_id' => $item->pivot->characteristic_value_id,
                        'price_modifier'          => $item->pivot->price_modifier,
                    ])
                    ->values()
                    ->toArray();

                $isChecked = !empty($entries);
                $price     = $isChecked ? ($entries[0]['price_modifier'] ?? 0) : 0;

                $valueName = $value->getTranslation('value', $locale) ?? $name;

                return Grid::make(12)->schema([
                    Checkbox::make("characteristics.{$char->id}.{$id}")
                        ->label($name)
                        ->afterStateHydrated(function ($component) use ($isChecked, $is_new) {
                            $component->state($is_new ? true : (bool) $isChecked);
                        })
                        ->columnSpan(3),

                    TextInput::make("characteristics_price.{$char->id}.{$id}")
                        ->placeholder("Ціна: {$valueName}")
                        ->afterStateHydrated(fn ($component) => $component->state($price))
                        ->numeric()
                        ->label(false)
                        ->dehydrated(true)
                        ->inputMode('decimal')
                        ->step('any')
                        ->columnSpan(3),
                ]);
            });

            return Fieldset::make($char->getTranslation('name', $locale) ?? '—')
                ->schema([Group::make($items->toArray())->columnSpanFull()]);
        }

        return $field;
    }
*/

    /**
     * Опции значений для select/checkbox/radio (id => label)
     */

    public static function characteristicTab(Get $get, ?Product $record): array
    {
        $category = $record?->categories?->first() ?? ProductCategory::find($get('category_id'));
       // dd($category);
        if (!$category) {
            return [
                Placeholder::make('no_category')
                    ->label('No category')
                    ->content('Сначала выберите категорию'),
            ];
        }

        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        $characteristics = $category->getAllCharacteristicsWithInheritance(false);
      //  dd($characteristics);
        return $characteristics
            //->map(fn($char) => self::buildCharacteristicField($char, $defaultLocale, $record))
            ->map(fn ($char, $i) => self::buildCharacteristicField($char, $defaultLocale, $record, $i))
            ->all();
    }
     public static function buildCharacteristicField(Characteristic $char, string $locale, ?Product $record = null): Component
    {
        $label       = $char->getTranslation('name', $locale) ?? '—';
        $required    = (bool) ( $char->is_required ?? false);
        $pricingType = (int)  ($char->pivot->pricing_type ?? $char->pricing_type ?? 0);
        $hasPriceUi  = in_array($char->field_type, ['checkbox','multiselect'], true)
            && in_array($pricingType, [1,2], true);
          $name =  $char->id;
           // Уже сохранённые значения (пусто на create)
        $existing = collect(data_get($record, 'characteristicValues', []))
            ->where('pivot.characteristic_id', $char->id);

       //     1) ОБЫЧНЫЙ MULTISELECT (без цен) → Select::multiple()

        if ($char->field_type === 'multiselect' && ! $hasPriceUi) {
             $name = 'char_' . $char->id;
             if (! $record?->exists) {
                $options = $char->values->mapWithKeys(function ($item) use ($locale) {
                    $raw     = $item->getAttributes()['value'] ?? null;
                    $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
                    $label   = $item->getTranslation('value', $locale) ?? ($decoded[$locale] ?? reset($decoded) ?? $item->id);

                    return [(string) $item->id => $label]; // 👈 ключи только СТРОКИ
                })->toArray();
                   $field= CheckboxList::make($name)
                    ->label($label)
                      ->options($options)
                    ->columns(2)
                    ->afterStateHydrated(function ($component, $state) {
                        // если в стейте вдруг лежит булевая мапа — превратим в массив выбранных ключей
                        if (is_array($state) && array_is_list($state)) {
                            // всё хорошо — это уже массив [ "23", "19", ... ]
                            return;
                        }
                        if (is_array($state)) {
                            // было вида [ "23" => true, "19" => false, ... ]
                            $selected = collect($state)->filter()->keys()->values()->all();
                            $component->state($selected);
                        } else {
                            $component->state([]); // на всякий случай
                        }
                    })
                    ->reactive()
                    ->default([])
                    ->dehydrated(true)
                    ->dehydrateStateUsing(fn ($state) =>
                        // всегда массив строк без дыр
                    collect($state)->map(fn($v) => (string) $v)->values()->all()
                    )
                    ->rules(['array'])
                    ->required($required)
                    ->columnSpanFull();
                  return Group::make()
                    ->statePath('characteristics')
                    ->schema([$field])
                    ->columnSpanFull();
            }
            // Оборачиваем в Group с префиксом statePath('characteristics')

            $field=   MultiSelect::make((string) $char->id)
                      ->label($label)
                        ->live()
                ->preload(false)            // ← обязателен, чтобы показать список сразу
                ->options(function (Get $get) use ($char, $locale, $name, $record) {
                    // 1) Выбранные В ЭТОТ МОМЕНТ (после взаимодействия с полем)
                    $selectedNow = collect((array) $get($name))
                        ->filter()
                        ->map(fn ($v) => (int) $v);

                    // 2) Уже сохранённые в БД (важно на первичном рендере)
                    $selectedPersisted = $record?->exists
                        ? $record->characteristicValues
                            ->where('pivot.characteristic_id', $char->id)
                            ->pluck('pivot.characteristic_value_id')
                        : collect();

                    $exclude = $selectedNow->merge($selectedPersisted)->unique()->values();

                    return $char->values()
                        ->when($exclude->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $exclude))
                        ->get()
                        ->mapWithKeys(function ($item) use ($locale) {
                            $raw     = $item->getAttributes()['value'];
                            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                            $label   = $decoded[$locale] ?? reset($decoded);
                            return [(string) $item->id => $label]; // ключи строкой!
                        })
                        ->toArray();
                })
                ->getOptionLabelsUsing(function (array $values) use ($char, $locale) {
                    return $char->values()
                        ->whereIn('id', $values)
                        ->get()
                        ->mapWithKeys(function ($item) use ($locale) {
                            $raw     = $item->getAttributes()['value'];
                            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                            $label   = $decoded[$locale] ?? reset($decoded);
                            return [(string) $item->id => $label];
                        })
                        ->toArray();
                })

                // гидрация state на редактировании
                ->afterStateHydrated(function ($component) use ($record, $char) {
                    if (! $record?->exists) { $component->state([]); return; }

                    $ids = $record->characteristicValues
                        ->where('pivot.characteristic_id', $char->id)
                        ->pluck('pivot.characteristic_value_id')
                        ->map(fn ($v) => (string) $v)
                        ->all();

                    $component->state($ids);
                })
               ->default([])
                ->rule('array')
                ->required($required);
                 return Group::make()
                     ->statePath('characteristics')
                     ->schema([$field])
                     ->columnSpanFull();
        }

                             //    2) КАСТОМНЫЙ UI: чекбоксы + цена
        // 💵 Доп. поля для цен на каждое значение (если pricing_type влияет)
        if (in_array($char->field_type, ['checkbox', 'multiselect']) && in_array($char->pricing_type, [1, 2])) {


            //   dd($entries);
            // dump($char->values);
            $is_new=true;
            // пройдем массви по значениям чтобы понять это первый разли заполняются характеристики, чтобы потом можно было птички проставить
       /*     $items_t = $char->values->map(function ($value) use (&$is_new,$char, $locale, $record) {
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
            });*/
            //  dump($is_new);
            $items = $char->values->map(function ($value) use (&$is_new,$char, $locale, $record) {
                $id = $value->id;

                $raw = $value->getAttributes()['value'];
                $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                $name = $decoded[$locale] ?? reset($decoded);

                /*   $isChecked = $entries
                       ->first(fn($v) => $v->characteristic_value_id == $id && $v->characteristic_id == $char->id);*/
                //dd();
                // безопасная коллекция выбранных значений (пустая на create)
                $cv = collect(data_get($record, 'characteristicValues', []));  // <- ключевая строка

                $entries = $cv
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
                        ->label("Ціна: {$valueName}")
                        ->dehydrated(true)
                        ->inputMode('decimal')
                        ->step('any')
                        ->columnSpan(3),
                ]); // ⬅️ Обязательно;
            });

            // ⬇️ ВАЖНО: переиндексировать и передать через ->schema()
            return Fieldset::make($char->getTranslation('name', $locale) ?? '—')
                ->schema([
                    Group::make()
                        ->schema($items->values()->all()) // <-- убираем ключи 10,11,12...
                        ->columnSpanFull(),
                ]);
        }
                         /*  if ($hasPriceUi) {
                               $isNew = $existing->isEmpty();

                               $items = $char->values->map(function ($value) use (&$isNew, $existing, $char, $locale) {
                                   $id   = (string) $value->id;

                                   $raw     = $value->getAttributes()['value'];
                                   $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                                   $name    = $decoded[$locale] ?? reset($decoded);

                                   $matched   = $existing->firstWhere('pivot.characteristic_value_id', (int) $id);
                                   if ($matched) $isNew = false;

                                   $isChecked = (bool) $matched;
                                   $price     = (float) data_get($matched, 'pivot.price_modifier', 0);
                                   $valueName = $value->getTranslation('value', $locale);

                                   return Grid::make(12)->schema([
                                       Checkbox::make($id)                     // короткое имя
                                       ->label($name)
                                           ->default($isNew ? true : $isChecked)
                                           ->dehydrated(true)
                                           ->columnSpan(3),

                                       TextInput::make("characteristics_price.{$char->id}.{$id}")
                                           ->placeholder("Ціна: {$valueName}")
                                           ->default($price)
                                           ->numeric()
                                           ->label(false)
                                           ->dehydrated(true)
                                           ->inputMode('decimal')
                                           ->step('any')
                                           ->columnSpan(3),
                                   ]);
                               })->values()->all();

                               // Серверная валидация "хотя бы один выбран"
                               $guard = Hidden::make("characteristics_guard.{$char->id}")
                                   ->dehydrateStateUsing(function (callable $get) use ($char) {
                                       $raw = (array) $get((string) $char->id); // короткое имя внутри Group
                                       return collect($raw)->filter(fn ($v) => (bool) $v)->isNotEmpty() ? 'ok' : null;
                                   })
                                   ->rules($required ? ['required'] : [])
                                   ->hidden();

                               // ВАЖНО: тоже оборачиваем в Group('characteristics')
                               return Fieldset::make($required ? ($label.' *') : $label)
                                   ->schema([
                                       Group::make()
                                           ->statePath('characteristics')
                                           ->schema(array_merge([$guard], $items))
                                           ->columnSpanFull(),
                                   ]);
                           }*/

                          //    3) ПРОЧИЕ ТИПЫ — тоже через Group('characteristics')
                           $fieldType = match ($char->field_type) {
                               'text','number','decimal' => TextInput::class,
                               'textarea'                => Textarea::class,
                               'toggle'                  => Toggle::class,
                               'select'                  => Select::class,
                            //   'multiselect'                  => MultiSelect::class,
                               'radio'                   => Radio::class,
                               'checkbox'                => CheckboxList::class,
                               'color'                   => ColorPicker::class,
                               'file'                    => FileUpload::class,
                               'datetime'                => DateTimePicker::class,
                               default                   => TextInput::class,
                           };

                           // создаём само поле с коротким именем
                           $field = $fieldType::make((string) $char->id)
                               ->label($label)
                            ;

                           if (in_array($char->field_type, ['number','decimal'], true)) {
                               $field = $field->numeric()->inputMode('decimal')->step(
                                   $char->field_type === 'decimal' ? 'any' : '1'
                               );
                           }

                           if (in_array($char->field_type, ['select','radio','checkbox'], true)) {
                               $options = $char->values->mapWithKeys(function ($item) use ($locale) {
                                   $raw     = $item->getAttributes()['value'];
                                   $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                                   return [$item->id => $decoded[$locale] ?? reset($decoded)];
                               })->toArray();
                               $field = $field->options($options);
                           }

                           // Обязательность
                           if ($required) {
                               if ($char->field_type === 'checkbox') {
                                   $field = $field
                                       ->dehydrated(true)
                                       ->dehydrateStateUsing(fn ($s) =>
                                       array_values(is_array($s) ? $s : ((string)$s === '' ? [] : (array)$s))
                                       )
                                       ->rules(['present','array','min:1']);
                                   if (method_exists($field, 'minItems')) $field = $field->minItems(1);
                               } else {
                                   //$field = $field->rules(['required']);
                                   $field = $field->required($required);
                               }
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
                                /*   'multiselect', 'checkbox' => $entries
                                       ->pluck('id') // не id, а characteristic_value_id!
                                       ->filter()
                                       ->values()
                                       ->toArray(),
                                   default => null,*/
            };
            $component->state($value);
        });
        // Значение при редактировании — через default
  /*      $field = $field->default(function () use ($char, $existing, $record) {
            dd($char);
            if (! $record?->exists) return null;

            $entry = ProductCharacteristicValue::query()
                ->where('product_id', $record->id)
                ->where('characteristic_id', $char->id)
                ->first();
            dd($entry);
            return match ($char->field_type) {
                'text','textarea','color' => $entry?->value_text,
                'number','decimal'        => $entry?->value_number,
                'toggle'                  => (bool) $entry?->value_text,
                'datetime'                => optional($entry?->value_datetime)?->format('Y-m-d H:i:s'),
                'select','radio'          => $entry?->characteristic_value_id,
                'file'                    => $entry && $entry->value_text ? [$entry->value_text] : [],
                'checkbox'                => $existing->pluck('pivot.characteristic_value_id')->filter()->values()->toArray(),
                default                   => null,
            };
        });*/

        // ВОЗВРАЩАЕМ поле внутри Group('characteristics')
        return Group::make()
            ->statePath('characteristics')
            ->schema([$field])
            ->columnSpanFull();
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
                ->maxLength(255)
                ,

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
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        $locales = static::getActiveLocales();
        return $table
            // включаем dnd-перетаскивание строк по колонке 'sort'
            ->reorderable('sort')

            // по умолчанию сортируем по 'sort'
            ->defaultSort('sort', 'asc')
            ->query(fn () => Product::query()->parents())
            ->columns([
                Tables\Columns\TextColumn::make('sku')->label('SKU')->sortable(),
                // 2) Невидимая колонка только для ПОИСКА
                TextColumn::make('title_search')
                    ->label('')                                    // ничего не рисуем
                    ->toggleable(isToggledHiddenByDefault: true)   // скрыта
                    ->searchable(
                        query: function (Builder $query, string $search) use ($defaultLocale): Builder {
                        $search = trim($search);

                        // Ищем по short_name И по переводу в JSON (MySQL 8)
                        return $query->where(function (Builder $q) use ($search, $defaultLocale) {
                            $q->where('short_name', 'like', "%{$search}%")
                                ->orWhereRaw(
                                // JSON_EXTRACT + COLLATE, чтобы сравнение было case-insensitive
                                    "JSON_UNQUOTE(JSON_EXTRACT(`title`, '$.\"{$defaultLocale}\"')) COLLATE utf8mb4_general_ci LIKE ?",
                                    ["%{$search}%"]
                                );
                        });
                    }
                    ),

                ViewColumn::make('title')
                    ->label(__('product.columns.title'))
                    ->view('filament.tables.columns.title-with-preview'),
                TextColumn::make('category_id')
                    ->label(__('product.columns.category'))
                    ->sortable(false)       // можно сделать кастомную сортировку позже
                    ->searchable(false)
                    ->formatStateUsing(function ($state,  $record)use ($defaultLocale)   {
                        $locale =  $defaultLocale;
                        $cat = $record->mainCategory;
                     //   dd($cat);
                        if (! $cat) return '—';

                        // title хранится как JSON/Translatable
                        $label = $cat->getTranslation('title', $locale, false);

                        if (is_string($label) && $label !== '') return $label;

                        // безопасный фолбек по доступным переводам
                        $all = $cat->getTranslations('title');
                        foreach ($all as $v) {
                            if (is_string($v) && $v !== '') return $v;
                            if (is_array($v) && !empty($v['value'])) return $v['value'];
                        }
                        return '—';
                    }),

                Tables\Columns\TextColumn::make('price')->label(__('product.columns.price'))->money('UAH'),
                Tables\Columns\IconColumn::make('in_stock')->label(__('product.columns.in_stock'))->boolean(),
                Tables\Columns\TextColumn::make('sort')->label(__('product.columns.sort'))->sortable()->toggleable(),
                ToggleColumn::make('is_new')->label(__('product.columns.is_new')),
                ToggleColumn::make('is_hit')->label(__('product.columns.is_hit')),
                ToggleColumn::make('is_home')->label(__('product.columns.is_home')),
                Tables\Columns\TextColumn::make('quantity')->label(__('product.columns.quantity')),
                Tables\Columns\TextColumn::make('updated_at')->label(__('product.columns.updated_at'))->dateTime('d.m.Y H:i')
            ])
            ->filtersFormColumns(6) // сколько колонок занимают фильтры в строке
            // 👇 сохранять выбор фильтров между перезагрузками
            ->persistFiltersInSession()
            ->filters([
                SelectFilter::make('category')
                    ->label(__('product.filters.category'))
                    ->columnSpan(2)
                    ->placeholder(__('product.filters.category_all'))                         // вместо «Всі»
                   // ->searchPrompt('Введите текст для поиска…')
                    ->relationship('mainCategory', 'id') // фильтруем по belongsTo 'category'
                    ->getOptionLabelFromRecordUsing(function (ProductCategory $record): string {
                        $locale = Setting::value('default_language_code') ?: app()->getLocale();

                        // без авто-фолбека, может вернуть null/array
                        $label = $record->getTranslation('title', $locale, false);

                        if (is_array($label)) {
                            $label = $label['value'] ?? Arr::first($label, fn($v) => is_string($v) && $v !== '') ?? '';
                        }

                        if (! is_string($label) || $label === '') {
                            $all = $record->getTranslations('title'); // ['uk'=>..., 'ru'=>..., ...] либо вложенные
                            foreach ($all as $v) {
                                if (is_string($v) && $v !== '') { $label = $v; break; }
                                if (is_array($v) && is_string($v['value'] ?? null) && $v['value'] !== '') { $label = $v['value']; break; }
                            }
                        }

                        return (string) $label;
                    })

                    ->preload()
                    ->searchable(),
                // Флаги: «Так / Ні / Усі»
                TernaryFilter::make('is_new')->label(__('product.filters.is_new'))
                    ->columnSpan(1)
                    ->nullable(), // покажет «Усі»
                TernaryFilter::make('is_hit')->label(__('product.filters.is_hit'))
                    ->columnSpan(1)
                    ->nullable(),
                TernaryFilter::make('is_home')->label(__('product.filters.is_home'))
                    ->columnSpan(1)
                    ->nullable(),  // Диапазон даты создания
              /*  Filter::make('created_between')
                    ->form([
                        DatePicker::make('from')->label('Створено з'),
                        DatePicker::make('until')->label('Створено до'),
                    ])
                    ->indicateUsing(function (array $data): array {
                        $i = [];
                        if ($data['from'] ?? null)  $i[] = 'З: ' . \Carbon\Carbon::parse($data['from'])->format('d.m.Y');
                        if ($data['until'] ?? null) $i[] = 'До: ' . \Carbon\Carbon::parse($data['until'])->format('d.m.Y');
                        return $i;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),*/

                // Поиск по внешнему коду
                Filter::make('code2_like')
                    ->form([
                        TextInput::make('code2')->label(__('product.filters.code2'))->placeholder('Напр., ABC-123'),
                    ])
                    ->query(fn (Builder $q, array $data) =>
                    $q->when($data['code2'] ?? null, fn ($qq, $v) => $qq->where('code2', 'like', "%{$v}%"))
                    )
                    ->indicateUsing(fn (array $data) => ($data['code2'] ?? null) ? ['Код: '.$data['code2']] : []),

                // Диапазон цены (если есть price)
          /*      Filter::make('price_between')
                    ->columns(2)
                    ->form([
                        TextInput::make('price_min')->label('Ціна від')->numeric(),
                        TextInput::make('price_max')->label('Ціна до')->numeric(),
                    ])
                    ->query(function (Builder $q, array $data): Builder {
                        return $q
                            ->when($data['price_min'] ?? null, fn ($qq, $v) => $qq->where('price', '>=', (float) $v))
                            ->when($data['price_max'] ?? null, fn ($qq, $v) => $qq->where('price', '<=', (float) $v));
                    })
                    ->indicateUsing(function (array $data): array {
                        $i = [];
                        if ($data['price_min'] ?? null) $i[] = 'Ціна ≥ '.$data['price_min'];
                        if ($data['price_max'] ?? null) $i[] = 'Ціна ≤ '.$data['price_max'];
                        return $i;
                    }),*/
            ], layout: FiltersLayout::AboveContent)

            ->Actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
    public static function getActiveLocales(): array
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
            RelationManagers\VariantsRelationManager::class,
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
    public static function syncCharacteristicsFromForm(\App\Models\Shop\Product $product, array $payload): void
    {
        $valuesBag = $payload['values'] ?? [];
        $pricesBag = $payload['prices'] ?? [];

        // Нормализуем: [charId => [valId => bool]] → [charId => [valId, valId...]]
        $normalized = [];
        foreach ($valuesBag as $charId => $vals) {
            if ($vals === null || $vals === '') { $vals = []; }
            if (is_array($vals) && array_keys($vals) !== range(0, count($vals) - 1)) {
                // это карта вида [valId => true/false]
                $vals = collect($vals)->filter()->keys()->values()->all();
            }
            $normalized[(int) $charId] = collect((array) $vals)
                ->filter(fn ($v) => $v !== null && $v !== '')
                ->map(fn ($v) => (int) $v)
                ->values()
                ->all();
        }

        // Удаляем старые строки только по затронутым charId
        $charIds = array_keys($normalized);
        if (! empty($charIds)) {
            \App\Models\Shop\ProductCharacteristicValue::query()
                ->where('product_id', $product->id)
                ->whereIn('characteristic_id', $charIds)
                ->delete();
        }

        // Вставляем новые
        foreach ($normalized as $charId => $valIds) {
            foreach ($valIds as $valId) {
                \App\Models\Shop\ProductCharacteristicValue::create([
                    'product_id'              => $product->id,
                    'characteristic_id'       => $charId,
                    'characteristic_value_id' => $valId,
                    'price_modifier'          => data_get($pricesBag, "{$charId}.{$valId}") ?: null,
                ]);
            }
        }
    }

    public static function variationTab(Get $get, ?Product $record): array
    {
        $categoryIds = $get('category_ids') ?? $record?->categories?->pluck('id')->toArray() ?? [];

        if (empty($categoryIds)) {
            return [
                Placeholder::make('no_category')
                    ->content('Сначала выберите категорию'),
            ];
        }

        $variations = CategoryVariation::getVariationsFromManyCategories($categoryIds);

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
                                ->afterStateHydrated(function (Set $set) use ($selected, $var) {
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
    public static function shouldRegisterNavigation(): bool
    {

        // не регистрируем пункт меню, если нет права
        return static::canViewAny();
    }
}
