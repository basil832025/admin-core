<?php

namespace App\Filament\Resources\Callcenter;

use App\Filament\Resources\Callcenter\OrderResource\Pages;
use App\Filament\Resources\Callcenter\OrderResource\Widgets;
use App\Filament\Resources\Shop\OrderResource as ShopOrderResource;
use App\Models\Shop\Client;
use App\Models\Shop\OrderItem;
use App\Models\Shop\ProductCharacteristicValue;
use App\Models\Callcenter\Order;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Livewire\Component as LivewireComponent;

class OrderResource extends ShopOrderResource
{
    protected static ?string $model = Order::class;
    protected static ?string $slug = 'callcenter/orders';
    protected static ?string $navigationGroup = null;
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationIcon = 'heroicon-o-phone';

    public static function getNavigationLabel(): string
    {
        return __('callcenter.nav.navigation_label');
    }

    protected static function canAccessModule(): bool
    {
        $user = auth('admin')->user();

        if (! $user instanceof \App\Models\User) {
            return false;
        }

        $permissions = [
            'access_callcenter_orders',
            'view_any_callcenter::order',
            'view_callcenter::order',
        ];

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccessModule();
    }

    public static function canViewAny(): bool
    {
        return static::canAccessModule();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->components([
                Group::make()
                    ->schema([
                        Section::make(__('order.sections.order_items'))
                            ->schema([
                                static::getItemsRepeater(),
                            ]),
                        Section::make(__('order.sections.sum_only'))
                            ->schema(static::getCallcenterTotalsSchema())
                            ->columns(),
                    ])
                    ->columnSpan(['lg' => 9]),

                Group::make()
                    ->schema(static::getCallcenterInfoTabSchema())
                    ->columnSpan(['lg' => 9]),

                Group::make()
                    ->schema([
                        Tabs::make('callcenter_right_tabs')
                            ->tabs(static::getCallcenterRightTabs())
                            ->activeTab(fn (): int => request()->routeIs('filament.admin.resources.callcenter.orders.create') ? 2 : 1)
                            ->contained(false),

                        Section::make(__('order.sections.statuses'))
                            ->schema(static::getCallcenterStatusesSchema()),

                        Section::make(__('order.sections.metadata'))
                            ->schema(static::getCallcenterMetadataSchema()),
                    ])
                    ->columnSpan(['lg' => 6]),
            ])
            ->columns(24);
    }

    protected static function getCallcenterInfoTabSchema(): array
    {
        $schema = parent::getInfoTabSchema();

        foreach ($schema as $component) {
            if (! $component instanceof Grid) {
                continue;
            }

            $fields = $component->getChildComponents();
            $clientsIndex = null;

            foreach ($fields as $index => $field) {
                if (! method_exists($field, 'getName')) {
                    continue;
                }

                $name = $field->getName();

                if ($name === 'number' && $field instanceof TextInput) {
                    $field
                        ->hidden()
                        ->dehydrated(false);

                    continue;
                }

                if ($name === 'clients_id' && $field instanceof Select) {
                    $clientsIndex = $index;

                    $field
                        ->label(__('order.fields.client'))
                        ->extraAttributes([
                            'class' => 'callcenter-client-select',
                        ])
                        ->allowHtml()
                        ->live()
                        ->reactive()
                        ->preload(false)
                        ->columnSpan(8)
                        ->getOptionLabelUsing(function ($value) {
                            if (! $value) {
                                return null;
                            }

                            $client = Client::query()->select('id', 'name', 'phone')->find($value);

                            return $client ? ($client->phone_pretty . ' · ' . e($client->name)) : null;
                        })
                        ->getOptionLabelFromRecordUsing(fn (Client $client) => $client->phone_pretty . ' · ' . e($client->name))
                        ->getSearchResultsUsing(function (string $search): array {
                            $digits = preg_replace('/\D+/', '', $search);

                            if (strlen($digits) < 3) {
                                return [];
                            }

                            return Client::query()
                                ->select('id', 'name', 'phone')
                                ->whereRaw("REGEXP_REPLACE(phone, '[^0-9]', '') LIKE ?", ["%{$digits}%"])
                                ->orderBy('name')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(function (Client $client) use ($digits): array {
                                    $phoneDigits = preg_replace('/\D+/', '', (string) $client->phone);
                                    $highlighted = str_replace(
                                        $digits,
                                        '<span style="background:#fde68a;padding:0 2px;border-radius:3px;">' . $digits . '</span>',
                                        $phoneDigits
                                    );

                                    return [
                                        $client->id => $client->phone_pretty
                                            . ' · '
                                            . e($client->name)
                                            . ' <span style="color:#64748b;font-size:11px;">['
                                            . $highlighted
                                            . ']</span>',
                                    ];
                                })
                                ->toArray();
                        })
                        ->afterStateUpdated(function ($state, Set $set) {
                            $phone = $state
                                ? Client::query()->whereKey($state)->value('phone')
                                : null;

                            $set('incoming_phone', $phone ?? '');
                            $set('history_refresh', (string) microtime(true));
                        })
                        ->afterStateHydrated(function (Get $get, Set $set) {
                            $id = $get('clients_id');

                            $set('incoming_phone', $id
                                ? (Client::query()->whereKey($id)->value('phone') ?? '')
                                : ''
                            );
                            $set('history_refresh', (string) microtime(true));
                        })
                        ->createOptionAction(fn (FormAction $action) => $action
                            ->mountUsing(function (Form $form, LivewireComponent $livewire): void {
                                $incomingPhone = static::extractPhoneFromSuggestion((string) data_get($livewire, 'data.incoming_phone', ''));

                                $form->fill([
                                    'phone' => $incomingPhone,
                                    'is_active' => true,
                                ]);
                            })
                        );

                    continue;
                }

                if ($name === 'client_phone_view' && $field instanceof TextInput) {
                    $field
                        ->hidden()
                        ->dehydrated(false);
                }
            }

            if ($clientsIndex !== null) {
                $phoneInput = TextInput::make('incoming_phone')
                    ->label(__('order.fields.phone'))
                    ->placeholder('+380')
                    ->tel()
                    ->extraInputAttributes([
                        'class' => 'callcenter-phone-input',
                        'autocomplete' => 'off',
                    ])
                    ->dehydrated(false)
                    ->columnSpan(4)
                    ->live(debounce: 300)
                    ->afterStateUpdated(function (?string $state, Set $set) {
                        $phone = static::extractPhoneFromSuggestion($state);

                        if ($phone !== (string) $state) {
                            $set('incoming_phone', $phone);
                        }

                        $digits = static::normalizePhone($phone);

                        if (strlen($digits) < 10) {
                            $set('clients_id', null);
                            return;
                        }

                        $clientId = static::findClientIdByPhone($digits);

                        $set('clients_id', $clientId);
                        $set('history_refresh', (string) microtime(true));
                    });

                array_splice($fields, $clientsIndex, 0, [$phoneInput]);
            }

            $fields[] = Hidden::make('history_refresh')
                ->default((string) microtime(true))
                ->dehydrated(false);

            $component->schema($fields);
        }

        foreach ($schema as $component) {
            if (! method_exists($component, 'getName')) {
                continue;
            }

            if ($component->getName() !== 'selected_address_id' || ! $component instanceof Select) {
                continue;
            }

            $component->default('-1');
        }

        return $schema;
    }

    protected static function normalizePhone(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }

    protected static function extractPhoneFromSuggestion(?string $value): string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return '';
        }

        if (str_contains($raw, ' - ')) {
            $raw = trim(explode(' - ', $raw, 2)[0] ?? '');
        }

        return $raw;
    }

    protected static function findClientIdByPhone(string $digits): ?int
    {
        $digits = static::normalizePhone($digits);

        if (strlen($digits) < 10) {
            return null;
        }

        $tail10 = substr($digits, -10);

        $clients = Client::query()
            ->select('id', 'phone')
            ->whereRaw("REGEXP_REPLACE(phone, '[^0-9]', '') LIKE ?", ["%{$tail10}%"])
            ->limit(50)
            ->get();

        if ($clients->isEmpty()) {
            return null;
        }

        $exact = $clients->first(function (Client $client) use ($digits): bool {
            return static::normalizePhone($client->phone) === $digits;
        });

        if ($exact) {
            return $exact->id;
        }

        $tailMatches = $clients->filter(function (Client $client) use ($tail10): bool {
            return str_ends_with(static::normalizePhone($client->phone), $tail10);
        })->values();

        if ($tailMatches->count() === 1) {
            return $tailMatches->first()->id;
        }

        return null;
    }

    public static function getItemsRepeater(): Repeater
    {
        $defaultLocale = config('app.locale', 'uk');

        return TableRepeater::make('items')
            ->relationship()
            ->label('')
            ->extraAttributes([
                'class' => 'callcenter-items-table',
            ])
            ->afterStateUpdated(function (Set $set, Get $get): void {
                static::recalculateShippingFromCurrentForm($get, $set);
            })
            ->addActionLabel(__('order.actions.add_item'))
            ->headers([
                Header::make('product_id')
                    ->label(__('order.fields.product'))
                    ->width('54%')
                    ->markAsRequired(),
                Header::make('unit')
                    ->label('Од.')
                    ->align('center')
                    ->width('10%'),
                Header::make('qty')
                    ->label('Кол-во')
                    ->align('center')
                    ->width('10%')
                    ->markAsRequired(),
                Header::make('unit_price')
                    ->label(__('order.fields.price'))
                    ->align('center')
                    ->width('12%')
                    ->markAsRequired(),
                Header::make('item_total')
                    ->label(__('order.fields.sum'))
                    ->align('center')
                    ->width('14%'),
                Header::make('id')
                    ->label('')
                    ->width('0%'),
            ])
            ->streamlined()
            ->showLabels(false)
            ->reorderable(false)
            ->deleteAction(fn ($action) => $action->action(function (array $arguments, Repeater $component, $livewire): void {
                $items = $component->getState();
                $itemKey = $arguments['item'] ?? null;

                if ($itemKey === null || ! isset($items[$itemKey])) {
                    return;
                }

                $row = is_array($items[$itemKey]) ? $items[$itemKey] : (array) $items[$itemKey];
                $orderItemId = (int) ($row['id'] ?? 0);

                if ($orderItemId > 0 && isset($livewire->record) && $livewire->record?->exists) {
                    OrderItem::query()->whereKey($orderItemId)->delete();

                    app(\App\Services\OrderPricing::class)->recalc($livewire->record);
                    $livewire->record->recalculateTotalPrice();
                }

                unset($items[$itemKey]);
                $component->state($items);
                $component->callAfterStateUpdated();

                if (isset($livewire->data) && is_array($livewire->data)) {
                    $livewire->data['delivery_price_auto'] = 'items_delete_' . microtime(true);
                }
            }))
            ->defaultItems(0)
            ->schema([
                Hidden::make('id')->dehydrated(false),

                Select::make('product_id')
                    ->extraAttributes([
                        'class' => 'callcenter-inline-select',
                    ])
                    ->searchable()
                    ->preload()
                    ->optionsLimit(50)
                    ->getSearchResultsUsing(function (string $search) use ($defaultLocale) {
                        $search = trim($search);

                        $query = \App\Models\Shop\Product::query()
                            ->select(['id', 'title', 'short_name', 'parent_id', 'sort', 'sku'])
                            ->where('in_stock', 1);

                        if ($search !== '') {
                            $like = "%{$search}%";

                            $query->where(function ($w) use ($like, $defaultLocale) {
                                $w->whereRaw("JSON_VALID(title) AND JSON_UNQUOTE(JSON_EXTRACT(title, ?)) LIKE ?", ["$.{$defaultLocale}", $like])
                                    ->orWhereRaw("NOT JSON_VALID(title) AND title LIKE ?", [$like]);
                            });

                            $query->orWhere(function ($w) use ($like, $defaultLocale) {
                                $w->whereRaw("JSON_VALID(short_name) AND JSON_UNQUOTE(JSON_EXTRACT(short_name, ?)) LIKE ?", ["$.{$defaultLocale}", $like])
                                    ->orWhereRaw("NOT JSON_VALID(short_name) AND short_name LIKE ?", [$like]);
                            });

                            $query->orWhere('sku', 'like', $like);
                        }

                        $query->orderByRaw("COALESCE(parent_id, id) ASC")
                            ->orderByRaw("CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END ASC")
                            ->orderBy('sort')
                            ->orderBy('id')
                            ->limit(50);

                        $items = $query->get();

                        return $items->mapWithKeys(fn ($product) => [
                            $product->id => static::productLabel($product, $defaultLocale),
                        ])->toArray();
                    })
                    ->getOptionLabelUsing(function ($value) use ($defaultLocale) {
                        if (! $value) {
                            return null;
                        }

                        $product = \App\Models\Shop\Product::query()
                            ->select(['id', 'title', 'short_name', 'parent_id', 'sku'])
                            ->find($value);

                        return $product ? static::productLabel($product, $defaultLocale) : null;
                    })
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, Set $set) {
                        $product = \App\Models\Shop\Product::find($state);

                        if (! $product) {
                            $set('product_id', null);
                            $set('unit_price', 0);

                            return;
                        }

                        $set('unit_price', number_format((float) ($product->price ?? 0), 1, '.', ''));
                    }),

                Placeholder::make('unit')
                    ->label('')
                    ->hiddenLabel()
                    ->extraAttributes([
                        'class' => 'text-center callcenter-unit-text',
                    ])
                    ->content(function (Get $get) {
                        $productId = (int) ($get('product_id') ?? 0);

                        if (! $productId) {
                            return '-';
                        }

                        return static::getProductUnitLabel($productId);
                    }),

                TextInput::make('qty')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->required()
                    ->live(debounce: 250)
                    ->afterStateUpdated(function ($state, Set $set, Get $get, $livewire): void {
                        static::persistOrderItemInlineChanges($get, ['qty' => max(1, (int) $state)], $livewire);
                        static::recalculateShippingFromCurrentForm($get, $set);
                    }),

                TextInput::make('unit_price')
                    ->extraInputAttributes([
                        'class' => 'callcenter-inline-input-price',
                    ])
                    ->rule('numeric')
                    ->inputMode('decimal')
                    ->required()
                    ->live(debounce: 300)
                    ->afterStateUpdated(function ($state, Set $set, Get $get, $livewire): void {
                        $normalized = (float) str_replace(',', '.', (string) $state);
                        static::persistOrderItemInlineChanges($get, ['unit_price' => $normalized], $livewire);
                        static::recalculateShippingFromCurrentForm($get, $set);
                    }),

                Placeholder::make('item_total')
                    ->label('')
                    ->hiddenLabel()
                    ->extraAttributes([
                        'class' => 'text-right',
                    ])
                    ->content(function (Get $get) {
                        $qty = (float) ($get('qty') ?? 0);
                        $price = (float) ($get('unit_price') ?? 0);

                        return number_format($qty * $price, 1, ',', ' ');
                    }),
            ])
            ->required();
    }

    protected static function getCallcenterTotalsSchema(): array
    {
        $sidebar = parent::getSidebarSchema();
        $amountSection = $sidebar[0] ?? null;

        if (! $amountSection instanceof Section) {
            return [];
        }

        $moved = [
            'order_total_right',
            'ui_adjustments_list',
            'ui_loyalty_spent',
            'shipping_price',
            'total_after_discount',
        ];

        return array_values(array_filter(
            $amountSection->getChildComponents(),
            fn ($component) => method_exists($component, 'getName')
                && in_array($component->getName(), $moved, true)
        ));
    }

    protected static function getCallcenterRightTabs(): array
    {
        return [
            Tab::make(__('order.sections.discounts_only'))
                ->schema([
                    Section::make(__('order.sections.discounts_only'))
                        ->schema(static::getCallcenterDiscountsSchema()),
                ]),

            Tab::make(__('order.sections.history_orders'))
                ->schema([
                    Section::make(__('order.sections.history_orders'))
                        ->schema(static::getCallcenterHistorySchema()),
                ]),
        ];
    }

    protected static function getCallcenterDiscountsSchema(): array
    {
        $sidebar = parent::getSidebarSchema();
        $amountSection = $sidebar[0] ?? null;

        if (! $amountSection instanceof Section) {
            return [];
        }

        $moved = [
            'order_total_right',
            'ui_adjustments_list',
            'ui_loyalty_spent',
            'shipping_price',
            'total_after_discount',
        ];

        return array_values(array_filter(
            $amountSection->getChildComponents(),
            fn ($component) => ! (method_exists($component, 'getName')
                && in_array($component->getName(), $moved, true))
        ));
    }

    protected static function getCallcenterStatusesSchema(): array
    {
        $sidebar = parent::getSidebarSchema();

        foreach ($sidebar as $section) {
            if (! $section instanceof Section) {
                continue;
            }

            $components = $section->getChildComponents();
            $hasStatus = collect($components)
                ->contains(fn ($component) => method_exists($component, 'getName') && $component->getName() === 'status_ui');

            if ($hasStatus) {
                return $components;
            }
        }

        return [];
    }

    protected static function getCallcenterHistorySchema(): array
    {
        return [
            View::make('filament.callcenter.order-history-sidebar')
                ->key(fn (Get $get, ?Order $record): string => 'cc-history-'
                    . ($get('clients_id') ?? $record?->clients_id ?? 'none')
                    . '-'
                    . ($get('history_refresh') ?? '0'))
                ->viewData(function (Get $get, ?Order $record): array {
                    $clientId = (int) ($get('clients_id') ?? $record?->clients_id ?? 0);
                    $excludeOrderId = (int) ($record?->id ?? 0);

                    $orders = $clientId
                        ? Order::query()
                            ->where('clients_id', $clientId)
                            ->when($excludeOrderId > 0, fn ($q) => $q->whereKeyNot($excludeOrderId))
                            ->with(['clientAddress:id,street,house,apartment,city,intercom,floor,entrance,note,is_private_house,type,latitude,longitude,street_place_id,formatted_address'])
                            ->latest('created_at')
                            ->limit(15)
                            ->get(['id', 'number', 'created_at', 'client_address_id'])
                        : collect();

                    return ['orders' => $orders];
                }),
        ];
    }

    protected static function getCallcenterMetadataSchema(): array
    {
        $sidebar = parent::getSidebarSchema();

        foreach ($sidebar as $section) {
            if (! $section instanceof Section) {
                continue;
            }

            $components = $section->getChildComponents();
            $hasCreatedAt = collect($components)
                ->contains(fn ($component) => method_exists($component, 'getName') && $component->getName() === 'created_at');

            if ($hasCreatedAt) {
                return $components;
            }
        }

        return [];
    }

    public static function getRelations(): array
    {
        return [];
    }

    protected static function getProductUnitLabel(int $productId): string
    {
        static $cache = [];

        if (isset($cache[$productId])) {
            return $cache[$productId];
        }

        $product = \App\Models\Shop\Product::query()
            ->select(['id', 'parent_id'])
            ->find($productId);

        $productIds = array_values(array_unique(array_filter([
            $productId,
            (int) ($product?->parent_id ?? 0),
        ])));

        $priority = ['rozmir-pirogiv', 'rozmiri-insi', 'vaga-grami', 'vaga-setiv', 'vaga'];

        $rows = ProductCharacteristicValue::query()
            ->with([
                'characteristic:id,slug',
                'characteristicValue:id,characteristic_id,value',
                'characteristicValue.characteristic:id,slug',
            ])
            ->whereIn('product_id', $productIds)
            ->get();

        foreach ($productIds as $pid) {
            $productRows = $rows->where('product_id', $pid)->values();

            foreach ($priority as $slug) {
                $match = $productRows->first(function (ProductCharacteristicValue $row) use ($slug): bool {
                    $rowSlug = $row->characteristic?->slug
                        ?? $row->characteristicValue?->characteristic?->slug;

                    return $rowSlug === $slug;
                });

                if (! $match) {
                    continue;
                }

                $value = static::resolveUnitValueFromRow($match);

                if ($value !== '') {
                    return $cache[$productId] = $value;
                }
            }
        }

        \Log::info('Callcenter unit lookup: no unit found', [
            'product_id' => $productId,
            'checked_product_ids' => $productIds,
            'rows' => $rows->map(function (ProductCharacteristicValue $row) {
                return [
                    'product_id' => $row->product_id,
                    'characteristic_slug' => $row->characteristic?->slug,
                    'characteristic_value_slug' => $row->characteristicValue?->characteristic?->slug,
                    'value_text' => $row->value_text,
                    'value_number' => $row->value_number,
                    'value_label' => $row->characteristicValue?->label,
                ];
            })->values()->all(),
        ]);

        return $cache[$productId] = '-';
    }

    protected static function recalculateShippingFromCurrentForm(Get $get, Set $set): void
    {
        $selfPickup = (bool) ($get('self_pickup') ?? false);

        if ($selfPickup) {
            $set('shipping_price', 0);
            $set('delivery_price_auto', 'items_pickup_' . microtime(true));
            return;
        }

        $address = (array) ($get('address') ?? []);
        $lat = $address['latitude'] ?? null;
        $lng = $address['longitude'] ?? null;

        if (! $lat || ! $lng) {
            return;
        }

        $orderTotal = (float) parent::calcBaseTotalFromGet($get);

        $tempOrder = new Order();
        $tempOrder->address = $address;
        $tempOrder->self_pickup = false;

        $delivery = app(\App\Services\DeliveryCalculationService::class)
            ->calculateDelivery($tempOrder, $orderTotal);

        $set('shipping_price', (float) ($delivery['price'] ?? 0));
        $set('delivery_price_auto', 'items_recalc_' . microtime(true));
    }

    protected static function persistOrderItemInlineChanges(Get $get, array $changes, $livewire): void
    {
        if (! isset($livewire->record) || ! $livewire->record?->exists) {
            return;
        }

        $orderItemId = (int) ($get('id') ?? 0);

        if ($orderItemId <= 0) {
            return;
        }

        $item = OrderItem::query()->whereKey($orderItemId)->first();

        if (! $item) {
            return;
        }

        $item->update($changes);

        app(\App\Services\OrderPricing::class)->recalc($livewire->record);
        $livewire->record->recalculateTotalPrice();
    }

    protected static function resolveUnitValueFromRow(ProductCharacteristicValue $row): string
    {
        $value = trim((string) ($row->value_text ?? ''));

        if ($value !== '') {
            return $value;
        }

        if ($row->value_number !== null) {
            return (string) $row->value_number;
        }

        if ($row->characteristicValue) {
            $label = trim((string) ($row->characteristicValue->label ?? ''));

            if ($label !== '') {
                return $label;
            }

            $raw = $row->characteristicValue->getRawOriginal('value');

            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);

                if (is_array($decoded)) {
                    $locale = app()->getLocale();

                    return trim((string) (
                        $decoded[$locale]
                        ?? $decoded['uk']
                        ?? $decoded['ru']
                        ?? $decoded['en']
                        ?? (count($decoded) ? reset($decoded) : '')
                    ));
                }

                return trim($raw, " \t\n\r\0\x0B\"");
            }
        }

        return '';
    }

    public static function getWidgets(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit'   => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
