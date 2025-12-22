<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\TimeDiscountResource\Pages;
use App\Models\Setting;
use App\Models\Shop\Characteristic;
use App\Models\Shop\CharacteristicValue;
use App\Models\Shop\Product;
use App\Models\Shop\ProductCategory;
use App\Models\Shop\TimeDiscount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Set;
use SolutionForest\FilamentTranslateField\Forms\Component\Translate;
use Filament\Resources\Concerns\Translatable;

class TimeDiscountResource extends Resource
{
    use Translatable;
    protected static ?string $model = TimeDiscount::class;

    protected static ?string $navigationGroup = null;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = null;
    protected static ?string $pluralModelLabel = null;
    protected static ?string $modelLabel = null;

    public static function getNavigationGroup(): ?string
    {
        return __('time_discount.nav.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('time_discount.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('time_discount.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('time_discount.nav.plural_model_label');
    }

    public static function form(Form $form): Form
    {
        $weekdayOptions = [
            1 => __('time_discount.options.weekdays.1'),
            2 => __('time_discount.options.weekdays.2'),
            3 => __('time_discount.options.weekdays.3'),
            4 => __('time_discount.options.weekdays.4'),
            5 => __('time_discount.options.weekdays.5'),
            6 => __('time_discount.options.weekdays.6'),
            7 => __('time_discount.options.weekdays.7'),
        ];
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        $locales = \App\Models\Setting::getActiveLocales(); // ['uk','en','ru']
        return $form->schema([
            Forms\Components\Section::make(__('time_discount.sections.main'))
                ->columns(3)
                ->schema([
                    // мультиязычное название
                    Translate::make()
                        ->locales($locales)
                        ->prefixLocaleLabel()
                        ->columns(1)
                        ->columnSpan(2)
                        ->schema(fn (string $locale) => [
                            Forms\Components\TextInput::make('name')
                                ->label(__('time_discount.fields.name'))
                                ->maxLength(160)
                                ->required($locale === $defaultLocale),
                        ]),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('time_discount.fields.is_active'))
                        ->default(true),

                    Forms\Components\CheckboxList::make('days')
                        ->label(__('time_discount.fields.days'))
                        ->options($weekdayOptions)
                        ->columns(7)
                        ->helperText(__('time_discount.helpers.days'))
                        ->dehydrateStateUsing(fn ($state) => array_values(array_map('intval', (array) $state)))
                        ->afterStateHydrated(function ($component, $state) {
                            // если в БД лежит JSON-число (2) или строка "2" — сделаем массив [2]
                            if (is_string($state)) {
                                $decoded = json_decode($state, true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    $state = $decoded;
                                }
                            }
                            if (!is_array($state)) {
                                $state = [$state];
                            }
                            $component->state(array_values(array_map('intval', $state)));
                        })->columns(10)->columnSpanFull(),

                    Forms\Components\Group::make()
                        ->columns(3)
                        ->schema([
                            Forms\Components\TimePicker::make('time_from')
                                ->label(__('time_discount.fields.time_from'))
                                ->seconds(false),
                            Forms\Components\TimePicker::make('time_to')
                                ->label(__('time_discount.fields.time_to'))
                                ->seconds(false),
                            Forms\Components\Select::make('time_type')
                                ->label(__('time_discount.fields.time_type'))
                                ->options([
                                    'order_created'   => __('time_discount.options.time_type_order_created'),
                                    'order_fulfilled' => __('time_discount.options.time_type_order_fulfilled'),
                                ])
                                ->default('order_created'),
                        ])
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('nth_item')
                        ->label(__('time_discount.fields.nth_item'))
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required(),

                    Forms\Components\TextInput::make('percent')
                        ->label(__('time_discount.fields.percent'))
                        ->numeric()
                        ->suffix('%')
                        ->minValue(0.01)
                        ->maxValue(100)
                        ->step(0.01)
                        ->required(),

                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label(__('time_discount.fields.starts_at'))
                        ->seconds(false),

                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label(__('time_discount.fields.ends_at'))
                        ->seconds(false),

                    Forms\Components\Textarea::make('note')
                        ->label(__('time_discount.fields.note'))
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make(__('time_discount.sections.scope'))
                ->description(__('time_discount.helpers.scope_description'))
                ->columns(2)
                ->schema([
                    // категории (группы)
                    Forms\Components\MultiSelect::make('categories')
                        ->label(__('time_discount.fields.categories'))
                        ->relationship(
                            name: 'categories',
                            titleAttribute: 'title' // поправим ниже через getOptionLabelFromRecordUsing
                        )
                        ->getOptionLabelFromRecordUsing(function (ProductCategory $record) {
                            $defaultLocale = Setting::value('default_language_code') ?: app()->getLocale();
                            return $record->getTranslation('title', $defaultLocale);
                        })
                        ->preload()
                        ->searchable(),

                    // товары
                    Forms\Components\MultiSelect::make('products')
                        ->label(__('time_discount.fields.products'))
                        ->relationship(
                            name: 'products',
                            titleAttribute: 'title'
                        )
                        ->options(function () {
                            $defaultLocale = Setting::value('default_language_code') ?: app()->getLocale();
                            return Product::query()
                                ->where('in_stock', 1)
                                ->get()
                                ->mapWithKeys(fn(Product $p) => [
                                    $p->id => (
                                        json_decode($p->getRawOriginal('title'), true)[$defaultLocale]
                                        ?? json_decode($p->getRawOriginal('title'), true)[config('app.locale')]
                                    ),
                                ])->toArray();
                        })
                        ->searchable()
                        ->preload(),

                    // характеристики (любой value)
                    // Характеристики (любой value)
                    Forms\Components\MultiSelect::make('characteristics')
                        ->label(__('time_discount.fields.characteristics'))
                        ->relationship(
                            name: 'characteristics',
                            titleAttribute: 'name'
                        )
                        ->options(function () {
                            // если у name перевод — тут подставь нужную локаль
                            return Characteristic::query()
                                ->orderBy('id')
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->reactive() // <- чтобы отреагировать на изменения
                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                            // оставим в characteristicValues только те, что принадлежат выбранным характеристикам
                            $selectedChars = (array) $state;
                            if (empty($selectedChars)) {
                                return; // ничего не чистим — пользователь может хотеть общий список
                            }

                            $currentValueIds = (array) $get('characteristicValues');
                            if (empty($currentValueIds)) {
                                return;
                            }

                            $validIds = CharacteristicValue::query()
                                ->whereIn('characteristic_id', $selectedChars)
                                ->whereIn('id', $currentValueIds)
                                ->pluck('id')
                                ->all();

                            // если какие-то значения «выпали» — перезапишем состояние
                            if (count($validIds) !== count($currentValueIds)) {
                                $set('characteristicValues', $validIds);
                            }
                        }),

                    // Значения характеристик (фильтруются по выбранным характеристикам)
                    Forms\Components\MultiSelect::make('characteristicValues')
                        ->label(__('time_discount.fields.characteristic_values'))
                        ->relationship(
                            name: 'characteristicValues',
                            titleAttribute: 'value'
                        )
                        ->options(function (Get $get) {
                            $defaultLocale = Setting::value('default_language_code') ?: app()->getLocale();

                            $charIds = (array) $get('characteristics'); // выбранные характеристики
                            $q = CharacteristicValue::query()
                                ->with('characteristic:id,name')
                                ->orderBy('characteristic_id')
                                ->orderBy('id');

                            if (!empty($charIds)) {
                                $q->whereIn('characteristic_id', $charIds);
                            }

                            return $q->get()->mapWithKeys(function (CharacteristicValue $v) use ($defaultLocale) {
                                // красивый лейбл вида «Размер пирогів: 30 см»
                                $charName = $v->characteristic->name ?? ('#'.$v->characteristic_id);

                                // если value переводимое — подставь аналогично товарам:
                                // $valText = $v->getTranslation('value', $defaultLocale);
                                $valText = $v->value ?? ('#'.$v->id);

                                return [$v->id => "{$charName}: {$valText}"];
                            })->toArray();
                        })
                        ->searchable()
                        ->preload(false) // чтобы не грузить весь справочник; будет подгружаться после выбора характеристик
                        ->helperText(__('time_discount.helpers.characteristic_values')),
                ])

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('time_discount.columns.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('percent')
                    ->label(__('time_discount.columns.percent'))
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('nth_item')
                    ->label(__('time_discount.columns.nth_item'))
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('time_type')
                    ->label(__('time_discount.columns.time_type'))
                    ->colors([
                        'primary' => 'order_created',
                        'warning' => 'order_fulfilled',
                    ])
                    ->formatStateUsing(fn ($state) => $state === 'order_created'
                        ? __('time_discount.options.time_type_display_created')
                        : __('time_discount.options.time_type_display_fulfilled')),

                Tables\Columns\TextColumn::make('days')
                    ->label(__('time_discount.columns.days'))
                    ->formatStateUsing(function ($state) {
                        $map = [
                            1 => __('time_discount.options.weekdays.1'),
                            2 => __('time_discount.options.weekdays.2'),
                            3 => __('time_discount.options.weekdays.3'),
                            4 => __('time_discount.options.weekdays.4'),
                            5 => __('time_discount.options.weekdays.5'),
                            6 => __('time_discount.options.weekdays.6'),
                            7 => __('time_discount.options.weekdays.7'),
                        ];

                        // null / пусто => "Все"
                        if ($state === null || $state === '' || $state === []) {
                            return __('time_discount.options.days_all');
                        }

                        // если строка — пробуем JSON decode
                        if (is_string($state)) {
                            $decoded = json_decode($state, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $state = $decoded;
                            }
                        }

                        // если коллекция — в массив
                        if ($state instanceof Collection) {
                            $state = $state->all();
                        }

                        // если скаляр (например 2) — оборачиваем в массив
                        if (!is_array($state)) {
                            $state = [$state];
                        }

                        // нормализуем к int и оставляем только 1..7
                        $days = array_values(array_filter(array_map('intval', $state), fn ($d) => $d >= 1 && $d <= 7));

                        if (empty($days)) {
                            return __('time_discount.options.days_all');
                        }

                        return implode(' ', array_map(fn ($d) => $map[$d] ?? (string) $d, $days));
                    }),

                Tables\Columns\TextColumn::make('time_from')
                    ->label(__('time_discount.columns.time_from')),

                Tables\Columns\TextColumn::make('time_to')
                    ->label(__('time_discount.columns.time_to')),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('time_discount.columns.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label(__('time_discount.columns.starts_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label(__('time_discount.columns.ends_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label(__('time_discount.filters.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTimeDiscounts::route('/'),
            'create' => Pages\CreateTimeDiscount::route('/create'),
            'edit'   => Pages\EditTimeDiscount::route('/{record}/edit'),
        ];
    }
}
