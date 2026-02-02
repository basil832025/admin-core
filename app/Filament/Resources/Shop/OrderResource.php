<?php

// ====== app/Filament/Resources/Shop/OrderResource.php ======

namespace App\Filament\Resources\Shop;

use App\Enums\OrderStatus;
use App\Filament\Clusters\Products\Resources\ProductResource;
use App\Filament\Resources\Shop\OrderResource\Pages;
use App\Filament\Resources\Shop\OrderResource\Widgets\OrderStats;
use App\Forms\Components\AddressForm;
use App\Models\Setting;
use App\Models\Shop\ClientAddress;
use App\Models\Shop\FixedDiscount;
use App\Models\Shop\Order;
use App\Models\Shop\Product;
use App\Models\Shop\PromoCode;
use App\Models\Shop\TimeDiscount;
use App\Models\Shop\VariationValue;
use App\Models\Currency;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;               // <— для таблицы (модалка «Статусы»)
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use App\Models\Shop\LoyaltyTransaction;
use App\Models\Shop\Client;
use App\Filament\Resources\ClientResource;
use Filament\Support\Enums\VerticalAlignment;
use App\Enums\PaymentMethodEnum;
use Filament\Tables\Columns\BadgeColumn;
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $slug = 'shop/orders';
    protected static ?string $recordTitleAttribute = 'number';
    protected static ?string $navigationGroup = 'Магазин';
    protected static ?string $navigationLabel = null;
    protected static ?string $modelLabel = null;
    protected static ?string $pluralModelLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('order.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('order.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('order.nav.plural_model_label');
    }
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->components([
                Group::make()
                    ->schema([
                        Forms\Components\Tabs::make('order_tabs')
                            ->tabs([
                                Forms\Components\Tabs\Tab::make(__('order.tabs.info'))
                                    ->schema(static::getInfoTabSchema())
                                    ->columns(2),
                                Forms\Components\Tabs\Tab::make(__('order.tabs.products'))
                                    ->schema(static::getProductsTabSchema()),
                                Forms\Components\Tabs\Tab::make(__('order.tabs.journal'))->schema([
                                    View::make('filament.orders.journal-tab')
                                        ->dehydrated(false)
                                        ->columnSpanFull(),
                                ]),
                             ])
                            ->persistTabInQueryString(),
                    ])
                    ->columnSpan(['lg' => fn (?Order $record) => $record === null ? 3 : 2]),
                Section::make()
                    ->schema(static::getSidebarSchema())
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    // =========================
    //   Переиспользуемая форма модалки статусов
    // =========================
    public static function statusModalForm(): array
    {
        return [
            Hidden::make('current')
                ->default(fn (?Order $r) => $r?->status?->value),

            ToggleButtons::make('status_ui')
                ->label(__('order.fields.status'))
                ->inline()
                ->required()
                ->options(fn () => static::allowedStatuses())
                ->icons(OrderStatus::iconsMap())
                ->colors(OrderStatus::colorsMap())
                ->default(fn (?Order $r) => $r?->status?->value ?? OrderStatus::New->value)
                ->reactive(),

            Textarea::make('downgrade_reason')
                ->label(__('order.fields.rollback_reason'))
                ->placeholder(__('order.placeholders.rollback_reason'))
                ->rows(3)
                ->visible(function (Get $get) {
                    $cur = $get('current');
                    $to  = $get('status_ui');
                    if (! $cur || ! $to) return false;

                    return OrderStatus::from($to)->rank() < OrderStatus::from($cur)->rank();
                })
                ->required(function (Get $get) {
                    $cur = $get('current');
                    $to  = $get('status_ui');
                    if (! $cur || ! $to) return false;

                    return OrderStatus::from($to)->rank() < OrderStatus::from($cur)->rank();
                }),
        ];
    }

    public static function getProductsTabSchema(): array
    {
        return [
            Section::make(__('order.sections.order_items'))
                ->headerActions([
                    FormAction::make(__('order.actions.clear'))
                        ->modalHeading(__('order.modals.clear_heading'))
                        ->modalDescription(__('order.modals.clear_description'))
                        ->requiresConfirmation()
                        ->color('danger')
                        ->action(fn (Set $set) => $set('items', [])),
                ])
                ->schema([
                    Placeholder::make('order_total')
                        ->label(__('order.fields.order_sum'))
                        ->content(function (callable $get) {
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
            // ——— Суммы и скидки ———
            Section::make(__('order.sections.amount_discounts'))
                ->schema([
                    Placeholder::make('order_total_right')
                        ->label(__('order.fields.order_sum'))
                        ->content(function (Get $get) {
                            $items = collect($get('items') ?? [])
                                ->map(fn ($item) => is_object($item) ? (array) $item : $item);

                            $base = $items->sum(function ($item) {
                                $qty = (float) ($item['qty'] ?? 0);
                                $price = (float) ($item['unit_price'] ?? 0);
                                $mods = collect($item['modifiers'] ?? [])
                                    ->map(fn ($m) => is_object($m) ? (array) $m : $m);
                                $modsSum = $mods->sum(fn ($m) => (float) ($m['price_modifier'] ?? 0));

                                return $qty * ($price + $modsSum);
                            });

                            return number_format($base, 2, ',', ' ') . ' грн';
                        })
                        ->reactive(),

                    // 1) Фіксована знижка
             /*       Select::make('ui_fixed_discount_id')
                        ->label(__('order.fields.fixed_discount'))
                        ->dehydrated(false)
                        ->searchable()
                        ->nullable()
                        ->options(fn () => FixedDiscount::active()->pluck('name', 'id'))
                        ->afterStateHydrated(function (Set $set, ?Order $record) {
                            if (! $record) return;
                            $adj = $record->adjustments()->where('type', 'fixed')->first();
                            $set('ui_fixed_discount_id', $adj?->meta['id'] ?? null);
                        })
                        ->reactive()
                        ->afterStateUpdated(function ($state, Set $set, ?Order $record) {
                            if (! $record) return;

                            app(\App\Services\OrderPricing::class)
                                ->applyFixedExclusive($record, $state ? (int)$state : null, policy: 'single');

                            if ($state) {
                                $set('ui_time_discount_id', null);
                                $set('ui_manual_percent', null);
                            }

                            app(\App\Services\OrderPricing::class)->recalc($record);
                            $set('ui_version', microtime(true));
                        }),*/

                    // 2) Знижки за часом (happy hours)
                    Select::make('ui_time_discount_id')
                        ->label(__('order.fields.time_discount'))
                        ->searchable()
                        ->nullable()
                        ->dehydrated(false)
                        ->reactive()
                        ->afterStateHydrated(function (Select $component, ?Order $record) {
                            if (! $record) return;

                            if (! $record->relationLoaded('adjustments')) {
                                $record->load('adjustments');
                            }

                            $adj = $record->adjustments->firstWhere('type', 'time');
                            $id  = $adj ? (data_get($adj->meta, 'id') ?? data_get($adj->meta, 'time_discount_id')) : null;

                            if ($id) {
                                $opts = $component->getOptions() ?? [];
                                if (! array_key_exists($id, $opts)) {
                                    if ($name = TimeDiscount::find($id)?->name) {
                                        $opts[$id] = $name;
                                        $component->options($opts);
                                    }
                                }
                                $component->state((string) $id);
                            }
                        })
                        ->options(function (Get $get) {
                            $type = $get('time_type') ?? 'order';
                            $momentStr = $type === 'execution'
                                ? trim(($get('delivery_date') ?? '') . ' ' . ($get('delivery_time') ?? ''))
                                : trim(($get('ordered_date')  ?? '') . ' ' . ($get('ordered_time')  ?? ''));

                            $moment = $momentStr ? Carbon::parse($momentStr) : now();

                            return TimeDiscount::query()
                                ->activeForMoment($moment, 'Europe/Kyiv')
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->afterStateUpdated(function ($state, Set $set, Get $get, ?Order $record) {
                            if (! $record) return;

                            $discountType = TimeDiscount::find($state)?->time_type ?? ($get('time_type') ?? 'order');
                            $momentStr = $discountType === 'execution'
                                ? trim(($get('delivery_date') ?? '') . ' ' . ($get('delivery_time') ?? ''))
                                : trim(($get('ordered_date')  ?? '') . ' ' . ($get('ordered_time')  ?? ''));

                            $moment = $momentStr ? Carbon::parse($momentStr) : now();

                            app(\App\Services\OrderPricing::class)->applyTimeExclusive(
                                $record,
                                $state ? (int) $state : null,
                                'single',
                                $moment
                            );

                            if ($state) {
                                $set('ui_fixed_discount_id', null);
                                $set('ui_manual_percent', null);
                            }

                            app(\App\Services\OrderPricing::class)->recalc($record);
                        }),

                    // 3) Промокод
                    TextInput::make('ui_promo_code')
                        ->label(__('order.fields.promo_code'))
                        ->placeholder(__('order.placeholders.promo_code'))
                        ->dehydrated(false)
                        ->reactive()
                        ->afterStateHydrated(function (TextInput $component, ?Order $record) {
                            if (! $record) return;
                            if (! $record->relationLoaded('adjustments')) $record->load('adjustments');

                            $adj  = $record->adjustments->firstWhere('type', 'coupon');
                            $code = $adj ? (string) data_get($adj->meta, 'code', '') : '';
                            if ($code !== '') $component->state($code);
                        })
                        ->suffixActions([
                            FormAction::make('applyPromo')
                                ->icon('heroicon-m-check')
                                ->action(function (Get $get, Set $set, ?Order $record) {
                                    if (! $record) return;

                                    $code = trim((string) $get('ui_promo_code'));
                                    if ($code === '') return;

                                    $ok = app(\App\Services\OrderPricing::class)->applyPromo($record, $code);
                                    if ($ok) {
                                        $set('ui_promo_code', $code);
                                        Notification::make()->title('Промокод застосовано')->success()->send();
                                    } else {
                                        Notification::make()->title('Промокод не дійсний')->danger()->send();
                                    }
                                }),

                            FormAction::make('clearPromo')
                                ->icon('heroicon-m-x-mark')
                                ->tooltip(__('order.actions.clear_promo'))
                                ->requiresConfirmation()
                                ->action(function (Set $set, ?Order $record) {
                                    if (! $record) return;

                                    try {
                                        DB::transaction(function () use ($record, $set) {
                                            $adj = $record->adjustments()->where('type', 'coupon')->first();

                                            if ($adj) {
                                                $promoId = $adj->promo_code_id
                                                    ?? ($adj->meta['promo_id'] ?? null);

                                                $promo = $promoId
                                                    ? PromoCode::find($promoId)
                                                    : (isset($adj->meta['code'])
                                                        ? PromoCode::where('code', $adj->meta['code'])->first()
                                                        : null);

                                                $promo?->unmarkUsed($record->id);
                                                $adj->delete();
                                            }

                                            app(\App\Services\OrderPricing::class)->recalc($record);
                                            $set('ui_promo_code', null);
                                        });

                                        Notification::make()
                                            ->success()
                                            ->title('Промокод удален')
                                            ->send();
                                    } catch (\Exception $e) {
                                        Notification::make()
                                            ->danger()
                                            ->title('Ошибка при удалении промокода')
                                            ->body($e->getMessage())
                                            ->send();

                                        Log::error('Ошибка при удалении промокода из заказа', [
                                            'order_id' => $record->id,
                                            'error' => $e->getMessage(),
                                            'trace' => $e->getTraceAsString(),
                                        ]);
                                    }
                                })
                        ]),

                    TextInput::make('ui_manual_percent')
                        ->label(__('order.fields.manual_discount_percent'))
                        ->numeric()
                        ->dehydrated(false)
                        ->reactive()
                        ->afterStateHydrated(function (TextInput $component, ?Order $record) {
                            if (! $record) return;
                            if (! $record->relationLoaded('adjustments')) $record->load('adjustments');

                            $adj = $record->adjustments->firstWhere('type', 'manual_percent');
                            if ($adj) {
                                $val = (float) data_get($adj->meta, 'percent', 0);
                                $component->state($val);
                            }
                        })
                        ->afterStateUpdated(function ($state, Set $set, Get $get, ?Order $record) {
                            if (! $record) return;

                            $val = (float) $state;

                            if ($val > 0) {
                                $set('ui_fixed_discount_id', null);
                                $set('ui_time_discount_id', null);
                            }

                            app(\App\Services\OrderPricing::class)
                                ->applyManualPercentExclusive($record, $val);

                            app(\App\Services\OrderPricing::class)->recalc($record);
                        }),

                    TextInput::make('ui_manual_fixed')
                        ->label(__('order.fields.manual_discount_amount'))
                        ->numeric()
                        ->dehydrated(false)
                        ->reactive()
                        ->afterStateHydrated(function (TextInput $component, ?Order $record) {
                            if (! $record) return;

                            $adj = $record->adjustments()
                                ->where('type', 'manual_fixed')
                                ->latest()
                                ->first();

                            $amount = $adj
                                ? (data_get($adj, 'meta.amount') ?? data_get($adj, 'amount'))
                                : null;

                            $component->state($amount !== null ? (float) abs($amount) : null);
                        })
                        ->afterStateUpdated(function ($state, $set, $get, ?Order $record) {
                            if (! $record) return;
                            app(\App\Services\OrderPricing::class)->applyManualFixed($record, (float)$state);
                            app(\App\Services\OrderPricing::class)->recalc($record);
                        }),

                    Placeholder::make('ui_adjustments_list')
                        ->label(__('order.fields.applied_discounts'))
                        ->content(function (?Order $order) {
                            if (! $order) return new HtmlString('—');

                            $rows = $order->adjustments()->orderByDesc('id')->get();
                            if ($rows->isEmpty()) {
                                return new HtmlString('<div class="text-sm text-gray-500">Скидки не применены</div>');
                            }

                            $out = '<div class="space-y-1">';
                            foreach ($rows as $adj) {
                                $cls = $adj->amount < 0 ? 'text-rose-600' : 'text-emerald-600';
                                $out .= '<div class="flex justify-between text-sm">'
                                    .    '<div><span class="font-medium">'.e($adj->label).'</span> '
                                    .    ($adj->type ? '<span class="text-gray-500">('.e($adj->type).')</span>' : '')
                                    .    '</div>'
                                    .    '<div class="'.$cls.'">'.number_format($adj->amount, 2, ',', ' ')
                                    .    ' '.e($order->currency ?? 'UAH').'</div>'
                                    . '</div>';
                            }
                            $out .= '</div>';

                            return new HtmlString($out);
                        })->dehydrated(false)->inlineLabel(false)
                        ->dehydrated(false),
                    Placeholder::make('ui_loyalty_spent')
                        ->label(__('order.fields.bonuses_written_off'))
                        ->content(function (?Order $order) {
                            if (! $order) {
                                return new HtmlString('—');
                            }

                            // берём все транзакции лояльности по этому заказу
                            $txQuery = $order->loyaltyTransactions();

                            // предполагаем, что поле суммы называется amount
                            // и списание идёт отрицательным знаком
                            $spent = (float) abs(
                                $txQuery->where('amount', '<', 0)->sum('amount')
                            );

                            if ($spent <= 0) {
                                return new HtmlString(
                                    '<div class="text-sm text-gray-500">Бонуси не використовувались</div>'
                                );
                            }

                            $val = number_format($spent, 2, ',', ' ') . ' грн';

                            return new HtmlString(
                                '<div class="text-lg font-semibold">'.$val.'</div>'
                            );
                        }),

                    Hidden::make('ui_version')->dehydrated(false)->reactive(),
                    Hidden::make('total_price')
                        ->dehydrated(true)
                        ->afterStateHydrated(fn ($component) => $component->state(null)) // не мешаем редактированию
                        ->dehydrateStateUsing(fn (Get $get) => round(static::calcBaseTotalFromGet($get), 2)),

                    Hidden::make('discount_total')
                        ->dehydrated(true)
                        ->afterStateHydrated(fn ($component) => $component->state(null))
                        ->dehydrateStateUsing(function (?Order $record) {
                            // если записи нет или скидок нет — 0
                            if (! $record) return 0.0;
                            return (float) $record->adjustments()->sum('amount'); // скидки у тебя отрицательные
                        }),

                    Hidden::make('grand_total')
                        ->dehydrated(true)
                        ->afterStateHydrated(fn ($component) => $component->state(null))
                        ->dehydrateStateUsing(function (Get $get, ?Order $record) {
                            $base = static::calcBaseTotalFromGet($get);
                            $adj  = $record ? (float) $record->adjustments()->sum('amount') : 0.0;
                            // если скидок нет — просто база
                            return round($base + $adj, 2);
                        }),
                    // Скрытое поле для отслеживания изменений координат (триггер для обновления доставки)
                    Hidden::make('delivery_coords_trigger')
                        ->dehydrated(false)
                        ->default('')
                        ->live()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, Get $get, ?Order $record) {
                            // Триггерим обновление delivery_price_auto для пересчета shipping_price
                            \Log::info('OrderResource: delivery_coords_trigger afterStateUpdated', [
                                'trigger' => $state,
                            ]);

                            // Обновляем delivery_price_auto для пересчета shipping_price
                            $set('delivery_price_auto', $state ?: time());

                            // Также напрямую обновляем shipping_price, если координаты есть
                            if ($record && $state) {
                                $address = $get('address') ?? [];
                                $latitude = $address['latitude'] ?? null;
                                $longitude = $address['longitude'] ?? null;
                                $selfPickup = $get('self_pickup') ?? false;

                                if (!$selfPickup && $latitude && $longitude) {
                                    $deliveryService = app(\App\Services\DeliveryCalculationService::class);
                                    $baseTotal = static::calcBaseTotalFromGet($get);
                                    $hasAdjustments = $record->adjustments()->exists();
                                    $orderTotal = $hasAdjustments
                                        ? (float) ($record->grand_total ?? 0)
                                        : (float) $baseTotal;

                                    $tempOrder = clone $record;
                                    $tempOrder->address = $address;
                                    $tempOrder->self_pickup = $selfPickup;

                                    $delivery = $deliveryService->calculateDelivery($tempOrder, $orderTotal);
                                    $calculatedPrice = (float) ($delivery['price'] ?? 0);

                                    $currentShippingPrice = (float) ($get('shipping_price') ?? 0);

                                    // Обновляем shipping_price если текущее значение равно 0 или близко к рассчитанному
                                    if ($currentShippingPrice == 0 || abs($currentShippingPrice - $calculatedPrice) < 0.01) {
                                        $set('shipping_price', $calculatedPrice);
                                        \Log::info('OrderResource: shipping_price updated via delivery_coords_trigger', [
                                            'new_value' => $calculatedPrice,
                                            'zone' => $delivery['zone'] ? $delivery['zone']->name : null,
                                        ]);
                                    }
                                }
                            }
                        }),

                    // Сумма доставки (редактируемое поле)
                    TextInput::make('shipping_price')
                        ->label('Сумма доставки')
                        ->numeric()
                        ->suffix('грн')
                        ->step(0.01)
                        ->minValue(0)
                        ->default(0)
                        ->reactive()
                        ->live()
                        ->afterStateHydrated(function (TextInput $component, $state, ?Order $record, Get $get) {
                            // При загрузке формы, если shipping_price пустой, рассчитываем автоматически
                            if (!$state && $record) {
                                $address = $get('address') ?? [];
                                $latitude = $address['latitude'] ?? null;
                                $longitude = $address['longitude'] ?? null;
                                $selfPickup = $get('self_pickup') ?? false;

                                if (!$selfPickup && $latitude && $longitude) {
                                    $deliveryService = app(\App\Services\DeliveryCalculationService::class);
                                    $baseTotal = static::calcBaseTotalFromGet($get);
                                    $hasAdjustments = $record->adjustments()->exists();
                                    $orderTotal = $hasAdjustments
                                        ? (float) ($record->grand_total ?? 0)
                                        : (float) $baseTotal;

                                    $tempOrder = clone $record;
                                    $tempOrder->address = $address;
                                    $tempOrder->self_pickup = $selfPickup;

                                    $delivery = $deliveryService->calculateDelivery($tempOrder, $orderTotal);
                                    $component->state($delivery['price'] ?? 0);
                                }
                            }
                        })
                        ->afterStateUpdated(function ($state, callable $set, Get $get, ?Order $record) {
                            // При изменении суммы доставки пересчитываем итоговую сумму
                            if ($record) {
                                $set('shipping_total', (float)$state);
                            }
                        })
                        ->helperText(function (Get $get, ?Order $record) {
                            // Показываем информацию о зоне доставки
                            if (!$record) {
                                return null;
                            }

                            $address = $get('address') ?? [];
                            $latitude = $address['latitude'] ?? null;
                            $longitude = $address['longitude'] ?? null;
                            $selfPickup = $get('self_pickup') ?? false;

                            if ($selfPickup) {
                                return 'Самовывоз';
                            }

                            if (!$latitude || !$longitude) {
                                return 'Выберите адрес для расчета доставки';
                            }

                            $deliveryService = app(\App\Services\DeliveryCalculationService::class);
                            $baseTotal = static::calcBaseTotalFromGet($get);
                            $hasAdjustments = $record->adjustments()->exists();
                            $orderTotal = $hasAdjustments
                                ? (float) ($record->grand_total ?? 0)
                                : (float) $baseTotal;

                            $tempOrder = clone $record;
                            $tempOrder->address = $address;
                            $tempOrder->self_pickup = $selfPickup;

                            $delivery = $deliveryService->calculateDelivery($tempOrder, $orderTotal);

                            if ($delivery['is_free']) {
                                $text = 'Бесплатная доставка';
                                if ($delivery['zone']) {
                                    $text .= ' (от ' . number_format($delivery['zone']->free_delivery_from, 2, ',', ' ') . ' грн)';
                                }
                                return $text;
                            }

                            if ($delivery['zone']) {
                                return 'Зона: ' . $delivery['zone']->name;
                            }

                            return null;
                        })
                        ->visible(fn (Get $get) => !($get('self_pickup') ?? false)),

                    // Скрытое поле для автоматического обновления shipping_price при изменении координат
                    Hidden::make('delivery_price_auto')
                        ->dehydrated(false)
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, Get $get, ?Order $record) {
                            // Автоматически обновляем shipping_price при изменении координат
                            \Log::info('OrderResource: delivery_price_auto afterStateUpdated called', [
                                'state' => $state,
                                'has_record' => !is_null($record),
                            ]);

                            if (!$record || !$state) {
                                \Log::info('OrderResource: delivery_price_auto skipped - no record or state');
                                return;
                            }

                            $address = $get('address') ?? [];
                            $latitude = $address['latitude'] ?? null;
                            $longitude = $address['longitude'] ?? null;
                            $selfPickup = $get('self_pickup') ?? false;

                            \Log::info('OrderResource: delivery_price_auto checking coordinates', [
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                                'selfPickup' => $selfPickup,
                                'address_keys' => array_keys($address),
                            ]);

                            if ($selfPickup) {
                                $set('shipping_price', 0);
                                \Log::info('OrderResource: shipping_price set to 0 (self pickup)');
                                return;
                            }

                            if (!$latitude || !$longitude) {
                                \Log::info('OrderResource: delivery_price_auto skipped - missing coordinates');
                                return;
                            }

                            $deliveryService = app(\App\Services\DeliveryCalculationService::class);
                            $baseTotal = static::calcBaseTotalFromGet($get);
                            $hasAdjustments = $record->adjustments()->exists();
                            $orderTotal = $hasAdjustments
                                ? (float) ($record->grand_total ?? 0)
                                : (float) $baseTotal;

                            $tempOrder = clone $record;
                            $tempOrder->address = $address;
                            $tempOrder->self_pickup = $selfPickup;

                            $delivery = $deliveryService->calculateDelivery($tempOrder, $orderTotal);

                            // Обновляем shipping_price
                            $currentShippingPrice = (float) ($get('shipping_price') ?? 0);
                            $calculatedPrice = (float) ($delivery['price'] ?? 0);

                            \Log::info('OrderResource: delivery_price_auto updating shipping_price', [
                                'current' => $currentShippingPrice,
                                'calculated' => $calculatedPrice,
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                                'zone' => $delivery['zone'] ? $delivery['zone']->name : null,
                                'is_free' => $delivery['is_free'] ?? false,
                            ]);

                            // Обновляем shipping_price если:
                            // Определяем, было ли ручное изменение:
                            // 1. Текущее значение равно 0 или пустое (первый расчет) - обновляем
                            // 2. Текущее значение близко к рассчитанному (разница < 1.0) - обновляем (возможно, изменилась зона)
                            // 3. Если разница большая (> 1.0), но координаты изменились - это тоже автоматическое изменение, обновляем
                            // 4. Только если разница большая И координаты не изменились - это ручное изменение, не обновляем

                            // Проверяем, изменились ли координаты по сравнению с сохраненными в БД
                            // Если delivery_price_auto вызывается, это означает, что delivery_coords_trigger изменился,
                            // что в свою очередь означает, что координаты изменились
                            $coordsChanged = false;
                            $recordLat = $record->address['latitude'] ?? null;
                            $recordLng = $record->address['longitude'] ?? null;

                            if ($recordLat && $recordLng && $latitude && $longitude) {
                                // Сравниваем с координатами из БД (record)
                                $coordsChanged = (abs((float)$recordLat - (float)$latitude) > 0.0001) ||
                                                (abs((float)$recordLng - (float)$longitude) > 0.0001);
                            } else if ($latitude && $longitude) {
                                // Если в БД нет координат, но есть новые - это изменение
                                $coordsChanged = true;
                            }

                            // Также проверяем, изменилась ли зона - если зона изменилась, это автоматическое обновление
                            $zoneChanged = false;
                            $recordZone = null;
                            if ($record->address && isset($record->address['latitude']) && isset($record->address['longitude'])) {
                                $tempOrderOld = clone $record;
                                $deliveryOld = $deliveryService->calculateDelivery($tempOrderOld, $orderTotal);
                                $recordZone = $deliveryOld['zone']->name ?? null;
                                $newZone = $delivery['zone']->name ?? null;
                                $zoneChanged = ($recordZone !== $newZone);
                            }

                            $shouldUpdate = $currentShippingPrice == 0 ||
                                          abs($currentShippingPrice - $calculatedPrice) < 1.0 ||
                                          $coordsChanged ||
                                          $zoneChanged;

                            if ($shouldUpdate) {
                                $set('shipping_price', $calculatedPrice);
                                \Log::info('OrderResource: shipping_price updated successfully', [
                                    'new_value' => $calculatedPrice,
                                    'old_value' => $currentShippingPrice,
                                    'coords_changed' => $coordsChanged,
                                    'zone_changed' => $zoneChanged,
                                    'old_zone' => $recordZone,
                                    'new_zone' => $delivery['zone']->name ?? null,
                                ]);
                            } else {
                                \Log::info('OrderResource: shipping_price not updated (manual change detected)', [
                                    'current' => $currentShippingPrice,
                                    'calculated' => $calculatedPrice,
                                    'difference' => abs($currentShippingPrice - $calculatedPrice),
                                    'coords_changed' => $coordsChanged,
                                    'zone_changed' => $zoneChanged,
                                ]);
                            }
                        }),


                    Placeholder::make('total_after_discount')
                        ->label(__('order.fields.total_with_discount'))
                        ->dehydrated(false)
                        ->reactive()
                        ->content(function (?Order $record, Get $get) {
                            // 1) Базовая сумма из текущих позиций формы
                            $baseTotal = static::calcBaseTotalFromGet($get);

                            // 2) Если заказа ещё нет — просто показываем базу
                            if (! $record) {
                                $val = number_format($baseTotal, 2, ',', ' ') . ' грн';
                                return new \Illuminate\Support\HtmlString('<div class="text-lg font-semibold">'.$val.'</div>');
                            }

                            // 3) Если заказ есть: если есть применённые скидки — показываем grand_total,
                            //    иначе — тоже базовую сумму
                            $hasAdjustments = $record->adjustments()->exists();
                            $record->refresh();

                            $amount = $hasAdjustments
                                ? (float) ($record->grand_total ?? 0)
                                : (float) $baseTotal;

                            // 4) Добавляем сумму доставки
                            // Получаем адрес из формы (актуальные данные)
                            $address = $get('address') ?? [];
                            $selfPickup = $get('self_pickup') ?? false;

                            // Используем shipping_price из формы (может быть отредактирован вручную)
                            $deliveryPrice = (float) ($get('shipping_price') ?? 0);

                            $finalAmount = $amount + $deliveryPrice;

                            $html = '<div class="space-y-1">';
                            $html .= '<div class="text-lg font-semibold">' . number_format($finalAmount, 2, ',', ' ') . ' грн</div>';

                            if ($deliveryPrice > 0) {
                                $html .= '<div class="text-xs text-gray-500 flex items-center gap-2">';
                                $html .= '<span>Товары:</span>';
                                $html .= '<span>' . number_format($amount, 2, ',', ' ') . ' грн</span>';
                                $html .= '<span class="mx-1">+</span>';
                                $html .= '<span>Доставка:</span>';
                                $html .= '<span>' . number_format($deliveryPrice, 2, ',', ' ') . ' грн</span>';
                                $html .= '</div>';
                            } elseif (!$selfPickup) {
                                // Проверяем, есть ли бесплатная доставка
                                $address = $get('address') ?? [];
                                $latitude = $address['latitude'] ?? null;
                                $longitude = $address['longitude'] ?? null;

                                if ($latitude && $longitude) {
                                    $tempOrder = clone $record;
                                    $tempOrder->address = $address;
                                    $tempOrder->self_pickup = $selfPickup;

                                    $deliveryService = app(\App\Services\DeliveryCalculationService::class);
                                    $delivery = $deliveryService->calculateDelivery($tempOrder, $amount);

                                    if (isset($delivery['is_free']) && $delivery['is_free'] && isset($delivery['zone']) && $delivery['zone']) {
                                        $html .= '<div class="text-xs text-green-600">Доставка бесплатна (от ' . number_format($delivery['zone']->free_delivery_from, 2, ',', ' ') . ' грн)</div>';
                                    }
                                }
                            }

                            $html .= '</div>';

                            return new \Illuminate\Support\HtmlString($html);
                        })
                       /* ->content(function (?Order $record) {
                            if (! $record) return new HtmlString('—');
                            $record->refresh();
                            $val = number_format((float)$record->grand_total, 2, ',', ' ') . ' грн';
                            return new HtmlString('<div class="text-lg font-semibold">'.$val.'</div>');
                        }),*/
                ]),

            // ——— Статусы (инлайн блок остаётся на форме редактирования) ———
            Section::make(__('order.sections.statuses'))
                ->reactive()
                ->schema([
                    Hidden::make('status')->default(fn (?Order $r) => $r?->status->value)->dehydrated(true),
                    Hidden::make('downgrade_pending')->default(false)->dehydrated(false),
                    Hidden::make('pending_status')->dehydrated(false),
                    Hidden::make('downgrade_reason')->dehydrated(false),

                    ToggleButtons::make('status_ui')
                        ->label(__('order.fields.status'))
                        ->dehydrated(false)
                        ->inline()
                        ->options(fn () => static::allowedStatuses())
                        ->icons(OrderStatus::iconsMap())
                        ->colors(OrderStatus::colorsMap())
                        ->required()
                        ->disabled(fn () => empty(static::allowedStatuses()))
                        ->default(fn (?Order $r) => $r?->status->value ?? OrderStatus::New->value)
                        ->afterStateHydrated(function (ToggleButtons $component, ?Order $record) {
                            if ($record) {
                                $component->state($record->status->value);
                            }
                        })
                        ->reactive()
                        ->afterStateUpdated(function (string $state, callable $set, callable $get, $livewire) {
                            $current = $get('status');
                            if (! $current) return;

                            if (! static::canSetStatus($state)) {
                                $set('status_ui', $current);
                                Notification::make()->danger()->title('Нет прав на установку этого статуса')->send();
                                return;
                            }

                            $oldRank = OrderStatus::from($current)->rank();
                            $newRank = OrderStatus::from($state)->rank();

                            if ($newRank >= $oldRank) {
                                $set('status', $state);
                                $set('status_ui', $state);
                                $livewire->prevStatus = $state;
                                $set('downgrade_pending', false);
                                $set('pending_status', null);
                                $set('downgrade_reason', null);
                                return;
                            }

                            if (! static::canDowngrade()) {
                                $set('status_ui', $current);
                                Notification::make()->danger()->title('Нет прав возвращать статус назад')->send();
                                return;
                            }

                            $set('status_ui', $current);
                            $set('pending_status', $state);
                            $set('downgrade_pending', true);
                        }),

                    Group::make([
                        Textarea::make('downgrade_reason')
                            ->label(__('order.fields.rollback_reason'))
                            ->placeholder(__('order.placeholders.rollback_reason'))
                            ->required()
                            ->rows(3)
                            ->dehydrated(false),

                        Actions::make([
                            FormAction::make('confirmDowngradeInline')
                                ->label(__('order.actions.confirm_rollback'))
                                ->color('danger')
                                ->icon('heroicon-m-arrow-uturn-left')
                                ->action(function (callable $get, callable $set, $livewire, Order $record) {
                                    $to     = $get('pending_status');
                                    $reason = (string) $get('downgrade_reason');
                                    if (! $to) return;

                                    if (! static::canSetStatus($to) || ! static::canDowngrade()) {
                                        Notification::make()->danger()->title('Нет прав')->send();
                                        return;
                                    }

                                    $from = $record->status->value;
                                    $record->extra_reason = $reason;
                                    $record->status = OrderStatus::from($to);
                                    $record->save();

                                    activity('order')->performedOn($record)->causedBy(auth('admin')->user())
                                        ->event('status_downgraded')->withProperties([
                                            'action' => 'status_downgraded',
                                            'from'   => $from,
                                            'to'     => $to,
                                            'reason' => $reason,
                                        ])->log(__('order.journal.status_rollback'));

                                    $set('status', $to);
                                    $set('status_ui', $to);
                                    $set('downgrade_pending', false);
                                    $set('pending_status', null);
                                    $set('downgrade_reason', null);
                                    $livewire->prevStatus = $to;

                                    Notification::make()->success()->title('Статус откатан')->send();
                                }),
                            FormAction::make('cancelDowngradeInline')
                                ->label(__('order.actions.cancel'))
                                ->color('gray')
                                ->icon('heroicon-m-x-mark')
                                ->action(function (callable $set) {
                                    $set('downgrade_pending', false);
                                    $set('pending_status', null);
                                    $set('downgrade_reason', null);
                                }),
                        ])->alignment('left'),
                    ])->visible(fn (callable $get) => (bool) $get('downgrade_pending')),
                ]),

            Section::make(__('order.sections.metadata'))
                ->schema([
                    Placeholder::make('created_at')
                        ->label(__('order.fields.created_at'))
                        ->content(fn (Order $record): ?string => $record->created_at?->diffForHumans()),
                    Placeholder::make('updated_at')
                        ->label(__('order.fields.updated_at'))
                        ->content(fn (Order $record): ?string => $record->updated_at?->diffForHumans()),
                ]),
        ];
    }



    public static function getInfoTabSchema(): array
    {
        return [
            Grid::make(12)->schema([

                // 1) Номер заказа — компактное поле
                TextInput::make('number')
                    ->label(__('order.fields.order_number'))
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder(fn (Order $r) => $r?->exists ? $r->number : __('order.placeholders.number_auto'))
                    ->columnSpan(3),

                // 2) Клиент — с create/edit в модалках из ClientResource
                Select::make('clients_id')
                    ->relationship('clients', 'name')
                    ->searchable()
                    ->label(__('order.fields.client'))
                    ->required()
                    ->live()
                    // === ЛЕЙБЛ ПУНКТА (выбранное значение) ===
                    // Если выбран клиент — показываем "Имя · +38 (...)"
                    ->getOptionLabelUsing(function ($value) {
                        if (!$value) return null;
                        $c = Client::query()->select('id','name','phone')->find($value);
                        return $c ? ($c->name . ' · ' . $c->phone_pretty) : null;
                    })
                    // На всякий случай (когда рисуется из relationship)
                    ->getOptionLabelFromRecordUsing(fn (Client $c) => $c->name . ' · ' . $c->phone_pretty)
                    // === ПОИСК ===
                    ->getSearchResultsUsing(function (string $search) {
                        $digits = preg_replace('/\D+/', '', $search); // только цифры из запроса
                        return Client::query()
                            ->select('id','name','phone')
                            ->when($search !== '', fn ($q) =>
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                            )
                            ->when($digits !== '', fn ($q) =>
                                // MySQL 8+: убираем всё, что не цифры, и ищем подстроку
                            $q->orWhereRaw("REGEXP_REPLACE(phone, '[^0-9]', '') LIKE ?", ["%{$digits}%"])
                            )
                            ->orderBy('name')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (Client $c) => [
                                $c->id => $c->name . ' · ' . $c->phone_pretty,
                            ]);
                    })
                    ->optionsLimit(50)
                    ->afterStateUpdated(function ($state, Set $set) {
                        // $state — это выбранный clients_id
                        $phone = $state
                            ? Client::query()->whereKey($state)->value('phone')
                            : null;

                        $set('client_phone_view', $phone ?? '');
                    })
                    ->afterStateHydrated(function (Get $get, Set $set) {
                        // При открытии формы (редактирование/создание) подставим телефон,
                        // если клиент уже выбран:
                        $id = $get('clients_id');
                        $set('client_phone_view', $id
                            ? Client::query()->whereKey($id)->value('phone')
                            : ''
                        );
                    })
                    ->createOptionForm(fn (Form $form) => ClientResource::form($form))
                    ->createOptionUsing(function (array $data) { $client = Client::create($data); return $client->getKey(); })
                    ->createOptionAction(function (FormAction $action) { return $action
                        ->modalHeading('Создание клиента')
                        ->modalSubmitActionLabel('Создать клиента')
                        ->modalWidth('4xl');
               })

                    ->editOptionForm(fn (Form $form) => ClientResource::form($form))
                    ->editOptionAction(fn (FormAction $action) => $action
                        ->modalHeading('Редактирование клиента')
                        ->modalSubmitActionLabel('Сохранить')
                        ->modalWidth('4xl') )
                    ->columnSpan(6),

                // 3) Телефон клиента — read-only + кнопка "копировать"
                TextInput::make('client_phone_view')
                    ->label(__('order.fields.phone'))
                    ->readOnly()            // нельзя редактировать
                    ->dehydrated(false)     // не сохраняем в модель заказа
                    ->reactive()            // чтобы перерисовывалось
                    ->extraAttributes(['x-data' => '{}'])// общий Alpine-контекст
                    ->extraInputAttributes([
                        'x-ref'   => 'cpInput',          // ссылка на input
                        'readonly'=> true,               // ВАЖНО: только для чтения, не disabled
                        'tabindex'=> 0,                  // можно фокуснуть
                    ])
                   // ->dependsOn('clients_id')
                    ->afterStateHydrated(function (TextInput $component, Get $get) {
                        $id = $get('clients_id');
                        $component->state($id ? (Client::find($id)->phone ?? '') : '');
                    })
                    ->formatStateUsing(function (Get $get) {
                        $id = $get('clients_id');
                        return $id ? (Client::find($id)->phone ?? '') : '';
                    })



                    ->columnSpan(3),
            ]),

               /* ->createOptionAction(function (FormAction $action) {
                    return $action->modalHeading('Создание клиента')->modalSubmitActionLabel('Создать клиента')->modalWidth('4xl');
                })*/

            Hidden::make('client_address_id')->dehydrated(true),

            Section::make(__('order.sections.time_payment'))
                ->schema([
                    Grid::make(12)->schema([
                        DatePicker::make('dat')
                            ->label(__('order.fields.created_date'))
                            ->default(fn (?Order $record) => $record?->exists ? null : now())
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (! $get('date_order')) {
                                    $set('date_order', $state);
                                }
                            })
                            ->columnSpan(3),

                        TimePicker::make('time_start')
                            ->label(__('order.fields.created_time'))
                            ->seconds(false)
                            ->default(fn (?Order $record) => $record?->exists ? null : Carbon::now()->format('H:i'))
                            ->live()
                            ->reactive()
                            /*->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (! $state) return;
                                $add = $get('self_pickup') ? 15 : 60;
                                $set('time_order', Carbon::parse($state)->addMinutes($add)->format('H:i'));
                            })*/
                            ->columnSpan(3),

                        TimePicker::make('time_order')
                            ->label(__('order.fields.order_time'))
                            ->seconds(false)
                            ->default(fn () => Carbon::now(config('app.timezone'))->addMinutes(60)->format('H:i'))
                            ->afterStateHydrated(function ($component, $state) {
                                if (blank($state)) {
                                    $component->state(
                                        Carbon::now(config('app.timezone'))->addMinutes(60)->format('H:i')
                                    );
                                }
                            })
                            ->live()
                            ->columnSpan(3),

                        DatePicker::make('date_order')
                            ->label(__('order.fields.order_date'))
                            ->default(now())
                            ->columnSpan(3),
                    ]),

                    Grid::make(12)->schema([
                        Toggle::make('as_soon_possible')
                            ->label(__('order.fields.asap'))
                            ->inline(false)
                            ->live()
                            ->columnSpan(3),

                        Toggle::make('self_pickup')
                            ->label(__('order.fields.pickup'))
                            ->inline(false)
                            ->live()
                            ->reactive()
                         /*   ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $start = $get('time_start');
                                if (! $start) return;
                                $add = $state ? 15 : 60;
                                $set('time_order', Carbon::parse($start)->addMinutes($add)->format('H:i'));
                            })*/
                            ->afterStateUpdated(function ($state, Set $set) {
                                $add = $state ? 15 : 60;

                             // Берём текущее время в Киеве
                             $dt = Carbon::now(config('app.timezone'))->addMinutes($add);

                             // Обновляем оба поля
                             $set('time_order', $dt->format('H:i'));     // TimePicker
                             $set('date_order', $dt->toDateString());    // DatePicker ожидает Y-m-d
                            })

                            ->columnSpan(3),

                    /*    Select::make('payment')
                            ->label('Способ оплаты')
                            ->options([
                                1=> 'Кредитная карта',
                                2 => 'Наличкой',
                                3 => 'Клубная карта (кредит/депозит)',
                                4 => 'Безналичная через организацию',
                                5 => 'Без оплаты',
                                9 => 'Оплата через POS-термінал',
                                10 => 'Рахунок-фактура (для корпоративних клієнтів)',
                                11 => 'LiqPay',
                            ])*/
                            Select::make('payment')
                                ->label(__('order.fields.payment_method'))
                                ->options(PaymentMethodEnum::options())
                                ->required()
                                ->native(false)
                                ->searchable()
                            ->default(1)
                            ->live()
                            ->reactive()
                            ->columnSpan(4),

                        TextInput::make('reason_non_payment')
                            ->label(__('order.fields.non_payment_reason'))
                            ->placeholder(__('order.placeholders.reason_short'))
                            ->visible(fn (Get $get) => (int) $get('payment') === 5)
                            ->maxLength(255)
                            ->columnSpan(12),
                    ]),

                ]),

            Select::make('selected_address_id')
                ->label(__('order.fields.delivery_address'))
                ->placeholder(__('order.placeholders.select_address'))
                ->default('')
                ->live()
                ->hidden(fn (Get $get) => (bool) $get('self_pickup'))
                ->afterStateHydrated(function (Select $component, ?Order $record, callable $set) {
                    if ($record && $record->client_address_id) {
                        $component->state((string) $record->client_address_id);
                        $address = ClientAddress::find($record->client_address_id);
                        if ($address) {
                            $addressData = $address->only([
                                'street','house','apartment','intercom','floor','entrance','zip','city','country','note','type','is_private_house',
                            ]);

                            // Получаем координаты из сохраненного адреса заказа, если они есть
                            $orderAddress = $record->address ?? [];
                            if (isset($orderAddress['latitude']) && isset($orderAddress['longitude'])) {
                                $addressData['latitude'] = $orderAddress['latitude'];
                                $addressData['longitude'] = $orderAddress['longitude'];
                                $addressData['formatted_address'] = $orderAddress['formatted_address'] ?? null;
                            } else {
                                // Если координат нет, получаем их через API
                                $coordinates = static::getCoordinatesForAddress($address);
                                if ($coordinates) {
                                    $addressData['latitude'] = $coordinates['latitude'];
                                    $addressData['longitude'] = $coordinates['longitude'];
                                    $addressData['formatted_address'] = $coordinates['formatted_address'] ?? null;
                                }
                            }

                            $set('address', $addressData);

                        }
                    }
                })
                ->afterStateUpdated(function ($state, callable $set, Get $get, ?Order $record) {
                    if ($state !== '-1') {
                        $set('client_address_id', (int) $state);
                    }

                    if (! $state || $state === '-1') {
                        $set('address', [
                            'street_place_id'=> null,
                            'street'=> null,
                            'house'=> null,
                            'apartment'=> null,
                            'intercom'=> null,
                            'floor'=> null,
                            'entrance'=> null,
                            'zip'=> null,
                            'city'=> 'Київ',
                            'country'=> null,
                            'note'=> null,
                            'type'=> null,
                            'is_private_house'=> false,
                            'latitude'=> null,
                            'longitude'=> null,
                            'formatted_address'=> null,
                        ]);

                        $set('delivery_coords_trigger', 'reset_' . time());
                        $set('delivery_price_auto', 'reset_' . time());
                        return;
                    }

                    $address = ClientAddress::find($state);
                    if (! $address) return;

                    $addressData = $address->only([
                        'street','house','apartment','intercom','floor','entrance','zip','city','country','note','type','is_private_house',
                        'latitude','longitude','street_place_id','formatted_address',
                    ]);

                    // fallback: если в адресе нет coords, но в заказе были — можно использовать их
                    if (empty($addressData['latitude']) || empty($addressData['longitude'])) {
                        $orderAddress = $record ? ((array) ($record->address ?? [])) : [];
                        if (!empty($orderAddress['latitude']) && !empty($orderAddress['longitude'])) {
                            $addressData['latitude'] = (float) $orderAddress['latitude'];
                            $addressData['longitude'] = (float) $orderAddress['longitude'];
                            $addressData['street_place_id'] = $addressData['street_place_id'] ?? ($orderAddress['street_place_id'] ?? null);
                            $addressData['formatted_address'] = $addressData['formatted_address'] ?? ($orderAddress['formatted_address'] ?? null);
                        }
                    }

                    $set('address', $addressData);
                    $selfPickup = (bool) ($get('self_pickup') ?? false);

                    if ($selfPickup) {
                        $set('shipping_price', 0);
                        return;
                    }

                    $lat = $addressData['latitude'] ?? null;
                    $lng = $addressData['longitude'] ?? null;

                    if ($lat && $lng && $record) {
                        $deliveryService = app(\App\Services\DeliveryCalculationService::class);

                        $baseTotal = static::calcBaseTotalFromGet($get);

                        $hasAdjustments = $record->adjustments()->exists();
                        $orderTotal = $hasAdjustments
                            ? (float) ($record->grand_total ?? 0)
                            : (float) $baseTotal;

                        $tempOrder = clone $record;
                        $tempOrder->address = $addressData;
                        $tempOrder->self_pickup = $selfPickup;

                        $delivery = $deliveryService->calculateDelivery($tempOrder, $orderTotal);

                        $calculatedPrice = (float) ($delivery['price'] ?? 0);

                        // ВАЖНО: при выборе сохранённого адреса всегда обновляем цену автоматически
                        $set('shipping_price', $calculatedPrice);

                        // опционально: чтобы триггеры/плейсхолдеры тоже “шевельнулись”
                        $set('delivery_coords_trigger', 'coords_' . $lat . '_' . $lng . '_' . time());
                    } else {
                        // координат нет — доставка не может пересчитаться
                        // можно оставить старую сумму или обнулить (на твой выбор)
                        // $set('shipping_price', 0);
                    }
                    // триггерим пересчет если coords есть
                    if (!empty($addressData['latitude']) && !empty($addressData['longitude'])) {
                        $key = 'coords_' . $addressData['latitude'] . '_' . $addressData['longitude'] . '_' . time();
                        $set('delivery_coords_trigger', $key);
                        $set('delivery_price_auto', 'auto_' . $key);
                    } else {
                        // нет coords: покажи подсказку (а пересчет невозможен)
                        $set('delivery_coords_trigger', 'error_no_coords_' . time());
                    }
                })

                ->options(function (callable $get) {
                    $clientId = $get('clients_id');
                    if (! $clientId) return [];

                    $addresses = ClientAddress::query()->where('client_id', $clientId)->get();
                    $final = collect(['-1' => __('order.fields.new_address')])->union(
                        $addresses->mapWithKeys(function ($address) {
                            $key = (string) $address->id;
                            $label = trim(implode(', ', array_filter([
                                $address->street,
                                $address->house,
                                $address->apartment ? __('order.address_prefixes.apartment') . ' ' . $address->apartment : null,
                                $address->entrance ? __('order.address_prefixes.entrance') . ' ' . $address->entrance : null,
                                $address->floor ? __('order.address_prefixes.floor') . ' ' . $address->floor : null,
                                $address->intercom ? __('order.address_prefixes.intercom') . ' ' . $address->intercom : null,
                            ])));
                            return [$key => $label];
                        })
                    );
                    return $final->all();
                })
                ->searchable()
                ->reactive()
                ->live()
                ->columnSpanFull(),

            AddressForm::make('address')
                ->key('address-form')
                ->visible(fn (Get $get) => ! $get('self_pickup') && filled($get('selected_address_id')))
                ->columnSpan('full'),

            Select::make('currency')
                ->searchable()
                ->label(__('order.fields.currency'))
                ->options(Currency::pluck('name', 'code'))
                ->default('UAH')
                ->required(),

            MarkdownEditor::make('notes')->label(__('order.fields.notes'))->columnSpan('full'),
        ];
    }

    public static function getRightFormSchema(): array
    {
        return  [
            AddressForm::make('address')
                ->key('address-form')
                ->visible(fn (Get $get) => filled($get('selected_address_id')))
                ->columnSpan('full'),
        ];
    }

    public static function canSetStatus(string|OrderStatus $status): bool
    {
        $name = $status instanceof OrderStatus ? $status->value : $status;
        $u = auth('admin')->user();
        if (! $u || !$u instanceof \App\Models\User) return false;
        return $u->can('set_order_status_' . $name);
    }

    public static function canDowngrade(): bool
    {
        $u = auth('admin')->user();
        return ($u && $u instanceof \App\Models\User) ? $u->can('order_status_downgrade') : false;
    }

    protected static function allowedStatuses(): array
    {
        return collect(OrderStatus::sorted())
            ->filter(fn (OrderStatus $s) => static::canSetStatus($s->value))
            ->mapWithKeys(fn (OrderStatus $s) => [$s->value => $s->getLabel()])
            ->all();
    }
    protected static function productOptionsTree(string $locale, ?string $search = null, int $limit = 50): array
    {
        $locale = preg_replace('/[^a-zA-Z0-9_\-]/', '', $locale) ?: config('app.locale', 'uk');
        $like = '%' . trim($search ?? '') . '%';

        $q = \App\Models\Shop\Product::query()
            ->select(['id','title','short_name','parent_id','sku','sort'])
            ->where('in_stock', 1)
            ->orderByRaw('COALESCE(parent_id, id), CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END, sort ASC, id ASC');

        // Поиск (важно: JSON_VALID чтобы не падало на битом JSON)
        if ($search !== null && trim($search) !== '') {
            $q->where(function ($qq) use ($like, $locale) {
                // short_name (обычно строка)
                $qq->orWhere('short_name', 'like', $like);

                // sku
                $qq->orWhere('sku', 'like', $like);

                // title: если JSON валидный — ищем по переводу, если нет — ищем как по обычной строке
                $path = '$."' . $locale . '"';

                $qq->orWhereRaw(
                    "(JSON_VALID(title) AND JSON_UNQUOTE(JSON_EXTRACT(title, '{$path}')) LIKE ?)",
                    [$like]
                );

                $qq->orWhereRaw(
                    "(NOT JSON_VALID(title) AND title LIKE ?)",
                    [$like]
                );

                // + можно еще fallback по uk если нужно:
                $qq->orWhereRaw(
                    "(JSON_VALID(title) AND JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"uk\"')) LIKE ?)",
                    [$like]
                );
            });
        }

        $items = $q->limit($limit)->get();

        // чтобы для детей можно было быстро взять родителя (без N+1)
        $parentIds = $items->pluck('parent_id')->filter()->unique()->values();
        $parents = $parentIds->isEmpty()
            ? collect()
            : \App\Models\Shop\Product::query()
                ->select(['id','title','short_name','parent_id','sku','sort'])
                ->whereIn('id', $parentIds)
                ->get()
                ->keyBy('id');

        $out = [];
        foreach ($items as $p) {
            $parent = $p->parent_id ? $parents->get($p->parent_id) : null;

            // Для детей хотим "↳ child — parent"
            $out[$p->id] = static::formatProductLabel($p, $locale, withParentForChild: true, parent: $parent);
        }

        return $out;
    }
    protected static function productLabel(\App\Models\Shop\Product $p, string $locale): string
    {
        $short = static::safeTranslate($p->getRawOriginal('short_name'), $locale);
        $title = static::safeTranslate($p->getRawOriginal('title'), $locale);

        $name = trim((string)($short ?: $title ?: $p->short_name ?: $p->title ?: ''));
        $sku  = trim((string)($p->sku ?? ''));

        // [23] [29] — это твой размер/sku
        $size = $sku !== '' ? " [{$sku}]" : '';

        // чтобы дочерние шли под главным визуально
        $prefix = $p->parent_id ? "↳ " : "";

        return $prefix . $name . $size;
    }
    /*  protected static function productOptionsTree(string $locale, ?string $search = null, int $limit = 50): array
       {
           $locale = preg_replace('/[^a-zA-Z0-9_\-]/', '', $locale) ?: config('app.locale', 'uk');
           $like = '%' . trim($search ?? '') . '%';

           $q = \App\Models\Shop\Product::query()
               ->select(['id','title','short_name','parent_id','sku','sort'])
               ->where('in_stock', 1)
               ->orderByRaw('COALESCE(parent_id, id), CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END, sort ASC, id ASC');

           // Поиск (важно: JSON_VALID чтобы не падало на битом JSON)
           if ($search !== null && trim($search) !== '') {
               $q->where(function ($qq) use ($like, $locale) {
                   // short_name (обычно строка)
                   $qq->orWhere('short_name', 'like', $like);

                   // sku
                   $qq->orWhere('sku', 'like', $like);

                   // title: если JSON валидный — ищем по переводу, если нет — ищем как по обычной строке
                   $path = '$."' . $locale . '"';

                   $qq->orWhereRaw(
                       "(JSON_VALID(title) AND JSON_UNQUOTE(JSON_EXTRACT(title, '{$path}')) LIKE ?)",
                       [$like]
                   );

                   $qq->orWhereRaw(
                       "(NOT JSON_VALID(title) AND title LIKE ?)",
                       [$like]
                   );

                   // + можно еще fallback по uk если нужно:
                   $qq->orWhereRaw(
                       "(JSON_VALID(title) AND JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"uk\"')) LIKE ?)",
                       [$like]
                   );
               });
           }

           $items = $q->limit($limit)->get();

           // чтобы для детей можно было быстро взять родителя (без N+1)
           $parentIds = $items->pluck('parent_id')->filter()->unique()->values();
           $parents = $parentIds->isEmpty()
               ? collect()
               : \App\Models\Shop\Product::query()
                   ->select(['id','title','short_name','parent_id','sku','sort'])
                   ->whereIn('id', $parentIds)
                   ->get()
                   ->keyBy('id');

           $out = [];
           foreach ($items as $p) {
               $parent = $p->parent_id ? $parents->get($p->parent_id) : null;

               // Для детей хотим "↳ child — parent"
               $out[$p->id] = static::formatProductLabel($p, $locale, withParentForChild: true, parent: $parent);
           }

           return $out;
       }*/
    protected static function safeTranslate(?string $raw, string $locale): ?string
    {
        if ($raw === null || $raw === '') return null;

        $trim = ltrim($raw);
        // если НЕ JSON — возвращаем как есть
        if ($trim === '' || ($trim[0] !== '{' && $trim[0] !== '[')) {
            return $raw;
        }

        $arr = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($arr)) {
            // невалидный JSON — вернём как строку
            return $raw;
        }

        return $arr[$locale]
            ?? $arr[config('app.locale')]
            ?? (is_string(reset($arr)) ? reset($arr) : $raw);
    }
  /*  protected static function productOptionsTree(string $locale, ?string $search = null, int $limit = 50): array
    {
        $locale = preg_replace('/[^a-zA-Z0-9_\-]/', '', $locale) ?: config('app.locale', 'uk');
        $like = '%' . trim($search ?? '') . '%';

        $q = \App\Models\Shop\Product::query()
            ->select(['id','title','short_name','parent_id','sku','sort'])
            ->where('in_stock', 1)
            ->orderByRaw('COALESCE(parent_id, id), CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END, sort ASC, id ASC');

        // Поиск (важно: JSON_VALID чтобы не падало на битом JSON)
        if ($search !== null && trim($search) !== '') {
            $q->where(function ($qq) use ($like, $locale) {
                // short_name (обычно строка)
                $qq->orWhere('short_name', 'like', $like);

                // sku
                $qq->orWhere('sku', 'like', $like);

                // title: если JSON валидный — ищем по переводу, если нет — ищем как по обычной строке
                $path = '$."' . $locale . '"';

                $qq->orWhereRaw(
                    "(JSON_VALID(title) AND JSON_UNQUOTE(JSON_EXTRACT(title, '{$path}')) LIKE ?)",
                    [$like]
                );

                $qq->orWhereRaw(
                    "(NOT JSON_VALID(title) AND title LIKE ?)",
                    [$like]
                );

                // + можно еще fallback по uk если нужно:
                $qq->orWhereRaw(
                    "(JSON_VALID(title) AND JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"uk\"')) LIKE ?)",
                    [$like]
                );
            });
        }

        $items = $q->limit($limit)->get();

        // чтобы для детей можно было быстро взять родителя (без N+1)
        $parentIds = $items->pluck('parent_id')->filter()->unique()->values();
        $parents = $parentIds->isEmpty()
            ? collect()
            : \App\Models\Shop\Product::query()
                ->select(['id','title','short_name','parent_id','sku','sort'])
                ->whereIn('id', $parentIds)
                ->get()
                ->keyBy('id');

        $out = [];
        foreach ($items as $p) {
            $parent = $p->parent_id ? $parents->get($p->parent_id) : null;

            // Для детей хотим "↳ child — parent"
            $out[$p->id] = static::formatProductLabel($p, $locale, withParentForChild: true, parent: $parent);
        }

        return $out;
    }*/
    protected static function formatProductLabel(
        \App\Models\Shop\Product $p,
        string $locale,
        bool $withParentForChild = true,
        ?\App\Models\Shop\Product $parent = null
    ): string {
        $childName = trim((string) ($p->short_name ?? ''));
        if ($childName === '') {
            $childName = static::safeTranslateJson($p->getRawOriginal('title'), $locale)
                ?? (string) ($p->title ?? '');
        }

        $sku = trim((string) ($p->sku ?? ''));
        $suffix = $sku !== '' ? " [{$sku}]" : '';

        // родительский товар
        if (!$p->parent_id) {
            return $childName . $suffix;
        }

        // дочерний товар
        if (!$withParentForChild) {
            return "↳ {$childName}" . $suffix;
        }

        $parentName = '';
        if ($parent) {
            $parentName = trim((string) ($parent->short_name ?? ''));
            if ($parentName === '') {
                $parentName = static::safeTranslateJson($parent->getRawOriginal('title'), $locale)
                    ?? (string) ($parent->title ?? '');
            }
        }

        // нормализуем, чтобы сравнение было честным
        $norm = fn ($s) => mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $s)));

        // если совпадают — НЕ добавляем "— parent"
        if ($parentName !== '' && $norm($childName) === $norm($parentName)) {
            return "↳ {$childName}" . $suffix;
        }

        // иначе показываем как задумано
        $label = "↳ {$childName}";
        if ($parentName !== '') {
            $label .= " — {$parentName}";
        }

        return $label . $suffix;
    }


    public static function getItemsRepeater(): Repeater
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');

        return Repeater::make('items')
            ->relationship()
            ->addActionLabel(__('order.actions.add_item'))
            ->schema([
                Grid::make(12)->schema([
                    Select::make('product_id')
                        ->label(__('order.fields.product'))
                        ->searchable()
                        ->preload()        // можно оставить, но основное — поиск ниже
                        ->optionsLimit(50)
                        ->getSearchResultsUsing(function (string $search) use ($defaultLocale) {

                            $search = trim($search);
                            $q = \App\Models\Shop\Product::query()
                                ->select(['id','title','short_name','parent_id','sort','sku'])
                                ->where('in_stock', 1); // активные (и главные и дочерние)

                            if ($search !== '') {
                                $like = "%{$search}%";

                                // title
                                $q->where(function ($w) use ($like, $defaultLocale) {
                                    $w->whereRaw("JSON_VALID(title) AND JSON_UNQUOTE(JSON_EXTRACT(title, ?)) LIKE ?", ["$.{$defaultLocale}", $like])
                                        ->orWhereRaw("NOT JSON_VALID(title) AND title LIKE ?", [$like]);
                                });

                                // short_name
                                $q->orWhere(function ($w) use ($like, $defaultLocale) {
                                    $w->whereRaw("JSON_VALID(short_name) AND JSON_UNQUOTE(JSON_EXTRACT(short_name, ?)) LIKE ?", ["$.{$defaultLocale}", $like])
                                        ->orWhereRaw("NOT JSON_VALID(short_name) AND short_name LIKE ?", [$like]);
                                });

                                // sku (размер)
                                $q->orWhere('sku', 'like', $like);
                            }

                            // сортировка: родитель -> дети
                            $q->orderByRaw("COALESCE(parent_id, id) ASC")
                                ->orderByRaw("CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END ASC")
                                ->orderBy('sort')
                                ->orderBy('id')
                                ->limit(50);

                            $items = $q->get();

                            return $items->mapWithKeys(fn ($p) => [
                                $p->id => static::productLabel($p, $defaultLocale),
                            ])->toArray();
                        })
                        ->getOptionLabelUsing(function ($value) use ($defaultLocale) {
                            if (!$value) return null;

                            $p = \App\Models\Shop\Product::query()
                                ->select(['id','title','short_name','parent_id','sku'])
                                ->find($value);

                            return $p ? static::productLabel($p, $defaultLocale) : null;
                        })
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                            $product = \App\Models\Shop\Product::find($state);

                            if (!$product) {
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('Товар не найден')
                                    ->send();
                                $set('product_id', null);
                                $set('unit_price', 0);
                                return;
                            }

                            $set('unit_price', $product->price ?? 0);

                            $mods = $get('modifiers') ?? [];
                            foreach ($mods as &$m) {
                                $m['_product_id']    = $state;
                                $m['value_id']       = $m['value_id'] ?? null;
                                $m['price_modifier'] = $m['price_modifier'] ?? 0;
                            }
                            $set('modifiers', $mods);

                            $set('order_total', now());
                        })
                        ->columnSpan(6),




        TextInput::make('qty')
                        ->label(__('order.fields.quantity'))
                        ->numeric()
                        ->live(debounce: 250)
                        ->afterStateUpdated(fn ($state, callable $get, callable $set) => $set('order_total', now()))
                        ->default(1)
                        ->columnSpan(2)
                        ->required(),

                    TextInput::make('unit_price')
                        ->label(__('order.fields.price'))
                        ->dehydrated()
                        ->numeric()
                        ->live(debounce:500)
                        ->afterStateUpdated(fn ($state, callable $get, callable $set) => $set('order_total', now()))
                        ->required()
                        ->columnSpan(2),

                    Placeholder::make('item_total')
                        ->label(__('order.fields.sum'))
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

                Repeater::make('modifiers')
                    ->label('')
                    ->addActionLabel(false)
                    ->collapsed(true)
                    ->relationship('modifiers')
                    ->dehydrated(true)
                    ->columns(12)
                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data) {
                        $data['type'] = 'characteristic';
                        return $data;
                    })
                    ->mutateRelationshipDataBeforeSaveUsing(function (array $data) {
                        $data['type'] = 'characteristic';
                        return $data;
                    })
                    ->schema([
                        Grid::make(12)->schema([
                            Hidden::make('_product_id')
                                ->dehydrated(false)
                                ->default(fn (Get $get) => $get('../../product_id'))
                                ->afterStateHydrated(function ($state, Set $set, Get $get) {
                                    if (blank($state)) {
                                        $set('_product_id', $get('../../product_id'));
                                    }
                                }),

                            Select::make('value_id')
                                ->label(__('order.fields.characteristic_value'))
                                ->placeholder(__('order.placeholders.select_variant'))
                                ->reactive()
                                ->searchable()
                                ->preload()
                                ->live()
                                ->columnSpan(9)
                                ->options(function (Get $get) {
                                    $productId = $get('../../product_id');
                                    if (! $productId) return [];

                                    return \App\Models\Shop\CharacteristicValue::query()
                                        ->join('product_characteristic_value as pcv', 'pcv.characteristic_value_id', '=', 'characteristic_values.id')
                                        ->where('pcv.product_id', $productId)
                                        ->whereRaw('COALESCE(pcv.price_modifier, 0) <> 0')
                                        ->with('characteristic')
                                        ->orderBy('characteristic_id')
                                        ->get(['characteristic_values.*','pcv.price_modifier'])
                                        ->mapWithKeys(fn ($val) => [
                                            $val->id => "{$val->characteristic->name} - {$val->value} (+{$val->price_modifier}₴)",
                                        ])
                                        ->toArray();
                                })
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    $pid = $get('_product_id') ?: $get('../../product_id');
                                    if (! $state || ! $pid) return;

                                    $price = DB::table('product_characteristic_value')
                                        ->where('product_id', $pid)
                                        ->where('characteristic_value_id', $state)
                                        ->value('price_modifier');

                                    $set('price_modifier', $price ?? 0);
                                    $set('order_total', now());
                                }),

                            TextInput::make('price_modifier')
                                ->label(__('order.fields.price_modifier'))
                                ->numeric()
                                ->inputMode('decimal')
                                ->step('any')
                                ->default(0)
                                ->suffix('₴')
                                ->columnSpan(3),
                        ])
                    ])
                    ->collapsed(false)
                    ->itemLabel(fn (array $state): ?string => 'Модификатор')
                    ->defaultItems(0)
                    ->reorderable()
                    ->columns(3)
                    ->columnSpanFull(),
            ])
            ->columns(1)
            ->extraItemActions([
                FormAction::make('addModifier')
                    ->icon('heroicon-o-plus')
                    ->iconButton()
                    ->tooltip('Добавить характеристику')
                    ->color('gray')
                    ->action(function (array $arguments, Repeater $component): void {
                        $index = $arguments['item'];
                        $items = $component->getState() ?? [];
                        $productId = data_get($items, "{$index}.product_id");
                        $mods = data_get($items, "{$index}.modifiers", []);
                        $mods[] = [
                            '_product_id'    => $productId,
                            'value_id'       => null,
                            'price_modifier' => 0,
                        ];
                        data_set($items, "{$index}.modifiers", $mods);
                        $component->state($items);
                    })
                    ->hidden(fn (array $arguments, Repeater $component): bool =>
                    blank(data_get($component->getRawItemState($arguments['item']) ?? [], 'product_id'))
                    ),
                FormAction::make('openProduct')
                    ->tooltip('Open product')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(function (array $arguments, Repeater $component): ?string {
                        $itemData = $component->getRawItemState($arguments['item']);
                        $product = Product::find($itemData['product_id'] ?? null);
                        if (! $product) return null;
                        return ProductResource::getUrl('edit', ['record' => $product]);
                    }, shouldOpenInNewTab: true)
                    ->hidden(fn (array $arguments, Repeater $component): bool => blank($component->getRawItemState($arguments['item'])['product_id'] ?? null)),
            ])
            ->orderColumn('sort')
            ->defaultItems(1)
            ->hiddenLabel()
            ->columns(['md' => 10])
            ->required();
    }

    // =========================
    //   Таблица с модалкой «Статусы»
    // =========================
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['clients', 'clientAddress', 'lastLiqpayLog']) // ← исправление N+1 проблемы
            )
            ->columns([
                TextColumn::make('number')
                    ->label('')
                    ->extraHeaderAttributes([

                        'style' => 'line-height:1.1;min-width:3rem;width:3rem;',
                        'x-data' => '{}',
                        // вставляем "родной" лейбл Filament, чтобы стили совпали
                        'x-html' => json_encode(
                            '<span class="fi-ta-header-cell-label text-sm font-medium">'
                            .'Номер<br>заказа'
                            .'</span>'
                        ) ])
                    ->grow(false) // чтобы колонка не ужималась другими

                    ->extraCellAttributes(['style' => 'min-width:3rem;width:3rem;'])
                    ->searchable()
                    ->searchable(isIndividual: true)
                    ->verticalAlignment(VerticalAlignment::Center)
                    ->sortable(),
                  //  ->extraAttributes(['class' => 'cursor-pointer underline']),
                  //  ->action('statuses'), // клик по номеру откроет модалку статусов

                TextColumn::make('clients.name')->searchable()->label(__('order.columns.client'))->sortable()->toggleable()->searchable(isIndividual: true)
                    ->extraHeaderAttributes(['style' => 'min-width:10rem;width:10rem;'])
                    ->extraCellAttributes(['style' => 'min-width:10rem;width:10rem;'])
                    ->url(fn (Order $record) =>
                    $record->clients_id
                        ? ClientResource::getUrl('edit', ['record' => $record->clients_id])
                        : null
                    )
                    ->openUrlInNewTab(),
                TextColumn::make('clients.phone')->searchable()->label(__('order.columns.phone'))->sortable()->toggleable()
                    ->searchable(isIndividual: true)
                    ->grow(false) // чтобы колонка не ужималась другими
                    ->extraHeaderAttributes(['style' => 'min-width:10rem;width:10rem;'])
                    ->extraCellAttributes(['style' => 'min-width:10rem;width:10rem;'])
                    ->copyable()
                    ->copyMessage(__('order.notifications.phone_copied'))
                    ->copyMessageDuration(1500),

                TextColumn::make('status')->label(__('order.columns.status'))->badge(),

                TextColumn::make('total_price')
                    ->label(__('order.columns.total_price'))
                    ->searchable()
                    ->sortable()
                    ->summarize([Sum::make()->money('UAH')]),

                TextColumn::make('discount_total')
                    ->label(__('order.columns.discount'))
                    ->formatStateUsing(fn ($state) =>
                    ($state ?? 0) != 0
                        ? number_format(((float) $state), 2, ',', ' ') . ' грн'
                        : '—'
                    )
                    ->badge()
                    ->color(fn ($state) => abs((float) ($state ?? 0)) > 0 ? 'success' : 'gray')
                    ->alignRight()
                    ->toggleable()
                    ->summarize([Sum::make()->money('UAH')]),

                TextColumn::make('grand_total')
                    ->extraHeaderAttributes([

                        'style' => 'line-height:1.1;',
                        'x-data' => '{}',
                        // вставляем "родной" лейбл Filament, чтобы стили совпали
                        'x-html' => json_encode(
                            '<span class="fi-ta-header-cell-label text-sm font-medium">'
                            .'Сумма<br>со скидкой'
                            .'</span>'
                        ) ])
                    ->searchable()
                    ->sortable()
                    ->summarize([Sum::make()->money('UAH')]),

                TextColumn::make('date_order')->label('')
                    ->extraHeaderAttributes([
                        'class' => 'th-wrap min-w-[10rem]',      // ← класс(ы) на <th>
                        'x-data' => '{}',
                        'x-html' => json_encode(
                            '<span class="fi-ta-header-cell-label text-sm font-medium">'
                            .'Дата<br>доставки'
                            .'</span>'
                        ),
                        'style'  => 'line-height: 1.1;', // опционально
                    ])
                    ->date()->toggleable(),
                TextColumn::make('time_order')->label('')
                    ->extraHeaderAttributes([
                        'x-data' => '{}',
                        'x-html' => json_encode(
                            '<span class="fi-ta-header-cell-label text-sm font-medium">'
                            .'Время<br>доставки'
                            .'</span>'
                        ),

                        'style'  => 'line-height: 1.1;', // опционально
                    ])
                    ->time('H:i')->toggleable(),
                TextColumn::make('delivery_info')
                    ->label('Доставка')
                    ->getStateUsing(fn (Order $record) => $record->self_pickup ? 'Самовывоз' : 'Доставка')
                    ->badge() // красивый бейдж
                    ->grow(false) // чтобы ширина не “съедалась” другими колонками
                    ->extraHeaderAttributes(['class' => 'min-w-[22rem] w-[22rem]'])
                    ->extraCellAttributes(['class' => 'min-w-[22rem] w-[22rem]'])
                    ->icon(fn ($record) => $record->self_pickup ? 'heroicon-m-shopping-bag' : 'heroicon-m-map-pin')
                    ->color(fn ($record) => $record->self_pickup ? 'warning' : 'primary') // другой цвет для самовывоза
                    // Подпись мелким текстом — адрес (только если не самовывоз)
                    ->description(function (Order $record) {
                        if ($record->self_pickup) return null;

                        // 1) сначала пробуем привязанный адрес
                        if ($a = $record->clientAddress) {
                            $parts = [
                                $a->street,
                                $a->house,
                                $a->apartment ? __('order.address_prefixes.apartment') . ' ' . $a->apartment : null,
                                $a->entrance  ? __('order.address_prefixes.entrance') . ' ' . $a->entrance : null,
                                $a->floor     ? __('order.address_prefixes.floor') . ' ' . $a->floor : null,
                            ];
                            $line = trim(implode(', ', array_filter($parts)));
                            return $line !== '' ? $line : null;
                        }

                        // 2) иначе — из JSON поля order.address (если есть)
                        $addr = (array) ($record->address ?? []);
                        $parts = [
                            $addr['street']   ?? null,
                            $addr['house']    ?? null,
                            !empty($addr['apartment']) ? __('order.address_prefixes.apartment') . ' ' . $addr['apartment'] : null,
                            !empty($addr['entrance'])  ? __('order.address_prefixes.entrance') . ' ' . $addr['entrance']  : null,
                            !empty($addr['floor'])     ? __('order.address_prefixes.floor') . ' ' . $addr['floor']       : null,
                        ];
                        $line = trim(implode(', ', array_filter($parts)));
                        return $line !== '' ? $line : '—';
                    })
                    ->wrap()        // перенос длинных адресов
                    ->toggleable(), // можно спрятать в настройках таблицы
                TextColumn::make('payment')
                    ->label(__('Оплата'))
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(
                        fn (null|PaymentMethodEnum $state) => $state?->label() ?? '—'
                    ),
                // ⬇️ НОВАЯ КОЛОНКА L i q P a y
                BadgeColumn::make('liqpay_status')
                    ->label('LiqPay')
                    ->getStateUsing(fn (Order $record) => $record->lastLiqpayLog?->status)
                    // что писать на бейдже
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'success', 'sandbox'        => 'Успішно',
                            'wait_accept', 'processing' => 'В обробці',
                            'failure', 'error'          => 'Помилка',
                            'reversed', 'refunded'      => 'Повернення',
                            default                     => 'Немає',
                        };
                    })
                    // цвет бейджа
                    ->color(function ($state) {
                        return match ($state) {
                            'success', 'sandbox'        => 'success',   // зелёный
                            'wait_accept', 'processing' => 'warning',   // жёлтый
                            'failure', 'error'          => 'danger',    // красный
                            'reversed', 'refunded'      => 'gray',      // серый
                            default                     => 'secondary', // обычный
                        };
                    })
                    // краткий коммент при наведении
                    ->tooltip(function (Order $record) {
                        $log = $record->lastLiqpayLog;

                        if (! $log) {
                            return 'Callback від LiqPay ще не приходив';
                        }

                        $payload = is_array($log->payload)
                            ? $log->payload
                            : (json_decode($log->payload ?? '[]', true) ?: []);

                        $err = $payload['err_description'] ?? $payload['err_code'] ?? null;

                        return match ($log->status) {
                            'success', 'sandbox'        => 'Оплата пройшла успішно',
                            'wait_accept', 'processing' => 'Платіж ще обробляється LiqPay',
                            'failure', 'error'          => $err
                                ? 'Помилка оплати: ' . $err
                                : 'Помилка оплати на стороні LiqPay',
                            'reversed', 'refunded'      => 'Платіж повернуто / відшкодовано',
                            default                     => 'Статус LiqPay: невідомий',
                        };
                    })
                    ->sortable(false)
                    ->toggleable(),
                TextColumn::make('created_at')->label('')
                    ->extraHeaderAttributes([
                        'x-data' => '{}',
                        'x-html' => json_encode(
                            '<span class="fi-ta-header-cell-label text-sm font-medium">'
                            .'Дата<br>заказа'
                            .'</span>'
                        ),

                        'style'  => 'line-height: 1.1;', // опционально
                    ])
                    ->date()->toggleable(),
            ])  ->defaultSort('created_at', 'desc') // 👈 сортировка по умолчанию
            ->filters([
                SelectFilter::make('payment')     // то же имя поля
                ->label(__('Оплата'))
                    ->options(PaymentMethodEnum::options())
                    ->multiple()
                    ->preload(),

                TrashedFilter::make(),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->placeholder(fn ($state): string => 'Dec 18, ' . now()->subYear()->format('Y')),
                        DatePicker::make('created_until')->placeholder(fn ($state): string => now()->format('M d, Y')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
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
                // МОДАЛЬНОЕ ДЕЙСТВИЕ «Статусы»
                Action::make('statuses')
                    ->label(__('order.actions.statuses'))
                    ->icon('heroicon-m-adjustments-horizontal')
                    ->color('gray')
                    ->modalHeading(fn (Order $r) => __('order.actions.statuses_modal_heading', ['number' => $r->number]))
                    ->modalWidth('lg')
                    ->form(fn (Order $record) => static::statusModalForm())
                    ->action(function (array $data, Order $record) {
                        $user = auth('admin')->user();
                        if (!$user || !$user instanceof \App\Models\User) {
                            Notification::make()->danger()->title('Ошибка авторизации')->send();
                            return;
                        }

                        $from = $record->status;
                        $to   = OrderStatus::from($data['status_ui']);

                        if ($to->value === $from->value) {
                            Notification::make()->title(__('order.notifications.status_not_changed'))->info()->send();
                            return;
                        }

                        if (! static::canSetStatus($to)) {
                            Notification::make()->danger()->title('Нет прав на установку этого статуса')->send();
                            return;
                        }

                        $oldRank = $from->rank();
                        $newRank = $to->rank();

                        if ($newRank < $oldRank && ! static::canDowngrade()) {
                            Notification::make()->danger()->title('Нет прав возвращать статус назад')->send();
                            return;
                        }

                        $reason = null;
                        if ($newRank < $oldRank) {
                            $reason = trim((string)($data['downgrade_reason'] ?? ''));
                            if ($reason === '') {
                                Notification::make()->danger()->title('Укажите причину отката')->send();
                                return;
                            }
                            $record->extra_reason = $reason;
                        }

                        $record->status = $to;
                        $record->save();

                        activity('order')
                            ->performedOn($record)
                            ->causedBy($user)
                            ->event($newRank < $oldRank ? 'status_downgraded' : 'status_changed')
                            ->withProperties([
                                'action' => $newRank < $oldRank ? 'status_downgraded' : 'status_changed',
                                'from'   => $from->value,
                                'to'     => $to->value,
                                'reason' => $reason,
                            ])->log($newRank < $oldRank ? __('order.journal.status_rollback') : __('order.journal.status_changed'));

                        Notification::make()
                            ->success()
                            ->title($newRank < $oldRank ? 'Статус откатан' : 'Статус обновлён')
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->groupedBulkActions([
                DeleteBulkAction::make()->action(function () {
                    Notification::make()->title("Now, now, don't be cheeky, leave some records for others to play with!")
                        ->warning()->send();
                }),
            ])
            ->groups([
                Tables\Grouping\Group::make('created_at')->label('Дата заказа')->date()->collapsible()
                    // 👇 Фикс: принудительно сортируем по дате НОВЫЕ → СТАРЫЕ
                    ->orderQueryUsing(fn (Builder $query) =>
                    $query->orderBy('created_at', 'desc')->orderBy('id', 'desc')
                    ),
            ])
            ->bulkActions([
                BulkActionGroup::make([ DeleteBulkAction::make(), ]),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $modelClass = static::$model;
        return (string) $modelClass::where('status', 'new')->count();
    }

    public static function getRelations(): array
    {
        return [
            // ... твои другие relation managers (например, ItemsRelationManager)
            \App\Filament\Resources\Shop\OrderResource\RelationManagers\ClientOrdersRelationManager::class,
        ];
    }

    /**
     * Получает координаты адреса через Google Places API (предпочтительно) или Geocoding API
     *
     * ВАЖНО: Если API ключ имеет ограничения по referer, серверные запросы не будут работать.
     * В этом случае координаты должны быть получены через клиентский JavaScript (поле "Вулиця (Київ)").
     *
     * @param ClientAddress $address
     * @param string|null $placeId Place ID из сохраненного адреса заказа (опционально)
     * @return array|null ['latitude' => float, 'longitude' => float, 'formatted_address' => string]
     */
    protected static function getCoordinatesForAddress(ClientAddress $address, ?string $placeId = null): ?array
    {
        // Если API ключ имеет ограничения по referer, серверные запросы не работают
        // В этом случае координаты должны быть получены через клиентский JavaScript
        // Пропускаем попытку получить координаты через серверный API
        Log::info('Skipping server-side coordinate lookup due to API key referer restrictions. Coordinates should be obtained via client-side JavaScript (Street field).');
        return null;

        /* Закомментировано, так как API ключ с ограничениями по referer не работает для серверных запросов
        try {
            $key = config('services.google_maps.key');
            if (!$key) {
                Log::warning('Google Maps API key not configured for address geocoding');
                return null;
            }

            if ($placeId) {
                // Используем Google Places API для получения координат по place_id
                $token = session('gplaces_token') ?? null;

                try {
                    $response = \Illuminate\Support\Facades\Http::timeout(8)->acceptJson()->get(
                        'https://maps.googleapis.com/maps/api/place/details/json',
                        [
                            'place_id' => $placeId,
                            'fields' => 'geometry,formatted_address',
                            'language' => 'uk',
                            'sessiontoken' => $token,
                            'key' => $key,
                        ]
                    );

                    if ($response->ok()) {
                        $data = $response->json();
                        if ($data['status'] === 'OK' && isset($data['result'])) {
                            $result = $data['result'];
                            $location = $result['geometry']['location'] ?? null;

                            if ($location) {
                                return [
                                    'latitude' => (float)($location['lat'] ?? 0),
                                    'longitude' => (float)($location['lng'] ?? 0),
                                    'formatted_address' => $result['formatted_address'] ?? null,
                                ];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to get coordinates via Places API', [
                        'place_id' => $placeId,
                        'error' => $e->getMessage(),
                    ]);
                    // Продолжаем с Geocoding API как fallback
                }
            }

            // Fallback: используем Google Places Autocomplete API для поиска адреса, затем Places Details API
            // Это более надежный способ, так как Places API обычно включен по умолчанию
            $street = $address->street;
            $house = $address->house;
            $city = $address->city ?: 'Київ';

            // Если в street уже есть город (например, "вул. Курортна, Ворзель Київська область")
            // убираем его и используем только улицу и дом
            if (str_contains($street, ',')) {
                $streetParts = explode(',', $street);
                $street = trim($streetParts[0]); // Берем только первую часть (улицу)
            }

            // Формируем адрес для поиска: улица, дом, город
            $addressParts = array_filter([
                $street,
                $house,
                $city,
            ]);
            $addressString = implode(', ', $addressParts);

            if (empty($addressString)) {
                return null;
            }

            // Используем Google Places Autocomplete API для поиска адреса
            $token = session('gplaces_token') ?? (string) \Illuminate\Support\Str::uuid();
            session(['gplaces_token' => $token]);

            try {
                $autocompleteResponse = \Illuminate\Support\Facades\Http::timeout(8)->acceptJson()->get(
                    'https://maps.googleapis.com/maps/api/place/autocomplete/json',
                    [
                        'input' => $addressString,
                        'types' => 'address',
                        'language' => 'uk',
                        'components' => 'country:ua',
                        'location' => '50.4501,30.5234', // Центр Киева
                        'radius' => 30000,
                        'sessiontoken' => $token,
                        'key' => $key,
                    ]
                );

                if ($autocompleteResponse->ok()) {
                    $autocompleteData = $autocompleteResponse->json();
                    if ($autocompleteData['status'] === 'OK' && !empty($autocompleteData['predictions'])) {
                        // Берем первый результат
                        $prediction = $autocompleteData['predictions'][0];
                        $foundPlaceId = $prediction['place_id'] ?? null;

                        if ($foundPlaceId) {
                            // Получаем детали места через Places Details API
                            $detailsResponse = \Illuminate\Support\Facades\Http::timeout(8)->acceptJson()->get(
                                'https://maps.googleapis.com/maps/api/place/details/json',
                                [
                                    'place_id' => $foundPlaceId,
                                    'fields' => 'geometry,formatted_address',
                                    'language' => 'uk',
                                    'sessiontoken' => $token,
                                    'key' => $key,
                                ]
                            );

                            if ($detailsResponse->ok()) {
                                $detailsData = $detailsResponse->json();
                                if ($detailsData['status'] === 'OK' && isset($detailsData['result'])) {
                                    $result = $detailsData['result'];
                                    $location = $result['geometry']['location'] ?? null;

                                    if ($location) {
                                        return [
                                            'latitude' => (float)($location['lat'] ?? 0),
                                            'longitude' => (float)($location['lng'] ?? 0),
                                            'formatted_address' => $result['formatted_address'] ?? null,
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get coordinates via Places Autocomplete API', [
                    'address' => $addressString,
                    'error' => $e->getMessage(),
                ]);
            }

            // Если Places API не сработал, возвращаем null
            Log::warning('Could not get coordinates for address using Places API', [
                'address' => $addressString,
                'address_id' => $address->id,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Error geocoding address', [
                'address_id' => $address->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
        */
    }

    public static function getWidgets(): array
    {
        return [ OrderStats::class ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit'   => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
    protected static function calcBaseTotalFromGet(Get $get): float
    {
        $items = collect($get('items') ?? [])
            ->map(fn ($i) => is_object($i) ? (array) $i : $i);

        return (float) $items->sum(function ($item) {
            $qty  = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['unit_price'] ?? 0);
            $mods  = collect($item['modifiers'] ?? [])
                ->map(fn ($m) => is_object($m) ? (array) $m : $m);
            $modsSum = (float) $mods->sum(fn ($m) => (float) ($m['price_modifier'] ?? 0));
            return $qty * ($price + $modsSum);
        });
    }
}
