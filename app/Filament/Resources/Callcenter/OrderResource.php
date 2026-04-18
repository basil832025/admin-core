<?php

namespace App\Filament\Resources\Callcenter;

use App\Enums\PrintOperationCode;
use App\Enums\PaymentMethodEnum;
use App\Filament\Resources\Callcenter\OrderResource\Pages;
use App\Filament\Resources\Callcenter\OrderResource\Widgets;
use App\Filament\Resources\Shop\OrderResource as ShopOrderResource;
use App\Services\PrintNode\KitchenDuplicatePrintService;
use App\Models\Shop\Client;
use App\Models\Shop\OrderItem;
use App\Models\Shop\ProductCharacteristicValue;
use App\Models\Shop\TimeDiscount;
use App\Models\Callcenter\Order;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Log;
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
        $historyInjected = false;

        foreach ($schema as $component) {
            if (! $component instanceof Grid) {
                continue;
            }

            $fields = $component->getChildComponents();
            $fields = array_values(array_filter($fields, function ($field): bool {
                return ! (method_exists($field, 'getName') && $field->getName() === 'incoming_phone');
            }));
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
                    ->helperText(function (Get $get) {
                        $clientId = (int) ($get('clients_id') ?? 0);

                        if ($clientId <= 0) {
                            return null;
                        }

                        $client = Client::query()
                            ->with('group')
                            ->find($clientId);

                        $group = $client?->group;
                        $groupName = $group?->display_name;

                        if (! $groupName) {
                            return null;
                        }

                        $isBlacklist = (bool) ($group->is_blacklist ?? false);
                        $style = $isBlacklist
                            ? 'display:inline-block;margin-top:2px;background:#fee2e2;color:#b91c1c;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;'
                            : 'display:inline-block;margin-top:2px;background:#eff6ff;color:#1d4ed8;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;';

                        $label = $isBlacklist
                            ? '👎 Группа клиента: ' . $groupName
                            : 'Группа клиента: ' . $groupName;

                        return new \Illuminate\Support\HtmlString(
                            '<span style="' . e($style) . '">'
                            . e($label)
                            . '</span>'
                        );
                    })
                    ->afterStateUpdated(function ($state, Set $set) {
                        $phone = $state
                            ? Client::query()->whereKey($state)->value('phone')
                            : null;

                        if ($phone) {
                            $set('incoming_phone', $phone);
                        }

                        $set('history_refresh', (string) microtime(true));
                    })
                    ->afterStateHydrated(function (Get $get, Set $set) {
                        $id = $get('clients_id');

                        if ($id) {
                            $set('incoming_phone', Client::query()->whereKey($id)->value('phone') ?? '');
                        }

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

            $hasSourceField = collect($fields)
                ->contains(fn ($field) => method_exists($field, 'getName') && $field->getName() === 'source_id');

            if (! $hasSourceField) {
                $fields[] = Hidden::make('source_id')
                    ->dehydrated(true);
            }

            if (! $historyInjected) {
                $fields[] = Hidden::make('history_refresh')
                    ->default((string) microtime(true))
                    ->dehydrated(false);

                $historyInjected = true;
            }

            $component->schema($fields);
        }

        $notesPos = null;

        foreach ($schema as $index => $component) {
            if (method_exists($component, 'getName') && $component->getName() === 'notes') {
                $notesPos = $index;
                break;
            }
        }

        $extraNotesFields = [
            Textarea::make('kitchen_note')
                ->label('Примечание для кухни')
                ->placeholder('Общее примечание по заказу для поваров')
                ->rows(3)
                ->columnSpanFull(),

            Textarea::make('courier_comment')
                ->label('Комментарий курьера')
                ->placeholder('Комментарий во время/после доставки')
                ->rows(3)
                ->columnSpanFull(),
        ];

        if ($notesPos !== null) {
            array_splice($schema, $notesPos + 1, 0, $extraNotesFields);
        } else {
            $schema = array_merge($schema, $extraNotesFields);
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
                Header::make('kitchen_note_action')
                    ->label('')
                    ->align('center')
                    ->width('4%'),
                Header::make('product_id')
                    ->label(__('order.fields.product'))
                    ->width('44%')
                    ->markAsRequired(),
                Header::make('unit')
                    ->label('Од.')
                    ->align('center')
                    ->width('8%'),
                Header::make('qty')
                    ->label('Кол-во')
                    ->align('center')
                    ->width('8%')
                    ->markAsRequired(),
                Header::make('unit_price')
                    ->label(__('order.fields.price'))
                    ->align('center')
                    ->width('10%')
                    ->markAsRequired(),
                Header::make('item_total')
                    ->label(__('order.fields.sum'))
                    ->align('center')
                    ->width('10%'),
                Header::make('time_discount_marker')
                    ->label(__('order.columns.discount'))
                    ->align('center')
                    ->width('16%'),
                Header::make('id')
                    ->label('')
                    ->width('0%'),
                Header::make('kitchen_note')
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

                Placeholder::make('kitchen_note_action')
                    ->label('')
                    ->hiddenLabel()
                    ->extraAttributes([
                        'class' => 'text-center',
                    ])
                    ->content(function (Get $get) {
                        $note = trim((string) ($get('kitchen_note') ?? ''));
                        $noteEscaped = e($note);
                        $orderItemId = (int) ($get('id') ?? 0);
                        $buttonClass = $note !== ''
                            ? 'callcenter-kitchen-note-btn is-active'
                            : 'callcenter-kitchen-note-btn';

                        return new \Illuminate\Support\HtmlString(
                            '<div x-data="{ open: false }" class="relative flex justify-center">'
                            . '<button type="button" class="' . $buttonClass . '" title="Примечание для кухни" @click.prevent="open = !open">+</button>'
                            . '<div x-show="open" x-cloak @click.outside="open = false" class="callcenter-kitchen-note-popover">'
                            . '<textarea class="callcenter-kitchen-note-textarea" rows="4" placeholder="Например: без лука, хорошо пропечь, двойная начинка">' . $noteEscaped . '</textarea>'
                            . '<div class="callcenter-kitchen-note-actions">'
                            . '<button type="button" class="callcenter-kitchen-note-save" data-order-item-id="' . $orderItemId . '" @click.prevent.stop="window.callcenterHandleKitchenNoteSave($el, $wire); open = false;">Сохранить</button>'
                            . '<button type="button" class="callcenter-kitchen-note-cancel" @click.prevent.stop="open = false">Закрыть</button>'
                            . '</div>'
                            . '</div>'
                            . '</div>'
                        );
                    }),

                Hidden::make('kitchen_note')
                    ->live(debounce: 400)
                    ->afterStateUpdated(function ($state, Get $get, $livewire): void {
                        $note = trim((string) $state);

                        static::persistOrderItemInlineChanges(
                            $get,
                            ['kitchen_note' => $note !== '' ? $note : null],
                            $livewire
                        );
                    }),

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
                    ->content(function (Get $get): HtmlString {
                        $productId = (int) ($get('product_id') ?? 0);

                        if (! $productId) {
                            return new HtmlString('<span class="callcenter-unit-with-tooltip"><span class="callcenter-unit-value">-</span><span class="callcenter-unit-tooltip" role="tooltip">Описание отсутствует</span></span>');
                        }

                        $unit = static::getProductUnitLabel($productId);
                        $description = static::getProductDescriptionForTooltip($productId);

                        return new HtmlString(
                            '<span class="callcenter-unit-with-tooltip">'
                            . '<span class="callcenter-unit-value">' . e($unit) . '</span>'
                            . '<span class="callcenter-unit-tooltip" role="tooltip">' . e($description) . '</span>'
                            . '</span>'
                        );
                    }),

                TextInput::make('qty')
                    ->extraFieldWrapperAttributes([
                        'class' => 'callcenter-inline-editable-wrapper callcenter-inline-qty-wrapper',
                    ])
                    ->extraInputAttributes([
                        'class' => 'callcenter-inline-input-qty',
                    ])
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
                    ->extraFieldWrapperAttributes([
                        'class' => 'callcenter-inline-editable-wrapper callcenter-inline-price-wrapper',
                    ])
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
                        'class' => 'text-right callcenter-inline-item-total',
                    ])
                    ->content(function (Get $get) {
                        $qty = (float) ($get('qty') ?? 0);
                        $price = (float) ($get('unit_price') ?? 0);

                        return number_format($qty * $price, 1, ',', ' ');
                    }),

                Placeholder::make('time_discount_marker')
                    ->label('')
                    ->hiddenLabel()
                    ->extraAttributes([
                        'class' => 'text-center',
                    ])
                    ->content(function (Get $get): HtmlString {
                        $orderItemId = (int) ($get('id') ?? 0);
                        if ($orderItemId <= 0) {
                            return new HtmlString('<span style="color:#9ca3af;">—</span>');
                        }

                        $orderId = static::resolveOrderIdByItemId($orderItemId);
                        if (! $orderId) {
                            return new HtmlString('<span style="color:#9ca3af;">—</span>');
                        }

                        $timeDiscountId = static::resolveSelectedTimeDiscountId($get, $orderId);
                        if (! $timeDiscountId) {
                            return new HtmlString('<span style="color:#9ca3af;">—</span>');
                        }

                        $map = static::buildTimeDiscountRecipientsMap($orderId, $timeDiscountId);
                        $entry = $map[$orderItemId] ?? null;
                        $count = (int) data_get($entry, 'units', 0);
                        $amount = (float) data_get($entry, 'amount', 0);

                        if ($count <= 0) {
                            return new HtmlString('<span style="color:#9ca3af;">—</span>');
                        }

                        $amountText = '- ' . number_format($amount, 2, ',', ' ');
                        if ($count > 1) {
                            $amountText .= ' ×' . $count;
                        }

                        return new HtmlString(
                            '<span style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;background:#FFF7ED;color:#C2410C;font-size:11px;font-weight:700;">'
                            . e($amountText)
                            . '</span>'
                        );
                    }),
            ])
            ->required();
    }

    protected static function resolveOrderIdByItemId(int $orderItemId): ?int
    {
        static $cache = [];

        if (array_key_exists($orderItemId, $cache)) {
            return $cache[$orderItemId];
        }

        $orderId = (int) (OrderItem::query()->whereKey($orderItemId)->value('shop_order_id') ?? 0);

        return $cache[$orderItemId] = ($orderId > 0 ? $orderId : null);
    }

    protected static function resolveSelectedTimeDiscountId(Get $get, int $orderId): ?int
    {
        $paths = [
            '../../ui_time_discount_id',
            '../ui_time_discount_id',
            'ui_time_discount_id',
            '../../../ui_time_discount_id',
        ];

        foreach ($paths as $path) {
            $raw = $get($path);
            $id = is_numeric($raw) ? (int) $raw : 0;
            if ($id > 0) {
                return $id;
            }
        }

        static $fallbackCache = [];
        if (array_key_exists($orderId, $fallbackCache)) {
            return $fallbackCache[$orderId];
        }

        $meta = DB::table('bs_shop_order_adjustments')
            ->where('shop_order_id', $orderId)
            ->whereNull('shop_order_item_id')
            ->where('type', 'time')
            ->orderByDesc('id')
            ->value('meta');

        $metaArr = is_string($meta) ? json_decode($meta, true) : (is_array($meta) ? $meta : []);
        $id = (int) (data_get($metaArr, 'id') ?? data_get($metaArr, 'time_discount_id') ?? 0);

        return $fallbackCache[$orderId] = ($id > 0 ? $id : null);
    }

    /**
     * @return array<int,array{units:int,amount:float}> [order_item_id => ['units' => int, 'amount' => float]]
     */
    protected static function buildTimeDiscountRecipientsMap(int $orderId, int $discountId): array
    {
        static $cache = [];
        $cacheKey = $orderId . ':' . $discountId;

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $discount = TimeDiscount::query()->find($discountId);
        if (! $discount || ! $discount->is_active) {
            return $cache[$cacheKey] = [];
        }

        $percent = (float) ($discount->percent ?? 0);
        $eachN = (int) ($discount->nth_item ?? 0);
        if ($percent <= 0 || $eachN < 1) {
            return $cache[$cacheKey] = [];
        }

        $units = [];

        $items = OrderItem::query()
            ->where('shop_order_id', $orderId)
            ->with(['product.categories', 'product.characteristicValues'])
            ->orderBy('id')
            ->get();

        foreach ($items as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }

            if (! static::matchesTimeDiscountScope($discount, $item)) {
                continue;
            }

            $qty = (int) ($item->qty ?? 0);
            $price = (float) ($item->unit_price ?? 0);
            if ($qty <= 0 || $price <= 0) {
                continue;
            }

            for ($i = 0; $i < $qty; $i++) {
                $units[] = [
                    'item_id' => (int) $item->id,
                    'price' => $price,
                ];
            }
        }

        if (count($units) === 0) {
            return $cache[$cacheKey] = [];
        }

        $result = [];

        if ($eachN === 1) {
            foreach ($units as $unit) {
                $itemId = (int) $unit['item_id'];
                if (! isset($result[$itemId])) {
                    $result[$itemId] = ['units' => 0, 'amount' => 0.0];
                }

                $result[$itemId]['units'] += 1;
                $result[$itemId]['amount'] += ((float) $unit['price']) * ($percent / 100);
            }

            foreach ($result as &$row) {
                $row['amount'] = round((float) $row['amount'], 2);
            }
            unset($row);

            return $cache[$cacheKey] = $result;
        }

        if (count($units) < $eachN) {
            return $cache[$cacheKey] = [];
        }

        if (intdiv(count($units), $eachN) <= 0) {
            return $cache[$cacheKey] = [];
        }

        $grouping = (string) ($discount->grouping_mode ?: TimeDiscount::GROUP_PRICE_SORTED);
        $target = (string) ($discount->apply_target ?: TimeDiscount::TARGET_CHEAPEST);
        $index = (int) ($discount->apply_index ?? 0);

        if ($grouping === TimeDiscount::GROUP_PRICE_SORTED) {
            usort($units, fn (array $a, array $b) => $b['price'] <=> $a['price']);
        }

        $chunks = array_chunk($units, $eachN);
        $chunks = array_values(array_filter($chunks, fn (array $chunk) => count($chunk) === $eachN));

        foreach ($chunks as $chunk) {
            $recipient = null;

            if ($target === TimeDiscount::TARGET_MOST_EXPENSIVE) {
                $recipient = collect($chunk)->sortByDesc('price')->first();
            } elseif ($target === TimeDiscount::TARGET_INDEX) {
                $sorted = collect($chunk)->sortBy('price')->values();
                $pos = max(1, min($eachN, $index > 0 ? $index : 1));
                $recipient = $sorted->get($pos - 1);
            } else {
                $recipient = collect($chunk)->sortBy('price')->first();
            }

            if (! is_array($recipient)) {
                continue;
            }

            $itemId = (int) ($recipient['item_id'] ?? 0);
            if ($itemId > 0) {
                if (! isset($result[$itemId])) {
                    $result[$itemId] = ['units' => 0, 'amount' => 0.0];
                }

                $result[$itemId]['units'] += 1;
                $result[$itemId]['amount'] += ((float) ($recipient['price'] ?? 0)) * ($percent / 100);
            }
        }

        foreach ($result as &$row) {
            $row['amount'] = round((float) $row['amount'], 2);
        }
        unset($row);

        return $cache[$cacheKey] = $result;
    }

    protected static function matchesTimeDiscountScope(TimeDiscount $discount, OrderItem $item): bool
    {
        $product = $item->product;
        if (! $product) {
            return false;
        }

        try {
            static $matchProductMethod;
            static $matchCategoryMethod;
            static $matchCharacteristicsMethod;

            if (! $matchProductMethod) {
                $matchProductMethod = new \ReflectionMethod(TimeDiscount::class, 'matchesProduct');
                $matchProductMethod->setAccessible(true);
            }

            if (! $matchCategoryMethod) {
                $matchCategoryMethod = new \ReflectionMethod(TimeDiscount::class, 'matchesCategory');
                $matchCategoryMethod->setAccessible(true);
            }

            if (! $matchCharacteristicsMethod) {
                $matchCharacteristicsMethod = new \ReflectionMethod(TimeDiscount::class, 'matchesCharacteristics');
                $matchCharacteristicsMethod->setAccessible(true);
            }

            if (! (bool) $matchProductMethod->invoke($discount, $product)) {
                return false;
            }

            if (! (bool) $matchCategoryMethod->invoke($discount, $product)) {
                return false;
            }

            if (! (bool) $matchCharacteristicsMethod->invoke($discount, $item)) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        return true;
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

        $components = array_values(array_filter(
            $amountSection->getChildComponents(),
            fn ($component) => method_exists($component, 'getName')
                && in_array($component->getName(), $moved, true)
        ));

        foreach ($components as $component) {
            if (! method_exists($component, 'getName') || $component->getName() !== 'ui_loyalty_spent') {
                continue;
            }

            if ($component instanceof Placeholder) {
                $component->content(function (?Order $record) {
                    if (! $record) {
                        return new HtmlString('—');
                    }

                    $spentFromTransactions = (float) abs(
                        $record->loyaltyTransactions()->where('amount', '<', 0)->sum('amount')
                    );

                    $spentFromAdjustments = (float) abs(
                        $record->adjustments()
                            ->whereNull('shop_order_item_id')
                            ->whereIn('type', ['loyalty', 'loyalty_spent', 'bonus_spent'])
                            ->sum('amount')
                    );

                    $spentFromSaleSum = max(0.0, (float) ($record->sale_sum ?? 0));

                    $spent = max($spentFromTransactions, $spentFromAdjustments, $spentFromSaleSum);

                    if ($spent <= 0) {
                        return new HtmlString('<div class="text-sm text-gray-500">Бонуси не використовувались</div>');
                    }

                    return new HtmlString('<div class="text-lg font-semibold">' . number_format($spent, 2, ',', ' ') . ' грн</div>');
                });
            }
        }

        $components[] = TextInput::make('cash_from')
            ->label('Сдача с')
            ->numeric()
            ->step(0.01)
            ->suffix('грн')
            ->placeholder('0')
            ->dehydrated(true)
            ->live(debounce: 300)
            ->dehydrateStateUsing(function ($state, Get $get) {
                $payment = $get('payment');
                $value = $payment instanceof PaymentMethodEnum ? $payment->value : (int) $payment;

                if ($value !== PaymentMethodEnum::CASH->value) {
                    return null;
                }

                $normalized = (float) str_replace(',', '.', (string) $state);

                return $normalized > 0 ? $normalized : null;
            })
            ->visible(function (Get $get): bool {
                $payment = $get('payment');
                $value = $payment instanceof PaymentMethodEnum ? $payment->value : (int) $payment;

                return $value === PaymentMethodEnum::CASH->value;
            });

        $components[] = Placeholder::make('imported_discount_info')
            ->label('Импортированная скидка')
            ->dehydrated(false)
            ->content(function (?Order $record, Get $get) {
                $sourceId = (int) ($get('source_id') ?? $record?->source_id ?? 0);
                if ($sourceId <= 0) {
                    return new \Illuminate\Support\HtmlString('—');
                }

                $importDiscount = (float) ($record?->adjustments()
                    ->where('type', 'import_discount')
                    ->whereNull('shop_order_item_id')
                    ->value('amount') ?? 0);

                $discountAmount = abs($importDiscount);
                if ($discountAmount <= 0) {
                    return new \Illuminate\Support\HtmlString('<span class="text-sm text-gray-500">Нет</span>');
                }

                $subtotal = (float) ($record?->subtotal ?? 0);
                $percentText = '';
                if ($subtotal > 0) {
                    $percent = round(($discountAmount / $subtotal) * 100, 2);
                    $percentText = ' (' . number_format($percent, 2, ',', ' ') . '%)';
                }

                return new \Illuminate\Support\HtmlString(
                    '<span style="color:#dc2626;font-weight:600;">-'
                    . number_format($discountAmount, 2, ',', ' ')
                    . ' грн'
                    . e($percentText)
                    . '</span>'
                );
            });

        $components[] = Placeholder::make('cash_change')
            ->label('')
            ->hiddenLabel()
            ->dehydrated(false)
            ->visible(function (Get $get): bool {
                $payment = $get('payment');
                $value = $payment instanceof PaymentMethodEnum ? $payment->value : (int) $payment;

                return $value === PaymentMethodEnum::CASH->value;
            })
            ->content(function (?Order $record, Get $get) {
                $baseTotal = static::calcBaseTotalFromGet($get);
                $deliveryPrice = (float) ($get('shipping_price') ?? 0);

                if (! $record) {
                    $finalAmount = (float) $baseTotal + $deliveryPrice;
                } else {
                    $hasAdjustments = $record->adjustments()->exists();
                    $record->refresh();

                    if ($hasAdjustments) {
                        $recordDelivery = max(
                            (float) ($record->shipping_total ?? 0),
                            (float) ($record->shipping_price ?? 0)
                        );

                        $finalAmount = (float) ($record->grand_total ?? 0);
                        $finalAmount += ($deliveryPrice - $recordDelivery);
                    } else {
                        $finalAmount = (float) $baseTotal + $deliveryPrice;
                    }
                }

                $cashRaw = (string) ($get('cash_from') ?? '0');
                $cashFrom = (float) str_replace(',', '.', $cashRaw);

                if ($cashFrom <= 0) {
                    return 'Сдача 0,00 грн';
                }

                $change = $cashFrom - $finalAmount;

                if ($change < 0) {
                    return 'Недостатньо ' . number_format(abs($change), 2, ',', ' ') . ' грн';
                }

                return 'Сдача ' . number_format($change, 2, ',', ' ') . ' грн';
            });

        $components[] = Placeholder::make('sidebar_receipt_buttons')
            ->label('')
            ->hiddenLabel()
            ->dehydrated(false)
            ->content(fn (): HtmlString => new HtmlString(
                '<div style="display:flex;gap:8px;flex-wrap:wrap;">'
                .'<button type="button" wire:click="mountAction(\'print_client_receipt_sidebar\')" style="display:block;flex:1;padding:10px 12px;border:1px solid #2563eb;border-radius:8px;background:#eff6ff;color:#1d4ed8;font-weight:700;cursor:pointer;text-align:center;">Клиентский чек</button>'
                .'<button type="button" wire:click="mountAction(\'print_logistic_receipt_sidebar\')" style="display:block;flex:1;padding:10px 12px;border:1px solid #b45309;border-radius:8px;background:#fffbeb;color:#b45309;font-weight:700;cursor:pointer;text-align:center;">Чек для логиста</button>'
                .'<button type="button" wire:click="mountAction(\'print_client_and_logistic_receipts_sidebar\')" style="display:block;flex:1;min-width:220px;padding:10px 12px;border:1px solid #166534;border-radius:8px;background:#ecfdf5;color:#166534;font-weight:700;cursor:pointer;text-align:center;">Клиентский + логиста чек</button>'
                .'</div>'
            ))
            ->columnSpanFull()
            ->visible(fn (LivewireComponent $livewire): bool => method_exists($livewire, 'mountAction'));

        return $components;
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

        Log::info('Callcenter unit lookup: no unit found', [
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

    protected static function getProductDescriptionForTooltip(int $productId): string
    {
        static $cache = [];

        if (isset($cache[$productId])) {
            return $cache[$productId];
        }

        $product = \App\Models\Shop\Product::query()
            ->select(['id', 'parent_id', 'description', 'short_desc'])
            ->find($productId);

        if (! $product) {
            return $cache[$productId] = 'Описание отсутствует';
        }

        $description = static::extractProductDescriptionText($product);

        if ($description === '' && (int) ($product->parent_id ?? 0) > 0) {
            $parent = \App\Models\Shop\Product::query()
                ->select(['id', 'description', 'short_desc'])
                ->find((int) $product->parent_id);

            if ($parent) {
                $description = static::extractProductDescriptionText($parent);
            }
        }

        if ($description === '') {
            $description = 'Описание отсутствует';
        }

        return $cache[$productId] = $description;
    }

    protected static function extractProductDescriptionText(\App\Models\Shop\Product $product): string
    {
        $locale = app()->getLocale();
        $defaultLocale = config('app.locale', 'uk');

        $description = static::extractLocalizedText($product->getRawOriginal('description'), $locale, $defaultLocale);

        if ($description === '') {
            $description = trim((string) ($product->short_desc ?? ''));
        }

        $description = preg_replace('/\s+/u', ' ', strip_tags($description));
        $description = trim((string) $description);

        if ($description === '') {
            return '';
        }

        if (mb_strlen($description) > 300) {
            return rtrim(mb_substr($description, 0, 297)) . '...';
        }

        return $description;
    }

    protected static function extractLocalizedText(mixed $raw, string $locale, string $defaultLocale): string
    {
        if (is_array($raw)) {
            $value = $raw[$locale]
                ?? $raw[$defaultLocale]
                ?? $raw['uk']
                ?? $raw['ru']
                ?? $raw['en']
                ?? (count($raw) ? reset($raw) : '');

            return trim((string) $value);
        }

        if (! is_string($raw)) {
            return '';
        }

        $trimmed = trim($raw);

        if ($trimmed === '') {
            return '';
        }

        $decoded = json_decode($trimmed, true);

        if (is_array($decoded)) {
            $value = $decoded[$locale]
                ?? $decoded[$defaultLocale]
                ?? $decoded['uk']
                ?? $decoded['ru']
                ?? $decoded['en']
                ?? (count($decoded) ? reset($decoded) : '');

            return trim((string) $value);
        }

        return $trimmed;
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

        $record = $livewire->record->fresh();
        if (! $record) {
            return;
        }

        $pricing = app(\App\Services\OrderPricing::class);

        $timeAdj = $record->adjustments()
            ->where('type', 'time')
            ->whereNull('shop_order_item_id')
            ->latest('id')
            ->first();

        if ($timeAdj) {
            $timeId = (int) (data_get($timeAdj->meta, 'id') ?? data_get($timeAdj->meta, 'time_discount_id') ?? 0);

            if ($timeId > 0) {
                $pricing->applyTimeExclusive(
                    $record,
                    $timeId,
                    'single',
                    static::resolveDiscountMomentForOrder($record)
                );
            } else {
                $pricing->recalc($record);
            }
        } else {
            $fixedAdj = $record->adjustments()
                ->where('type', 'fixed')
                ->whereNull('shop_order_item_id')
                ->latest('id')
                ->first();

            if ($fixedAdj) {
                $fixedId = (int) (data_get($fixedAdj->meta, 'id') ?? data_get($fixedAdj->meta, 'fixed_discount_id') ?? 0);

                if ($fixedId > 0) {
                    $pricing->applyFixedExclusive($record, $fixedId, 'single');
                } else {
                    $pricing->recalc($record);
                }
            } else {
                $pricing->recalc($record);
            }
        }

        $livewire->record = $record->fresh();
        $livewire->record->recalculateTotalPrice();
    }

    protected static function resolveDiscountMomentForOrder(Order $order): Carbon
    {
        $tz = config('app.timezone', 'Europe/Kyiv');

        if (! (bool) $order->as_soon_possible && $order->date_order) {
            $date = $order->date_order instanceof \DateTimeInterface
                ? Carbon::instance($order->date_order)->setTimezone($tz)
                : Carbon::parse((string) $order->date_order, $tz);

            if ($order->time_order) {
                $time = $order->time_order instanceof \DateTimeInterface
                    ? Carbon::instance($order->time_order)->setTimezone($tz)->format('H:i:s')
                    : Carbon::parse((string) $order->time_order, $tz)->format('H:i:s');
                $date->setTimeFromTimeString($time);
            }

            return $date;
        }

        if ($order->dat) {
            $date = $order->dat instanceof \DateTimeInterface
                ? Carbon::instance($order->dat)->setTimezone($tz)
                : Carbon::parse((string) $order->dat, $tz);

            if ($order->time_start) {
                $time = $order->time_start instanceof \DateTimeInterface
                    ? Carbon::instance($order->time_start)->setTimezone($tz)->format('H:i:s')
                    : Carbon::parse((string) $order->time_start, $tz)->format('H:i:s');
                $date->setTimeFromTimeString($time);
            }

            return $date;
        }

        return now($tz);
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
