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
class TimeDiscountResource extends Resource
{
    protected static ?string $model = TimeDiscount::class;

    protected static ?string $navigationGroup = 'Дисконтные программы';
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Скидки по времени';
    protected static ?string $pluralModelLabel = 'Скидки по времени';
    protected static ?string $modelLabel = 'Скидка по времени';

    public static function form(Form $form): Form
    {
        $weekdayOptions = [
            1 => 'Пн',
            2 => 'Вт',
            3 => 'Ср',
            4 => 'Чт',
            5 => 'Пт',
            6 => 'Сб',
            7 => 'Вс',
        ];

        return $form->schema([
            Forms\Components\Section::make('Основные параметры')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Название')
                        ->required()
                        ->maxLength(160)
                        ->columnSpan(2),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Активна')
                        ->default(true),

                    Forms\Components\CheckboxList::make('days')
                        ->label('Дни недели')
                        ->options([1=>'Пн',2=>'Вт',3=>'Ср',4=>'Чт',5=>'Пт',6=>'Сб',7=>'Вс'])
                        ->columns(7)
                        ->helperText('Если не выбрано — действует каждый день')
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
                                ->label('Время с')
                                ->seconds(false),
                            Forms\Components\TimePicker::make('time_to')
                                ->label('Время по')
                                ->seconds(false),
                            Forms\Components\Select::make('time_type')
                                ->label('Тип времени')
                                ->options([
                                    'order_created'   => 'По времени создания заказа',
                                    'order_fulfilled' => 'По времени выполнения (доставка/выдача)',
                                ])
                                ->default('order_created'),
                        ])
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('nth_item')
                        ->label('Каждый N-й товар')
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required(),

                    Forms\Components\TextInput::make('percent')
                        ->label('Скидка %')
                        ->numeric()
                        ->suffix('%')
                        ->minValue(0.01)
                        ->maxValue(100)
                        ->step(0.01)
                        ->required(),

                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label('Начало действия')
                        ->seconds(false),

                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label('Окончание действия')
                        ->seconds(false),

                    Forms\Components\Textarea::make('note')
                        ->label('Примечание')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Область действия')
                ->description('Ограничь акцию по группам, товарам, характеристикам или их значениям')
                ->columns(2)
                ->schema([
                    // категории (группы)
                    Forms\Components\MultiSelect::make('categories')
                        ->label('Категории (группы)')
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
                        ->label('Товары')
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
                        ->label('Характеристики (любой value)')
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
                        ->label('Значения характеристик')
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
                        ->helperText('Если заполнено — приоритетнее, чем просто «характеристики (любой value)»'),
                ])

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('percent')
                    ->label('Скидка')
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('nth_item')
                    ->label('Каждый N-й')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('time_type')
                    ->label('Тип времени')
                    ->colors([
                        'primary' => 'order_created',
                        'warning' => 'order_fulfilled',
                    ])
                    ->formatStateUsing(fn ($state) => $state === 'order_created'
                        ? 'Создание заказа'
                        : 'Выполнение'),

                Tables\Columns\TextColumn::make('days')
                    ->label('Дни')
                    ->formatStateUsing(function ($state) {
                        $map = [1=>'Пн',2=>'Вт',3=>'Ср',4=>'Чт',5=>'Пт',6=>'Сб',7=>'Вс'];

                        // null / пусто => "Все"
                        if ($state === null || $state === '' || $state === []) {
                            return 'Все';
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
                            return 'Все';
                        }

                        return implode(' ', array_map(fn ($d) => $map[$d] ?? (string) $d, $days));
                    }),

                Tables\Columns\TextColumn::make('time_from')
                    ->label('С'),

                Tables\Columns\TextColumn::make('time_to')
                    ->label('По'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Начало')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Окончание')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Активна'),
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
