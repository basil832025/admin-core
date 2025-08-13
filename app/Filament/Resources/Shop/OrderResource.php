<?php

namespace App\Filament\Resources\Shop;

use App\Enums\OrderStatus;
use App\Filament\Clusters\Products\Resources\ProductResource;
use App\Filament\Resources\Shop\OrderResource\Pages;
use App\Filament\Resources\Shop\OrderResource\RelationManagers;
use App\Filament\Resources\Shop\OrderResource\Widgets\OrderStats;
use App\Forms\Components\AddressForm;
use App\Models\Setting;
use App\Models\Shop\Order;
use App\Models\Shop\Product;
use App\Models\Shop\ProductCategory;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Shop\VariationValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use \App\Models\Currency;
use Illuminate\Support\Carbon;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Spatie\Activitylog\Models\Activity;
use Filament\Forms\Components\View;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'shop/orders';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?string $navigationGroup = 'Магазин';
    protected static ?string $navigationLabel = 'Заказы';
    protected static ?string $modelLabel = 'Заказы';
    protected static ?string $pluralModelLabel = 'Заказы';
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?int $navigationSort = 1;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ЛЕВАЯ ЧАСТЬ: две вкладки
                Forms\Components\Group::make()
                    ->schema([
                        Tabs::make('order_tabs')
                            ->tabs([
                                Tab::make('Инфо по заказу')
                                    ->schema(static::getInfoTabSchema())
                                    ->columns(2),

                                Tab::make('Товары')
                                    ->schema(static::getProductsTabSchema()),
                            ])
                            ->persistTabInQueryString(), // удобно при перезагрузке
                    ])
                    ->columnSpan(['lg' => fn (?Order $record) => $record === null ? 3 : 2]),

                // ПРАВАЯ КОЛОНКА: статусы + служебные поля
                Forms\Components\Section::make()
                    ->schema(static::getSidebarSchema())
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);

    }
    public static function getProductsTabSchema(): array
    {
        return [
            Forms\Components\Section::make('Товары заказа')
                ->headerActions([
                    Action::make('Очистить')
                        ->modalHeading('Are you sure?')
                        ->modalDescription('All existing items will be removed from the order.')
                        ->requiresConfirmation()
                        ->color('danger')
                        ->action(fn (Forms\Set $set) => $set('items', [])),
                ])
                ->schema([
                    Forms\Components\Placeholder::make('order_total')
                        ->label('Сума замовлення')
                        ->content(function (callable $get) {
                            // ваш расчёт суммы как был
                            $items = $get('items') ?? [];
                            $items = collect($items)->map(fn ($item) => is_object($item) ? (array) $item : $item);
                            $total = $items->sum(function ($item) {
                                $qty = (float) ($item['qty'] ?? 0);
                                $price = (float) ($item['unit_price'] ?? 0);
                                $modifiers = collect($item['modifiers'] ?? [])->map(fn ($m) => is_object($m) ? (array) $m : $m);
                                $modifierSum = $modifiers->sum(fn ($mod) => (float) ($mod['price_modifier'] ?? 0));
                                return $qty * ($price + $modifierSum);
                            });
                            return number_format($total, 2, ',', ' ') . ' грн';
                        })
                        ->reactive()
                        ->columnSpanFull(),

                    static::getItemsRepeater(),
                ]),
        ];
    }

    public static function getSidebarSchema(): array
    {
        return [
            Forms\Components\Section::make('Статусы')
                ->schema([
                    Forms\Components\ToggleButtons::make('status')
                        ->label('Статус')
                        ->inline()
                        ->options(fn () => static::allowedStatuses()) // ваш фильтр по правам
                        ->icons(collect(OrderStatus::cases())->mapWithKeys(
                            fn (OrderStatus $s) => [$s->value => $s->getIcon()]
                        )->all())
                        ->colors(collect(OrderStatus::cases())->mapWithKeys(
                            fn (OrderStatus $s) => [$s->value => $s->getColor()]
                        )->all())
                        ->required()
                        ->disabled(fn () => empty(static::allowedStatuses())),
                ]),

            Forms\Components\Section::make('Служебная информация')
                ->schema([
                    Forms\Components\Placeholder::make('created_at')
                        ->label('Создан')
                        ->content(fn (Order $record): ?string => $record->created_at?->diffForHumans()),
                    Forms\Components\Placeholder::make('updated_at')
                        ->label('Изменён')
                        ->content(fn (Order $record): ?string => $record->updated_at?->diffForHumans()),
                ]),
        ];
    }

    public static function getInfoTabSchema(): array
    {
        return [
            Forms\Components\TextInput::make('number')
                ->default('OR-' . random_int(100000, 999999))
                ->disabled()
                ->label('Номер заказа')
                ->dehydrated()
                ->required()
                ->maxLength(32)
                ->unique(Order::class, 'number', ignoreRecord: true),

            Forms\Components\Select::make('clients_id')
                ->relationship('clients', 'name')
                ->searchable()
                ->label('Клиент')
                ->required()
                // ... весь ваш createOptionForm и createOptionAction без изменений ...
                ->createOptionForm([/* как у вас */])
                ->createOptionAction(function (Action $action) {
                    return $action->modalHeading('Создание клиента')->modalSubmitActionLabel('Создать клиента')->modalWidth('lg');
                }),

            // связь адреса (hidden как было)
            Forms\Components\Hidden::make('client_address_id')->dehydrated(true),

            // Выбор адреса (оставляем вашу логику полностью)
            Forms\Components\Select::make('selected_address_id')
                ->label('Адрес доставки')
                ->placeholder('Выберите адрес')
                ->afterStateHydrated(function (Forms\Components\Select $component, ?\App\Models\Shop\Order $record, callable $set) {
                    if ($record && $record->client_address_id) {
                        $component->state((string) $record->client_address_id);

                        // 👇 Загрузим данные в addressForm
                        $address = \App\Models\Shop\ClientAddress::find($record->client_address_id);

                        if ($address) {
                            $set('address', $address->only([
                                'street',
                                'house',
                                'apartment',
                                'intercom',
                                'floor',
                                'entrance',
                                'zip',
                                'city',
                                'country',
                                'note',
                                'type',
                                'is_private_house',
                            ]));
                        }
                    }
                })


                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state !== '-1') {
                        $set('client_address_id', (int) $state); // ✅ Обновляем поле в Order
                    }
                    if (! $state || $state === '-1') {
                        $set('address', [
                            'street' => null,
                            'house' => null,
                            'apartment' => null,
                            'intercom' => null,
                            'floor' => null,
                            'entrance' => null,
                            'zip' => null,
                            'city' => null,
                            'country' => null,
                            'note' => null,
                            'type' => null,
                            'is_private_house' => false,
                        ]);
                    } else {
                        $address = \App\Models\Shop\ClientAddress::find($state);
                        if ($address) {
                            $set('address', $address->only([
                                'street',
                                'house',
                                'apartment',
                                'intercom',
                                'floor',
                                'entrance',
                                'zip',
                                'city',
                                'country',
                                'note',
                                'type',
                                'is_private_house',
                            ]));
                        }
                    }
                })
                ->options(function (callable $get) {
                    $clientId = $get('clients_id');

                    if (! $clientId) {
                        return [];
                    }

                    $addresses = \App\Models\Shop\ClientAddress::query()
                        ->where('client_id', $clientId)
                        ->get();

                    $final = collect(['-1' => 'Новый адрес'])
                        ->union(
                            $addresses->mapWithKeys(function ($address) {
                                $key = (string) $address->id;
                                $label = trim(implode(', ', array_filter([
                                    $address->street,
                                    $address->house,
                                    $address->apartment ? 'кв. ' . $address->apartment : null,
                                    $address->entrance ? 'подъезд ' . $address->entrance : null,
                                    $address->floor ? 'этаж ' . $address->floor : null,
                                    $address->intercom ? 'домофон ' . $address->intercom : null,
                                ])));

                                //  dump($key,$label);
                                return [$key => $label];// 👈 ключи как строки!
                            })
                        ) ; // 👈 сохраняет ключи!
                    // dump($final->keys()->all());
                    //dd($final); // ⬅️ Посмотрим, что реально вернётся
                    // dd($final->all());
                    return $final->all(); // потом это оставить ->toArray()
                })


                //  ->default(fn (?Order $record) => $record?->client_address_id)
                ->searchable()
                ->reactive()
                ->live()

                //  ->placeholder('Новый адрес')
                ->columnSpanFull(),

            // 👉 сам адрес (раньше был справа) ставим над примечанием:
            AddressForm::make('address')
                ->key('address-form')
                ->visible(fn (Get $get) => filled($get('selected_address_id')))
                ->columnSpan('full'),

            Forms\Components\Select::make('currency')
                ->searchable()
                ->label('Валюта')
                ->options(\App\Models\Currency::pluck('name', 'code'))
                ->default('UAH')
                ->required(),

            Forms\Components\MarkdownEditor::make('notes')
                ->label('Примечание')
                ->columnSpan('full'),
        ];
    }
    /*public static function getInfoTabSchema(): array
    {
        return [
            Forms\Components\TextInput::make('number')
                ->default('OR-' . random_int(100000, 999999))
                ->disabled()
                ->label('Номер заказа')
                ->dehydrated()
                ->required()
                ->maxLength(32)
                ->unique(Order::class, 'number', ignoreRecord: true),

            Forms\Components\Select::make('clients_id')
                ->relationship('clients', 'name')
                ->searchable()
                ->label('Клиент')
                ->required()
                // ... весь ваш createOptionForm и createOptionAction без изменений ...
                ->createOptionForm([])
                ->createOptionAction(function (Action $action) {
                    return $action->modalHeading('Создание клиента')->modalSubmitActionLabel('Создать клиента')->modalWidth('lg');
                }),

            // связь адреса (hidden как было)
            Forms\Components\Hidden::make('client_address_id')->dehydrated(true),

            // Выбор адреса (оставляем вашу логику полностью)
            Forms\Components\Select::make('selected_address_id')
                ->label('Адрес доставки')
                ->placeholder('Выберите адрес')
                ->afterStateHydrated(function (Forms\Components\Select $component, ?\App\Models\Shop\Order $record, callable $set) {
                    // ... ваш код из getDetailsFormSchema ...
                })
                ->afterStateUpdated(function ($state, callable $set) {
                    // ... ваш код из getDetailsFormSchema ...
                })
                ->options(function (callable $get) {
                    // ... ваш код из getDetailsFormSchema ...
                })
                ->searchable()
                ->reactive()
                ->live()
                ->columnSpanFull(),

            // 👉 сам адрес (раньше был справа) ставим над примечанием:
            AddressForm::make('address')
                ->key('address-form')
                ->visible(fn (Get $get) => filled($get('selected_address_id')))
                ->columnSpan('full'),

            Forms\Components\Select::make('currency')
                ->searchable()
                ->label('Валюта')
                ->options(\App\Models\Currency::pluck('name', 'code'))
                ->default('UAH')
                ->required(),

            Forms\Components\MarkdownEditor::make('notes')
                ->label('Примечание')
                ->columnSpan('full'),
        ];
    }*/

    public static function getRightFormSchema(): array
    {
        return  [
            AddressForm::make('address')
                ->key('address-form') // уникальный ключ для реактивности
                ->visible(fn (Get $get) => filled($get('selected_address_id')))
                //   ->visible(fn (Get $get) => dd($get('client_address_id')))
                ->columnSpan('full'),


        ];
    }
    public static function getDetailsFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('number')
                ->default('OR-' . random_int(100000, 999999))
                ->disabled()
                ->label('Номер заказа')
                ->dehydrated()
                ->required()
                ->maxLength(32)
                ->unique(Order::class, 'number', ignoreRecord: true),

            Forms\Components\Select::make('clients_id')
                ->relationship('clients', 'name')
                ->searchable()
                ->label('Клиент')
                ->required()
                ->createOptionForm([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->label('ФИО')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        //->required()
                        ->email()
                        ->maxLength(255)
                        ->unique(),

                    Forms\Components\TextInput::make('phone')
                        ->label('Телефон')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Select::make('gender')
                        ->placeholder('Select gender')
                        ->label('Пол')
                        ->options([
                            'male' => 'Мужчина',
                            'female' => 'Женщина',
                        ])
                        ->required()
                        ->native(false),
                ])
                ->createOptionAction(function (Action $action) {
                    return $action
                        ->modalHeading('Создание клиента')
                        ->modalSubmitActionLabel('Создать клиента')
                        ->modalWidth('lg');
                }),
            Forms\Components\Hidden::make('client_address_id')  ->dehydrated(true),
            Forms\Components\Select::make('selected_address_id')
                ->label('Адрес доставки')
                ->placeholder('Выберите адрес')
                ->afterStateHydrated(function (Forms\Components\Select $component, ?\App\Models\Shop\Order $record, callable $set) {
                    if ($record && $record->client_address_id) {
                        $component->state((string) $record->client_address_id);

                        // 👇 Загрузим данные в addressForm
                        $address = \App\Models\Shop\ClientAddress::find($record->client_address_id);

                        if ($address) {
                            $set('address', $address->only([
                                'street',
                                'house',
                                'apartment',
                                'intercom',
                                'floor',
                                'entrance',
                                'zip',
                                'city',
                                'country',
                                'note',
                                'type',
                                'is_private_house',
                            ]));
                        }
                    }
                })


                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state !== '-1') {
                        $set('client_address_id', (int) $state); // ✅ Обновляем поле в Order
                    }
                    if (! $state || $state === '-1') {
                        $set('address', [
                            'street' => null,
                            'house' => null,
                            'apartment' => null,
                            'intercom' => null,
                            'floor' => null,
                            'entrance' => null,
                            'zip' => null,
                            'city' => null,
                            'country' => null,
                            'note' => null,
                            'type' => null,
                            'is_private_house' => false,
                        ]);
                    } else {
                        $address = \App\Models\Shop\ClientAddress::find($state);
                        if ($address) {
                            $set('address', $address->only([
                                'street',
                                'house',
                                'apartment',
                                'intercom',
                                'floor',
                                'entrance',
                                'zip',
                                'city',
                                'country',
                                'note',
                                'type',
                                'is_private_house',
                            ]));
                        }
                    }
                })
                ->options(function (callable $get) {
                    $clientId = $get('clients_id');

                    if (! $clientId) {
                        return [];
                    }

                    $addresses = \App\Models\Shop\ClientAddress::query()
                        ->where('client_id', $clientId)
                        ->get();

                    $final = collect(['-1' => 'Новый адрес'])
                        ->union(
                            $addresses->mapWithKeys(function ($address) {
                                $key = (string) $address->id;
                                $label = trim(implode(', ', array_filter([
                                    $address->street,
                                    $address->house,
                                    $address->apartment ? 'кв. ' . $address->apartment : null,
                                    $address->entrance ? 'подъезд ' . $address->entrance : null,
                                    $address->floor ? 'этаж ' . $address->floor : null,
                                    $address->intercom ? 'домофон ' . $address->intercom : null,
                                ])));

                              //  dump($key,$label);
                                return [$key => $label];// 👈 ключи как строки!
                            })
                        ) ; // 👈 сохраняет ключи!
                   // dump($final->keys()->all());
                    //dd($final); // ⬅️ Посмотрим, что реально вернётся
                   // dd($final->all());
                    return $final->all(); // потом это оставить ->toArray()
                })


                //  ->default(fn (?Order $record) => $record?->client_address_id)
                ->searchable()
                ->reactive()
                ->live()

              //  ->placeholder('Новый адрес')
                ->columnSpanFull(),

            Forms\Components\ToggleButtons::make('status')
                ->inline()
                ->label('Статус')
             //   ->options(OrderStatus::class)
             ->options(fn () => static::allowedStatuses()) // ← только разрешённые
                ->icons(collect(OrderStatus::cases())->mapWithKeys(
                    fn (OrderStatus $s) => [$s->value => $s->getIcon()]
                )->all())
                ->colors(collect(OrderStatus::cases())->mapWithKeys(
                    fn (OrderStatus $s) => [$s->value => $s->getColor()]
                )->all())
                ->required()
                ->disabled(fn () => empty(static::allowedStatuses())) // если ничего не можно — блокируем
              //  ->default('new')
                ->required(),

            Forms\Components\Select::make('currency')
                ->searchable()
                ->label('Валюта')
                ->options(\App\Models\Currency::pluck('name', 'code'))
                ->default('UAH')
                ->required(),
            Forms\Components\Hidden::make('order_total_dirty')
                ->dehydrated(false)
                ->reactive(),


            Forms\Components\MarkdownEditor::make('notes')
                ->label('Примечание')
                ->columnSpan('full'),
        ];
    }
// 1) Хелпер: можно ли ставить конкретный статус?
    protected static function canSetStatus(string $code): bool
    {
        $u = auth()->user();
        return $u?->can('set_order_status') || $u?->can("set_order_status_{$code}");
    }

    protected static function allowedStatuses(): array
    {
        // из enum -> [value => label]
        return collect(OrderStatus::cases())
            ->filter(fn (OrderStatus $s) => static::canSetStatus($s->value))
            ->mapWithKeys(fn (OrderStatus $s) => [$s->value => $s->getLabel()])
            ->all();
    }
    public static function getItemsRepeater(): Repeater
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        return Repeater::make('items')
            ->relationship()
            ->addActionLabel('Добавить товар')
            ->schema([
                Grid::make(12)
                     ->schema([
                         Forms\Components\Select::make('product_id')
                    ->label('Продкут/товар')
                    ->options(function () use ($defaultLocale) {
                        return Product::query()
                            ->where('in_stock', 1)
                            ->get()
                            ->mapWithKeys(fn($cat) => [
                                $cat->id => (
                                    json_decode($cat->getRawOriginal('title'), true)[$defaultLocale]
                                    ?? json_decode($cat->getRawOriginal('title'), true)[config('app.locale')]
                                ),
                            ])
                            ->toArray();
                    })
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        // Форсим оновлення placeholder через $set (можна пусто)
                        $set('order_total_dirty', now());
                        $set('unit_price', Product::find($state)?->price ?? 0);
                    })
                   // ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('unit_price', Product::find($state)?->price ?? 0))
                    ->distinct()
                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                             ->columnSpan(6)
                    ->searchable(),

                Forms\Components\TextInput::make('qty')
                    ->label('Количество')
                    ->numeric()
                    ->live(debounce: 250) // обновляет после задержки (как blur)
                    // обновляем итогокую сумму по корзине
                    ->afterStateUpdated(fn ($state, callable $get, callable $set) => $set('order_total', now()))
                    ->default(1)
                    ->columnSpan(2)
                    ->required(),

                Forms\Components\TextInput::make('unit_price')
                    ->label('Цена')
                   // ->disabled()
                    ->dehydrated()
                    ->numeric()
                    //->reactive()
                    ->live(debounce:500) // обновляет после задержки (как blur)
                    ->afterStateUpdated(fn ($state, callable $get, callable $set) => $set('order_total', now()))
                    ->required()
                    ->columnSpan(2),
                Forms\Components\Placeholder::make('item_total')
                    ->label('Сумма')
                    ->content(function (callable $get) {
                        $qty = (float) $get('qty') ?? 0;
                        $price = (float) $get('unit_price') ?? 0;
                        $modifiers = $get('modifiers') ?? [];
                        $modifiers = collect($modifiers)->map(fn($m) => is_array($m) ? $m : (array)$m);

                        $modifierSum = $modifiers->sum('price_modifier');

                        $total = $qty * ($price + $modifierSum);


                        return number_format($total, 2, ',', ' ') . ' грн';
                    })
                    ->reactive()
                    ->columnSpan(2),
            ]),
            //->columns(4),
                // ⬇️ Вариации и характеристики
                Repeater::make('modifiers')
                    ->relationship('modifiers')
                    ->label('Вариации / Характеристики')
                    ->schema([
                        Select::make('type')
                            ->label('Тип')
                            ->options([
                                'variation' => 'Вариация',
                                'characteristic' => 'Характеристика',
                            ])
                            ->reactive(),

                        Select::make('value_id')
                            ->label('Значение')
                            ->options(function (callable $get) {
                                $type = $get('type');
                                $productId = $get('../../product_id');

                                if (! $type || ! $productId) return [];

                                if ($type === 'variation') {
                                    return \App\Models\Shop\ProductVariation::where('product_id', $productId)
                                        ->with('variation')
                                        ->get()
                                        ->mapWithKeys(fn($v) => [
                                            $v->id => "{$v->variation->name} (+{$v->price}₴)",
                                        ])
                                        ->toArray();
                                }

                                if ($type === 'characteristic') {
                                    return \App\Models\Shop\CharacteristicValue::whereIn('id', function ($query) use ($productId) {
                                        $query->select('characteristic_value_id')
                                            ->from('product_characteristic_value')
                                            ->where('product_id', $productId)
                                            ->where('price_modifier', '>', 0);
                                    })
                                        ->with('characteristic')
                                        ->get()
                                        ->mapWithKeys(function ($val) {
                                            return [
                                                $val->id => "{$val->characteristic->name} - {$val->value}",
                                            ];
                                        })
                                        ->toArray();
                                }

                                return [];
                            })


                            ->searchable()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $type = $get('type');
                                $productId = $get('../../product_id');

                                if (! $type || ! $state || ! $productId) {
                                    return;
                                }

                                if ($type === 'variation') {
                                    $price = \DB::table('product_variation')
                                        ->where('product_id', $productId)
                                        ->where('id', $state)
                                        ->value('price');

                                    if ($price !== null) {
                                        $set('price_modifier', $price);
                                        $set('order_total', now());
                                    }
                                }

                                if ($type === 'characteristic') {
                                    $price = \DB::table('product_characteristic_value')
                                        ->where('product_id', $productId)
                                        ->where('characteristic_value_id', $state)
                                        ->value('price_modifier');

                                    if ($price !== null) {
                                        $set('price_modifier', $price);
                                        $set('order_total', now());
                                    }
                                }
                            })

                            ->afterStateHydrated(function (Select $component, $state, callable $get) {
                                $type = $get('type');
                                $productId = $get('../../product_id');

                                if (! $type || ! $state || ! $productId) {
                                    return;
                                }

                                if ($type === 'variation') {
                                    $variation = \App\Models\Shop\ProductVariation::with('variation')
                                        ->where('product_id', $productId)
                                        ->where('variation_id', $state)
                                        ->first()  ;

                                  //  dd($variation->variation->name,$productId,$state);
                                    if ($variation) {
                                        $component->state("{$variation->variation->name} (+{$variation->price}₴)");
                                    }
                                }

                                if ($type === 'characteristic') {
                                    $charVal = \App\Models\Shop\CharacteristicValue::with('characteristic')->find($state);
                                    if ($charVal) {
                                        $component->label("{$charVal->characteristic->name} - {$charVal->value}");
                                    }
                                }
                            })

                            ->reactive(),
                        Hidden::make('value_id')
                            ->required()
                            ,
                        TextInput::make('price_modifier')
                            ->label('Цена +')
                            ->numeric()
                            ->inputMode('decimal')
                            ->step('any')
                            ->default(0),
                    ])
                    ->defaultItems(0)
                    ->reorderable()
                    ->addActionLabel('Добавить модификатор')
                    ->columns(3)
                    ->columnSpanFull(), // ⬅️ ❗ Ключевой момент,
            ])
            ->columns(1)

            ->extraItemActions([
                Action::make('openProduct')
                    ->tooltip('Open product')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(function (array $arguments, Repeater $component): ?string {
                        $itemData = $component->getRawItemState($arguments['item']);

                        $product = Product::find($itemData['product_id']);

                        if (! $product) {
                            return null;
                        }

                        return ProductResource::getUrl('edit', ['record' => $product]);
                    }, shouldOpenInNewTab: true)
                    ->hidden(fn (array $arguments, Repeater $component): bool => blank($component->getRawItemState($arguments['item'])['product_id'])),
            ])
            ->orderColumn('sort')
            ->defaultItems(1)
            ->hiddenLabel()
            ->columns([
                'md' => 10,
            ])
            ->required();
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Номер заказа')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('clients.name')
                    ->searchable()
                    ->label('Клиент')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge(),
                Tables\Columns\TextColumn::make('currency')
                    ->label('Валюта')
                    //->getStateUsing(fn ($record): ?string => Currency::find($record->currency)?->code ?? null)
                    ->getStateUsing(function ($record){
                        $string = \App\Models\Currency::where('code', $record->currency)->value('name');
                       // dump($string);
                        return $string;
                    }
               )
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Сумма')
                    ->searchable()
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('UAH'),
                    ]),
                Tables\Columns\TextColumn::make('shipping_price')
                    ->label('Сумма доставки')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money(),
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата заказа')
                    ->date()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->placeholder(fn ($state): string => 'Dec 18, ' . now()->subYear()->format('Y')),
                        Forms\Components\DatePicker::make('created_until')
                            ->placeholder(fn ($state): string => now()->format('M d, Y')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators['created_from'] = 'Order from ' . Carbon::parse($data['created_from'])->toFormattedDateString();
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = 'Order until ' . Carbon::parse($data['created_until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->groupedBulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->action(function () {
                        Notification::make()
                            ->title('Now, now, don\'t be cheeky, leave some records for others to play with!')
                            ->warning()
                            ->send();
                    }),
            ])
            ->groups([
                Tables\Grouping\Group::make('created_at')
                    ->label('Дата заказа')
                    ->date()
                    ->collapsible(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
// показывает в меню слева сколько новых заказов
    public static function getNavigationBadge(): ?string
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = static::$model;

        return (string) $modelClass::where('status', 'new')->count();
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    public static function getWidgets(): array
    {
        return [
            OrderStats::class,
        ];
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
