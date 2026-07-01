<?php

// ====== app/Filament/Resources/Shop/OrderResource.php ======

namespace App\Filament\Resources\Shop;

use App\Enums\OrderStatus;
use App\Filament\Clusters\Products\Resources\ProductResource;
use App\Filament\Resources\Shop\OrderResource\Pages;
use App\Filament\Resources\Shop\OrderResource\Widgets\OrderStats;
use App\Forms\Components\AddressForm;
use App\Models\Callcenter\Source as CallcenterSource;
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
use Filament\Tables\Actions\Action;               // <РІР‚вЂќ Р Т‘Р В»РЎРЏ РЎвЂљР В°Р В±Р В»Р С‘РЎвЂ РЎвЂ№ (Р СР С•Р Т‘Р В°Р В»Р С”Р В° Р’В«Р РЋРЎвЂљР В°РЎвЂљРЎС“РЎРѓРЎвЂ№Р’В»)
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
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
use App\Models\Shop\CashalotLog;
use App\Filament\Resources\ClientResource;
use Filament\Support\Enums\VerticalAlignment;
use App\Enums\PaymentMethodEnum;
use App\Services\PrivatBankPaypartsRefundService;
use App\Services\CashalotFiscalService;
use Filament\Tables\Columns\BadgeColumn;
class OrderResource extends Resource
{
    protected static function isCallcenterContext(): bool
    {
        // Works for both initial page load and Livewire requests.
        return (string) static::getSlug() === 'callcenter/orders';
    }

    protected static function pickupMethodOptions(): array
    {
        return [
            'pickup' => __('order.delivery_methods.pickup'),
            'bolt' => 'Bolt',
            'glovo' => 'Glovo',
        ];
    }

    protected static function normalizePickupShippingMethod(?string $method): string
    {
        return array_key_exists((string) $method, static::pickupMethodOptions())
            ? (string) $method
            : 'pickup';
    }

    protected static function deliveryMethodLabel(Order $record): string
    {
        if (! $record->self_pickup) {
            return __('order.delivery_methods.delivery');
        }

        return static::pickupMethodOptions()[$record->shipping_method ?? ''] ?? __('order.delivery_methods.pickup');
    }

    protected static ?string $model = Order::class;
    protected static ?string $slug = 'shop/orders';
    protected static ?string $recordTitleAttribute = 'number';
    protected static ?string $navigationGroup = null;
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
    //   Р СџР ВµРЎР‚Р ВµР С‘РЎРѓР С—Р С•Р В»РЎРЉР В·РЎС“Р ВµР СР В°РЎРЏ РЎвЂћР С•РЎР‚Р СР В° Р СР С•Р Т‘Р В°Р В»Р С”Р С‘ РЎРѓРЎвЂљР В°РЎвЂљРЎС“РЎРѓР С•Р Р†
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
                            return number_format($total, 2, ',', ' ') . ' Р С–РЎР‚Р Р…';
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
            // РІР‚вЂќРІР‚вЂќРІР‚вЂќ Р РЋРЎС“Р СР СРЎвЂ№ Р С‘ РЎРѓР С”Р С‘Р Т‘Р С”Р С‘ РІР‚вЂќРІР‚вЂќРІР‚вЂќ
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

                            return number_format($base, 2, ',', ' ') . ' Р С–РЎР‚Р Р…';
                        })
                        ->reactive(),

                    // 1) Р В¤РЎвЂ“Р С”РЎРѓР С•Р Р†Р В°Р Р…Р В° Р В·Р Р…Р С‘Р В¶Р С”Р В°
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

                    // 2) Р вЂ”Р Р…Р С‘Р В¶Р С”Р С‘ Р В·Р В° РЎвЂЎР В°РЎРѓР С•Р С (happy hours)
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
                            } else {
                                $component->state('none');
                            }
                        })
                        ->options(function (Get $get) {
                            $type = (string) ($get('time_type') ?? 'order');
                            $moment = static::resolveTimeDiscountMomentFromForm($get, $type);

                            $options = TimeDiscount::query()
                                ->activeForMoment($moment, 'Europe/Kyiv')
                                ->pluck('name', 'id')
                                ->toArray();

                            return ['none' => 'Р вЂР ВµР В· Р В°Р С”РЎвЂ РЎвЂ“РЎвЂ”'] + $options;
                        })
                        ->afterStateUpdated(function ($state, Set $set, Get $get, ?Order $record) {
                            if (! $record) return;

                            $stateStr = trim((string) $state);

                            if ($stateStr === '' || $stateStr === 'none') {
                                $record->adjustments()->whereIn('type', ['time', 'fixed'])->delete();
                                app(\App\Services\OrderPricing::class)->recalc($record);
                                static::recalculateShippingFromCurrentForm($get, $set, $record);
                                $set('delivery_price_auto', 'discount_time_none_' . microtime(true));
                                return;
                            }

                            $record->adjustments()->where('type', 'manual_item_override')->delete();

                            $discountType = (string) (TimeDiscount::find($stateStr)?->time_type ?? ($get('time_type') ?? 'order'));
                            $moment = static::resolveTimeDiscountMomentFromForm($get, $discountType);

                            app(\App\Services\OrderPricing::class)->applyTimeExclusive(
                                $record,
                                (int) $stateStr,
                                'single',
                                $moment
                            );

                            if ($state) {
                                $set('ui_fixed_discount_id', null);
                                $set('ui_manual_percent', null);
                            }

                            app(\App\Services\OrderPricing::class)->recalc($record);
                            static::recalculateShippingFromCurrentForm($get, $set, $record);
                            $set('delivery_price_auto', 'discount_time_' . microtime(true));

                            $record->refresh()->loadMissing('adjustments');

                            if ($state && method_exists(static::class, 'buildItemsStateFromOrder')) {
                                $set('items', static::buildItemsStateFromOrder($record));
                            }

                            $timeAdj = $record->adjustments->firstWhere('type', 'time');

                            if ($stateStr !== '' && (! $timeAdj || abs((float) $timeAdj->amount) < 0.0001)) {
                                $set('ui_time_discount_id', 'none');

                                Notification::make()
                                    ->warning()
                                    ->title('Р С’Р С”РЎвЂ РЎвЂ“РЎРЏ Р Р…Р Вµ Р В·Р В°РЎРѓРЎвЂљР С•РЎРѓР С•Р Р†РЎС“РЎвЂќРЎвЂљРЎРЉРЎРѓРЎРЏ Р Т‘Р С• РЎвЂ РЎРЉР С•Р С–Р С• Р В·Р В°Р СР С•Р Р†Р В»Р ВµР Р…Р Р…РЎРЏ')
                                    ->body('Р вЂ™Р С‘Р В±РЎР‚Р В°Р Р…Р В° Р В·Р Р…Р С‘Р В¶Р С”Р В° Р В·Р В° РЎвЂЎР В°РЎРѓР С•Р С Р Р…Р Вµ Р С—РЎвЂ“Р Т‘РЎвЂ¦Р С•Р Т‘Р С‘РЎвЂљРЎРЉ Р Т‘Р В»РЎРЏ РЎвЂљР С•Р Р†Р В°РЎР‚РЎвЂ“Р Р† РЎС“ Р С—Р С•РЎвЂљР С•РЎвЂЎР Р…Р С•Р СРЎС“ Р В·Р В°Р СР С•Р Р†Р В»Р ВµР Р…Р Р…РЎвЂ“.')
                                    ->send();
                            }
                        }),

                    // 3) Р СџРЎР‚Р С•Р СР С•Р С”Р С•Р Т‘
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
                                        $set('delivery_price_auto', 'discount_coupon_apply_' . microtime(true));
                                        Notification::make()->title('Р СџРЎР‚Р С•Р СР С•Р С”Р С•Р Т‘ Р В·Р В°РЎРѓРЎвЂљР С•РЎРѓР С•Р Р†Р В°Р Р…Р С•')->success()->send();
                                    } else {
                                        Notification::make()->title('Р СџРЎР‚Р С•Р СР С•Р С”Р С•Р Т‘ Р Р…Р Вµ Р Т‘РЎвЂ“Р в„–РЎРѓР Р…Р С‘Р в„–')->danger()->send();
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
                                            $set('delivery_price_auto', 'discount_coupon_clear_' . microtime(true));
                                        });

                                        Notification::make()
                                            ->success()
                                            ->title('Р СџРЎР‚Р С•Р СР С•Р С”Р С•Р Т‘ РЎС“Р Т‘Р В°Р В»Р ВµР Р…')
                                            ->send();
                                    } catch (\Exception $e) {
                                        Notification::make()
                                            ->danger()
                                            ->title('Р С›РЎв‚¬Р С‘Р В±Р С”Р В° Р С—РЎР‚Р С‘ РЎС“Р Т‘Р В°Р В»Р ВµР Р…Р С‘Р С‘ Р С—РЎР‚Р С•Р СР С•Р С”Р С•Р Т‘Р В°')
                                            ->body($e->getMessage())
                                            ->send();

                                        Log::error('Р С›РЎв‚¬Р С‘Р В±Р С”Р В° Р С—РЎР‚Р С‘ РЎС“Р Т‘Р В°Р В»Р ВµР Р…Р С‘Р С‘ Р С—РЎР‚Р С•Р СР С•Р С”Р С•Р Т‘Р В° Р С‘Р В· Р В·Р В°Р С”Р В°Р В·Р В°', [
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
                        ->minValue(0)
                        ->maxValue(100)
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

                            $val = min(100, max(0, (float) $state));
                            $set('ui_manual_percent', $val > 0 ? $val : null);

                            if ($val > 0) {
                                $set('ui_fixed_discount_id', null);
                                $set('ui_time_discount_id', null);
                            }

                            app(\App\Services\OrderPricing::class)
                                ->applyManualPercentExclusive($record, $val);

                            app(\App\Services\OrderPricing::class)->recalc($record);
                            $set('delivery_price_auto', 'discount_manual_percent_' . microtime(true));
                        }),

                    TextInput::make('ui_manual_fixed')
                        ->label(__('order.fields.manual_discount_amount'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(fn (Get $get) => max(0, round(static::calcBaseTotalFromGet($get), 2)))
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
                            $baseTotal = max(0, round(static::calcBaseTotalFromGet($get), 2));
                            $amount = min($baseTotal, max(0, (float) $state));
                            $set('ui_manual_fixed', $amount > 0 ? $amount : null);
                            app(\App\Services\OrderPricing::class)->applyManualFixed($record, $amount);
                            app(\App\Services\OrderPricing::class)->recalc($record);
                            $set('delivery_price_auto', 'discount_manual_fixed_' . microtime(true));
                        }),

                    Placeholder::make('ui_adjustments_list')
                        ->label(__('order.fields.applied_discounts'))
                        ->content(function (?Order $order, Get $get) {
                            if (! $order) return new HtmlString('РІР‚вЂќ');

                            $orderId = (int) ($order->getKey() ?: ($get('id') ?? 0));
                            $rows = $orderId > 0
                                ? \Illuminate\Support\Facades\DB::table('bs_shop_order_adjustments')
                                    ->where('shop_order_id', $orderId)
                                    ->whereNotIn('type', ['import_total_correction', 'manual_item_override'])
                                    ->orderByDesc('id')
                                    ->get(['id', 'type', 'label', 'amount'])
                                : collect();

                            $recordDiscountAmount = abs((float) ($order->discount_total ?? 0));
                            $formDiscountAmount = abs((float) ($get('discount_total') ?? 0));
                            $dbDiscountAmount = $orderId > 0
                                ? abs((float) (\Illuminate\Support\Facades\DB::table('bs_shop_orders')->where('id', $orderId)->value('discount_total') ?? 0))
                                : 0.0;
                            $discountAmount = max($recordDiscountAmount, $formDiscountAmount, $dbDiscountAmount);
                            $subtotal = (float) ($order->subtotal ?? 0);

                            $discountFallbackHtml = static function (float $discountAmount, float $subtotal, ?string $currency): ?string {
                                if ($discountAmount <= 0) {
                                    return null;
                                }

                                $percentText = '';
                                if ($subtotal > 0) {
                                    $percent = round(($discountAmount / $subtotal) * 100, 2);
                                    $percentText = ' (' . number_format($percent, 2, ',', ' ') . '%)';
                                }

                                return '<div class="space-y-1">'
                                    . '<div class="flex justify-between text-sm">'
                                    . '<div><span class="font-medium">Р РЋР С”Р С‘Р Т‘Р С”Р В° Р С—Р С• Р В·Р В°Р С”Р В°Р В·РЎС“</span></div>'
                                    . '<div class="text-rose-600">-'
                                    . number_format($discountAmount, 2, ',', ' ')
                                    . ' '
                                    . e($currency ?? 'UAH')
                                    . e($percentText)
                                    . '</div>'
                                    . '</div>'
                                    . '</div>';
                            };

                            if ($rows->isEmpty()) {
                                $fallback = $discountFallbackHtml($discountAmount, $subtotal, $order->currency);
                                if ($fallback !== null) {
                                    return new HtmlString($fallback);
                                }

                                return new HtmlString('<div class="text-sm text-gray-500">Р РЋР С”Р С‘Р Т‘Р С”Р С‘ Р Р…Р Вµ Р С—РЎР‚Р С‘Р СР ВµР Р…Р ВµР Р…РЎвЂ№</div>');
                            }

                            $out = '<div class="space-y-1">';
                            $hasDiscountRow = false;
                            $promoTypes = ['time', 'fixed', 'coupon'];
                            $promoRows = $rows->filter(fn ($adj) => in_array((string) $adj->type, $promoTypes, true))->values();
                            $promoItemsDiscountAmount = abs((float) ($order->items()->sum('discount_total') ?? 0));
                            $usePromoItemsDiscountFallback = $promoRows->isNotEmpty()
                                && $discountAmount > 0
                                && $promoItemsDiscountAmount > 0
                                && $promoRows->every(fn ($adj) => abs((float) $adj->amount) < 0.0001);
                            $promoFallbackApplied = false;

                            foreach ($rows as $adj) {
                                $displayAmount = (float) $adj->amount;

                                if (
                                    ! $promoFallbackApplied
                                    && $usePromoItemsDiscountFallback
                                    && in_array((string) $adj->type, $promoTypes, true)
                                ) {
                                    $displayAmount = -1 * $promoItemsDiscountAmount;
                                    $promoFallbackApplied = true;
                                }

                                $cls = $displayAmount < 0 ? 'text-rose-600' : 'text-emerald-600';
                                if ($displayAmount < 0) {
                                    $hasDiscountRow = true;
                                }

                                $out .= '<div class="flex justify-between text-sm">'
                                    .    '<div><span class="font-medium">'.e($adj->label).'</span> '
                                    .    ($adj->type ? '<span class="text-gray-500">('.e($adj->type).')</span>' : '')
                                    .    '</div>'
                                    .    '<div class="'.$cls.'">'.number_format($displayAmount, 2, ',', ' ')
                                    .    ' '.e($order->currency ?? 'UAH').'</div>'
                                    . '</div>';
                            }

                            if (! $hasDiscountRow) {
                                $fallback = $discountFallbackHtml($discountAmount, $subtotal, $order->currency);
                                if ($fallback !== null) {
                                    $out .= $fallback;
                                }
                            }

                            $out .= '</div>';

                            return new HtmlString($out);
                        })->dehydrated(false)->inlineLabel(false)
                        ->dehydrated(false),
                    Placeholder::make('ui_loyalty_spent')
                        ->label(__('order.fields.bonuses_written_off'))
                        ->content(function (?Order $order) {
                            if (! $order) {
                                return new HtmlString('РІР‚вЂќ');
                            }

                            $spent = static::resolveSpentBonuses($order);

                            if ($spent <= 0) {
                                return new HtmlString(
                                    '<div class="text-sm text-gray-500">Р вЂР С•Р Р…РЎС“РЎРѓР С‘ Р Р…Р Вµ Р Р†Р С‘Р С”Р С•РЎР‚Р С‘РЎРѓРЎвЂљР С•Р Р†РЎС“Р Р†Р В°Р В»Р С‘РЎРѓРЎРЉ</div>'
                                );
                            }

                            $val = number_format($spent, 2, ',', ' ') . ' Р С–РЎР‚Р Р…';

                            return new HtmlString(
                                '<div class="text-lg font-semibold">'.$val.'</div>'
                            );
                        }),

                    Hidden::make('ui_version')->dehydrated(false)->reactive(),
                    Hidden::make('total_price')
                        ->dehydrated(true)
                        ->afterStateHydrated(fn ($component) => $component->state(null)) // Р Р…Р Вµ Р СР ВµРЎв‚¬Р В°Р ВµР С РЎР‚Р ВµР Т‘Р В°Р С”РЎвЂљР С‘РЎР‚Р С•Р Р†Р В°Р Р…Р С‘РЎР‹
                        ->dehydrateStateUsing(fn (Get $get) => round(static::calcBaseTotalFromGet($get), 2)),

                    Hidden::make('discount_total')
                        ->dehydrated(true)
                        ->afterStateHydrated(fn ($component) => $component->state(null))
                        ->dehydrateStateUsing(function (?Order $record) {
                            // Р ВµРЎРѓР В»Р С‘ Р В·Р В°Р С—Р С‘РЎРѓР С‘ Р Р…Р ВµРЎвЂљ Р С‘Р В»Р С‘ РЎРѓР С”Р С‘Р Т‘Р С•Р С” Р Р…Р ВµРЎвЂљ РІР‚вЂќ 0
                            if (! $record) return 0.0;
                            return (float) $record->adjustments()->sum('amount'); // РЎРѓР С”Р С‘Р Т‘Р С”Р С‘ РЎС“ РЎвЂљР ВµР В±РЎРЏ Р С•РЎвЂљРЎР‚Р С‘РЎвЂ Р В°РЎвЂљР ВµР В»РЎРЉР Р…РЎвЂ№Р Вµ
                        }),

                    Hidden::make('grand_total')
                        ->dehydrated(true)
                        ->afterStateHydrated(fn ($component) => $component->state(null))
                        ->dehydrateStateUsing(function (Get $get, ?Order $record) {
                            $baseTotal = static::calcBaseTotalFromGet($get);
                            $deliveryPrice = (float) ($get('shipping_price') ?? 0);
                            $resolved = static::resolveFinalAmount($record, $baseTotal, $deliveryPrice);

                            return round((float) ($resolved['final'] ?? 0), 2);
                        }),
                    // Р РЋР С”РЎР‚РЎвЂ№РЎвЂљР С•Р Вµ Р С—Р С•Р В»Р Вµ Р Т‘Р В»РЎРЏ Р С•РЎвЂљРЎРѓР В»Р ВµР В¶Р С‘Р Р†Р В°Р Р…Р С‘РЎРЏ Р С‘Р В·Р СР ВµР Р…Р ВµР Р…Р С‘Р в„– Р С”Р С•Р С•РЎР‚Р Т‘Р С‘Р Р…Р В°РЎвЂљ (РЎвЂљРЎР‚Р С‘Р С–Р С–Р ВµРЎР‚ Р Т‘Р В»РЎРЏ Р С•Р В±Р Р…Р С•Р Р†Р В»Р ВµР Р…Р С‘РЎРЏ Р Т‘Р С•РЎРѓРЎвЂљР В°Р Р†Р С”Р С‘)
                    Hidden::make('delivery_coords_trigger')
                        ->dehydrated(false)
                        ->default('')
                        ->live()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, Get $get, ?Order $record) {
                            // Р СћРЎР‚Р С‘Р С–Р С–Р ВµРЎР‚Р С‘Р С Р С•Р В±Р Р…Р С•Р Р†Р В»Р ВµР Р…Р С‘Р Вµ delivery_price_auto Р Т‘Р В»РЎРЏ Р С—Р ВµРЎР‚Р ВµРЎРѓРЎвЂЎР ВµРЎвЂљР В° shipping_price
                            \Log::info('OrderResource: delivery_coords_trigger afterStateUpdated', [
                                'trigger' => $state,
                            ]);

                            // Р С›Р В±Р Р…Р С•Р Р†Р В»РЎРЏР ВµР С delivery_price_auto Р Т‘Р В»РЎРЏ Р С—Р ВµРЎР‚Р ВµРЎРѓРЎвЂЎР ВµРЎвЂљР В° shipping_price
                            $set('delivery_price_auto', $state ?: time());

                            // Р СћР В°Р С”Р В¶Р Вµ Р Р…Р В°Р С—РЎР‚РЎРЏР СРЎС“РЎР‹ Р С•Р В±Р Р…Р С•Р Р†Р В»РЎРЏР ВµР С shipping_price, Р ВµРЎРѓР В»Р С‘ Р С”Р С•Р С•РЎР‚Р Т‘Р С‘Р Р…Р В°РЎвЂљРЎвЂ№ Р ВµРЎРѓРЎвЂљРЎРЉ
                            if ($state) {
                                $address = $get('address') ?? [];
                                $latitude = $address['latitude'] ?? null;
                                $longitude = $address['longitude'] ?? null;
                                $selfPickup = $get('self_pickup') ?? false;

                                if (!$selfPickup && $latitude && $longitude) {
                                    $deliveryService = app(\App\Services\DeliveryCalculationService::class);
                                    $orderTotal = static::calcDeliveryBaseFromGet($get, $record);

                                    $tempOrder = $record ? clone $record : new Order();
                                    $tempOrder->address = $address;
                                    $tempOrder->self_pickup = $selfPickup;

                                    $delivery = $deliveryService->calculateDelivery($tempOrder, $orderTotal);
                                    $calculatedPrice = (float) ($delivery['price'] ?? 0);

                                    $currentShippingPrice = (float) ($get('shipping_price') ?? 0);

                                    // Р С›Р В±Р Р…Р С•Р Р†Р В»РЎРЏР ВµР С shipping_price Р ВµРЎРѓР В»Р С‘ РЎвЂљР ВµР С”РЎС“РЎвЂ°Р ВµР Вµ Р В·Р Р…Р В°РЎвЂЎР ВµР Р…Р С‘Р Вµ РЎР‚Р В°Р Р†Р Р…Р С• 0 Р С‘Р В»Р С‘ Р В±Р В»Р С‘Р В·Р С”Р С• Р С” РЎР‚Р В°РЎРѓРЎРѓРЎвЂЎР С‘РЎвЂљР В°Р Р…Р Р…Р С•Р СРЎС“
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

                    // Р РЋРЎС“Р СР СР В° Р Т‘Р С•РЎРѓРЎвЂљР В°Р Р†Р С”Р С‘ (РЎР‚Р ВµР Т‘Р В°Р С”РЎвЂљР С‘РЎР‚РЎС“Р ВµР СР С•Р Вµ Р С—Р С•Р В»Р Вµ)
                    TextInput::make('shipping_price')
                        ->label('Р РЋРЎС“Р СР СР В° Р Т‘Р С•РЎРѓРЎвЂљР В°Р Р†Р С”Р С‘')
                        ->numeric()
                        ->suffix('Р С–РЎР‚Р Р…')
                        ->step(0.01)
                        ->minValue(0)
                        ->default(0)
                        ->reactive()
                        ->live()
                        ->afterStateHydrated(function (TextInput $component, $state, ?Order $record, Get $get) {
                            // Р СџРЎР‚Р С‘ Р В·Р В°Р С–РЎР‚РЎС“Р В·Р С”Р Вµ РЎвЂћР С•РЎР‚Р СРЎвЂ№, Р ВµРЎРѓР В»Р С‘ shipping_price Р С—РЎС“РЎРѓРЎвЂљР С•Р в„–, РЎР‚Р В°РЎРѓРЎРѓРЎвЂЎР С‘РЎвЂљРЎвЂ№Р Р†Р В°Р ВµР С Р В°Р Р†РЎвЂљР С•Р СР В°РЎвЂљР С‘РЎвЂЎР ВµРЎРѓР С”Р С‘
                            if (!$state && $record) {
                                $address = $get('address') ?? [];
                                $latitude = $address['latitude'] ?? null;
                                $longitude = $address['longitude'] ?? null;
                                $selfPickup = $get('self_pickup') ?? false;

                                if (!$selfPickup && $latitude && $longitude) {
                                    $deliveryService = app(\App\Services\DeliveryCalculationService::class);
                                    $orderTotal = static::calcDeliveryBaseFromGet($get, $record);

                                    $tempOrder = clone $record;
                                    $tempOrder->address = $address;
                                    $tempOrder->self_pickup = $selfPickup;

                                    $delivery = $deliveryService->calculateDelivery($tempOrder, $orderTotal);
                                    $component->state($delivery['price'] ?? 0);
                                }
                            }
                        })
                        ->afterStateUpdated(function ($state, callable $set, Get $get, ?Order $record) {
                            // Р СџРЎР‚Р С‘ Р С‘Р В·Р СР ВµР Р…Р ВµР Р…Р С‘Р С‘ РЎРѓРЎС“Р СР СРЎвЂ№ Р Т‘Р С•РЎРѓРЎвЂљР В°Р Р†Р С”Р С‘ Р С—Р ВµРЎР‚Р ВµРЎРѓРЎвЂЎР С‘РЎвЂљРЎвЂ№Р Р†Р В°Р ВµР С Р С‘РЎвЂљР С•Р С–Р С•Р Р†РЎС“РЎР‹ РЎРѓРЎС“Р СР СРЎС“
                            if ($record) {
                                $set('shipping_total', (float)$state);
                            }
                        })
                        ->helperText(function (Get $get, ?Order $record) {
                            $address = $get('address') ?? [];
                            $latitude = $address['latitude'] ?? null;
                            $longitude = $address['longitude'] ?? null;
                            $selfPickup = $get('self_pickup') ?? false;

                            if ($selfPickup) {
                                return 'Р РЋР В°Р СР С•Р Р†РЎвЂ№Р Р†Р С•Р В·';
                            }

                            if (!$latitude || !$longitude) {
                                return 'Р вЂ™РЎвЂ№Р В±Р ВµРЎР‚Р С‘РЎвЂљР Вµ Р В°Р Т‘РЎР‚Р ВµРЎРѓ Р Т‘Р В»РЎРЏ РЎР‚Р В°РЎРѓРЎвЂЎР ВµРЎвЂљР В° Р Т‘Р С•РЎРѓРЎвЂљР В°Р Р†Р С”Р С‘';
                            }

                            $deliveryService = app(\App\Services\DeliveryCalculationService::class);
                            $orderTotal = static::calcDeliveryBaseFromGet($get, $record);

                            $tempOrder = $record ? clone $record : new Order();
                            $tempOrder->address = $address;
                            $tempOrder->self_pickup = $selfPickup;

                            $delivery = $deliveryService->calculateDelivery($tempOrder, $orderTotal);
                            $zone = $delivery['zone'] ?? null;

                            if (! $zone) {
                                return null;
                            }

                            $color = e($zone->color ?? '#64748b');
                            $name = e($zone->name ?? '');
                            $html = '<span style="display:inline-flex;align-items:center;gap:6px;padding:2px 8px;border-radius:999px;background:' . $color . ';color:#fff;">Р вЂ”Р С•Р Р…Р В°: <strong>' . $name . '</strong></span>';

                            if ($delivery['is_free']) {
                                $html .= ' <span style="color:#16a34a;">Р вЂР ВµРЎРѓР С—Р В»Р В°РЎвЂљР Р…Р В°РЎРЏ Р Т‘Р С•РЎРѓРЎвЂљР В°Р Р†Р С”Р В° (Р С•РЎвЂљ ' . number_format((float) $zone->free_delivery_from, 2, ',', ' ') . ' Р С–РЎР‚Р Р…)</span>';
                            }

                            return new \Illuminate\Support\HtmlString($html);
                        })
                        ->visible(fn (Get $get) => !($get('self_pickup') ?? false)),

                    // Р РЋР С”РЎР‚РЎвЂ№РЎвЂљР С•Р Вµ Р С—Р С•Р В»Р Вµ Р Т‘Р В»РЎРЏ Р В°Р Р†РЎвЂљР С•Р СР В°РЎвЂљР С‘РЎвЂЎР ВµРЎРѓР С”Р С•Р С–Р С• Р С•Р В±Р Р…Р С•Р Р†Р В»Р ВµР Р…Р С‘РЎРЏ shipping_price Р С—РЎР‚Р С‘ Р С‘Р В·Р СР ВµР Р…Р ВµР Р…Р С‘Р С‘ Р С”Р С•Р С•РЎР‚Р Т‘Р С‘Р Р…Р В°РЎвЂљ
                    Hidden::make('delivery_price_auto')
                        ->dehydrated(false)
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, Get $get, ?Order $record) {
                            // Р С’Р Р†РЎвЂљР С•Р СР В°РЎвЂљР С‘РЎвЂЎР ВµРЎРѓР С”Р С‘ Р С•Р В±Р Р…Р С•Р Р†Р В»РЎРЏР ВµР С shipping_price Р С—РЎР‚Р С‘ Р С‘Р В·Р СР ВµР Р…Р ВµР Р…Р С‘Р С‘ Р С”Р С•Р С•РЎР‚Р Т‘Р С‘Р Р…Р В°РЎвЂљ
                            \Log::info('OrderResource: delivery_price_auto afterStateUpdated called', [
                                'state' => $state,
                                'has_record' => !is_null($record),
                            ]);

                            if (!$state) {
                                \Log::info('OrderResource: delivery_price_auto skipped - empty state');
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
                                $set('shipping_total', 0);
                                \Log::info('OrderResource: shipping_price set to 0 (self pickup)');
                                return;
                            }

                            if (!$latitude || !$longitude) {
                                \Log::info('OrderResource: delivery_price_auto skipped - missing coordinates');
                                return;
                            }

                            $deliveryService = app(\App\Services\DeliveryCalculationService::class);
                            $orderTotal = static::calcDeliveryBaseFromGet($get, $record);

                            $tempOrder = $record ? clone $record : new Order();
                            $tempOrder->address = $address;
                            $tempOrder->self_pickup = $selfPickup;

                            $delivery = $deliveryService->calculateDelivery($tempOrder, $orderTotal);

                            // Р С›Р В±Р Р…Р С•Р Р†Р В»РЎРЏР ВµР С shipping_price
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

                            $set('shipping_price', $calculatedPrice);
                            \Log::info('OrderResource: shipping_price updated automatically', [
                                'new_value' => $calculatedPrice,
                                'old_value' => $currentShippingPrice,
                                'zone' => $delivery['zone'] ? $delivery['zone']->name : null,
                                'is_free' => $delivery['is_free'] ?? false,
                            ]);
                        }),


                    Placeholder::make('total_after_discount')
                        ->label(__('order.fields.total_with_discount'))
                        ->dehydrated(false)
                        ->reactive()
                        ->content(function (?Order $record, Get $get) {
                            // 1) Р вЂР В°Р В·Р С•Р Р†Р В°РЎРЏ РЎРѓРЎС“Р СР СР В° Р С‘Р В· РЎвЂљР ВµР С”РЎС“РЎвЂ°Р С‘РЎвЂ¦ Р С—Р С•Р В·Р С‘РЎвЂ Р С‘Р в„– РЎвЂћР С•РЎР‚Р СРЎвЂ№
                            $baseTotal = static::calcBaseTotalFromGet($get);

                            // 2) Р вЂўРЎРѓР В»Р С‘ Р В·Р В°Р С”Р В°Р В·Р В° Р ВµРЎвЂ°РЎвЂ Р Р…Р ВµРЎвЂљ РІР‚вЂќ Р С—РЎР‚Р С•РЎРѓРЎвЂљР С• Р С—Р С•Р С”Р В°Р В·РЎвЂ№Р Р†Р В°Р ВµР С Р В±Р В°Р В·РЎС“
                            if (! $record) {
                                $val = number_format($baseTotal, 2, ',', ' ') . ' Р С–РЎР‚Р Р…';
                                return new \Illuminate\Support\HtmlString('<div class="text-lg font-semibold">'.$val.'</div>');
                            }

                            $deliveryPrice = (float) ($get('shipping_price') ?? 0);
                            $resolved = static::resolveFinalAmount($record, $baseTotal, $deliveryPrice);
                            $finalAmount = (float) ($resolved['final'] ?? 0);
                            $bonusesSpent = (float) ($resolved['bonuses'] ?? 0);
                            $amount = max(0, $finalAmount - $deliveryPrice);

                            // 4) Р СџР С•Р С”Р В°Р В·РЎвЂ№Р Р†Р В°Р ВµР С breakdown
                            $address = $get('address') ?? [];
                            $selfPickup = $get('self_pickup') ?? false;

                            $html = '<div class="space-y-1">';
                            $html .= '<div class="text-lg font-semibold">' . number_format($finalAmount, 2, ',', ' ') . ' Р С–РЎР‚Р Р…</div>';

                            if ($deliveryPrice > 0) {
                                $html .= '<div class="text-xs text-gray-500 flex items-center gap-2">';
                                $html .= '<span>Р СћР С•Р Р†Р В°РЎР‚РЎвЂ№:</span>';
                                $html .= '<span>' . number_format($amount, 2, ',', ' ') . ' Р С–РЎР‚Р Р…</span>';
                                $html .= '<span class="mx-1">+</span>';
                                $html .= '<span>Р вЂќР С•РЎРѓРЎвЂљР В°Р Р†Р С”Р В°:</span>';
                                $html .= '<span>' . number_format($deliveryPrice, 2, ',', ' ') . ' Р С–РЎР‚Р Р…</span>';
                                $html .= '</div>';
                                if ($bonusesSpent > 0) {
                                    $html .= '<div class="text-xs text-gray-500">Р РЋР С—Р С‘РЎРѓР В°Р Р…Р Р…РЎвЂ№Р Вµ Р В±Р С•Р Р…РЎС“РЎРѓРЎвЂ№: -'
                                        . number_format($bonusesSpent, 2, ',', ' ')
                                        . ' Р С–РЎР‚Р Р…</div>';
                                }
                            } elseif (!$selfPickup) {
                                // Р СџРЎР‚Р С•Р Р†Р ВµРЎР‚РЎРЏР ВµР С, Р ВµРЎРѓРЎвЂљРЎРЉ Р В»Р С‘ Р В±Р ВµРЎРѓР С—Р В»Р В°РЎвЂљР Р…Р В°РЎРЏ Р Т‘Р С•РЎРѓРЎвЂљР В°Р Р†Р С”Р В°
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
                                        $html .= '<div class="text-xs text-green-600">Р вЂќР С•РЎРѓРЎвЂљР В°Р Р†Р С”Р В° Р В±Р ВµРЎРѓР С—Р В»Р В°РЎвЂљР Р…Р В° (Р С•РЎвЂљ ' . number_format($delivery['zone']->free_delivery_from, 2, ',', ' ') . ' Р С–РЎР‚Р Р…)</div>';
                                    }
                                }
                            }

                            $html .= '</div>';

                            return new \Illuminate\Support\HtmlString($html);
                        })
                       /* ->content(function (?Order $record) {
                            if (! $record) return new HtmlString('РІР‚вЂќ');
                            $record->refresh();
                            $val = number_format((float)$record->grand_total, 2, ',', ' ') . ' Р С–РЎР‚Р Р…';
                            return new HtmlString('<div class="text-lg font-semibold">'.$val.'</div>');
                        }),*/
                ]),

            // РІР‚вЂќРІР‚вЂќРІР‚вЂќ Р РЋРЎвЂљР В°РЎвЂљРЎС“РЎРѓРЎвЂ№ (Р С‘Р Р…Р В»Р В°Р в„–Р Р… Р В±Р В»Р С•Р С” Р С•РЎРѓРЎвЂљР В°РЎвЂРЎвЂљРЎРѓРЎРЏ Р Р…Р В° РЎвЂћР С•РЎР‚Р СР Вµ РЎР‚Р ВµР Т‘Р В°Р С”РЎвЂљР С‘РЎР‚Р С•Р Р†Р В°Р Р…Р С‘РЎРЏ) РІР‚вЂќРІР‚вЂќРІР‚вЂќ
            Section::make(__('order.sections.statuses'))
                ->reactive()
                ->schema([
                    Hidden::make('status')->default(fn (?Order $r) => $r?->status?->value ?? OrderStatus::New->value)->dehydrated(true),
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
                                Notification::make()->danger()->title('Р СњР ВµРЎвЂљ Р С—РЎР‚Р В°Р Р† Р Р…Р В° РЎС“РЎРѓРЎвЂљР В°Р Р…Р С•Р Р†Р С”РЎС“ РЎРЊРЎвЂљР С•Р С–Р С• РЎРѓРЎвЂљР В°РЎвЂљРЎС“РЎРѓР В°')->send();
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
                                Notification::make()->danger()->title('Р СњР ВµРЎвЂљ Р С—РЎР‚Р В°Р Р† Р Р†Р С•Р В·Р Р†РЎР‚Р В°РЎвЂ°Р В°РЎвЂљРЎРЉ РЎРѓРЎвЂљР В°РЎвЂљРЎС“РЎРѓ Р Р…Р В°Р В·Р В°Р Т‘')->send();
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
                                        Notification::make()->danger()->title('Р СњР ВµРЎвЂљ Р С—РЎР‚Р В°Р Р†')->send();
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

                                    Notification::make()->success()->title('Р РЋРЎвЂљР В°РЎвЂљРЎС“РЎРѓ Р С•РЎвЂљР С”Р В°РЎвЂљР В°Р Р…')->send();
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
                        ->content(fn (?Order $record): ?string => $record?->created_at?->diffForHumans()),
                    Placeholder::make('updated_at')
                        ->label(__('order.fields.updated_at'))
                        ->content(fn (?Order $record): ?string => $record?->updated_at?->diffForHumans()),
                ]),
        ];
    }

    protected static function resolveTimeDiscountMomentFromForm(Get $get, string $timeType): Carbon
    {
        // Р вЂ™ callcenter Р С•РЎР‚Р С‘Р ВµР Р…РЎвЂљР С‘РЎР‚РЎС“Р ВµР СРЎРѓРЎРЏ Р Р…Р В° Р Р†РЎвЂ№Р В±РЎР‚Р В°Р Р…Р Р…РЎвЂ№Р Вµ Р Т‘Р В°РЎвЂљРЎС“/Р Р†РЎР‚Р ВµР СРЎРЏ Р Т‘Р С•РЎРѓРЎвЂљР В°Р Р†Р С”Р С‘,
        // Р С”Р В°Р С” Р Р…Р В° checkout (fixed delivery date).
        if (! (bool) ($get('as_soon_possible') ?? false)) {
            $deliveryMoment = static::composeMomentFromStates(
                $get('date_order'),
                $get('time_order')
            );

            if ($deliveryMoment) {
                return $deliveryMoment;
            }
        }

        // Р В¤Р С•Р В»Р В±Р ВµР С”: Р СР С•Р СР ВµР Р…РЎвЂљ РЎРѓР С•Р В·Р Т‘Р В°Р Р…Р С‘РЎРЏ Р В·Р В°Р С”Р В°Р В·Р В°.
        $createdMoment = static::composeMomentFromStates(
            $get('dat'),
            $get('time_start')
        );

        return $createdMoment ?? now(config('app.timezone', 'Europe/Kyiv'));
    }

    protected static function composeMomentFromStates(mixed $dateState, mixed $timeState): ?Carbon
    {
        $tz = config('app.timezone', 'Europe/Kyiv');
        $date = null;

        if ($dateState instanceof \DateTimeInterface) {
            $date = Carbon::instance($dateState)->setTimezone($tz);
        } elseif (is_string($dateState) && trim($dateState) !== '') {
            try {
                $date = Carbon::parse($dateState, $tz);
            } catch (\Throwable) {
                $date = null;
            }
        }

        if (! $date) {
            return null;
        }

        $timeString = null;
        if ($timeState instanceof \DateTimeInterface) {
            $timeString = Carbon::instance($timeState)->setTimezone($tz)->format('H:i:s');
        } elseif (is_string($timeState) && trim($timeState) !== '') {
            $raw = trim($timeState);

            if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $raw)) {
                $timeString = strlen($raw) === 5 ? $raw . ':00' : $raw;
            } else {
                try {
                    $timeString = Carbon::parse($raw, $tz)->format('H:i:s');
                } catch (\Throwable) {
                    $timeString = null;
                }
            }
        }

        if ($timeString) {
            $date->setTimeFromTimeString($timeString);
        }

        return $date;
    }



    public static function getInfoTabSchema(): array
    {
        return [
            Grid::make(12)->schema([

                // 1) Р СњР С•Р СР ВµРЎР‚ Р В·Р В°Р С”Р В°Р В·Р В° РІР‚вЂќ Р С”Р С•Р СР С—Р В°Р С”РЎвЂљР Р…Р С•Р Вµ Р С—Р С•Р В»Р Вµ
                TextInput::make('number')
                    ->label(__('order.fields.order_number'))
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder(fn (?Order $r) => $r?->exists ? $r->number : __('order.placeholders.number_auto'))
                    ->columnSpan(3),

                // 2) Р С™Р В»Р С‘Р ВµР Р…РЎвЂљ РІР‚вЂќ РЎРѓ create/edit Р Р† Р СР С•Р Т‘Р В°Р В»Р С”Р В°РЎвЂ¦ Р С‘Р В· ClientResource
                Select::make('clients_id')
                    ->relationship('clients', 'name')
                    ->searchable()
                    ->label(__('order.fields.client'))
                    ->required()
                    ->live()
                    // === Р вЂєР вЂўР в„ўР вЂР вЂє Р СџР Р€Р СњР С™Р СћР С’ (Р Р†РЎвЂ№Р В±РЎР‚Р В°Р Р…Р Р…Р С•Р Вµ Р В·Р Р…Р В°РЎвЂЎР ВµР Р…Р С‘Р Вµ) ===
                    // Р вЂўРЎРѓР В»Р С‘ Р Р†РЎвЂ№Р В±РЎР‚Р В°Р Р… Р С”Р В»Р С‘Р ВµР Р…РЎвЂљ РІР‚вЂќ Р С—Р С•Р С”Р В°Р В·РЎвЂ№Р Р†Р В°Р ВµР С "Р ВР СРЎРЏ Р’В· +38 (...)"
                    ->getOptionLabelUsing(function ($value) {
                        if (!$value) return null;
                        $c = Client::query()->select('id','name','phone')->find($value);
                        return $c ? ($c->name . ' Р’В· ' . $c->phone_pretty) : null;
                    })
                    // Р СњР В° Р Р†РЎРѓРЎРЏР С”Р С‘Р в„– РЎРѓР В»РЎС“РЎвЂЎР В°Р в„– (Р С”Р С•Р С–Р Т‘Р В° РЎР‚Р С‘РЎРѓРЎС“Р ВµРЎвЂљРЎРѓРЎРЏ Р С‘Р В· relationship)
                    ->getOptionLabelFromRecordUsing(fn (Client $c) => $c->name . ' Р’В· ' . $c->phone_pretty)
                    // === Р СџР С›Р ВР РЋР С™ ===
                    ->getSearchResultsUsing(function (string $search) {
                        $digits = preg_replace('/\D+/', '', $search); // РЎвЂљР С•Р В»РЎРЉР С”Р С• РЎвЂ Р С‘РЎвЂћРЎР‚РЎвЂ№ Р С‘Р В· Р В·Р В°Р С—РЎР‚Р С•РЎРѓР В°
                        return Client::query()
                            ->select('id','name','phone')
                            ->when($search !== '', fn ($q) =>
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                            )
                            ->when($digits !== '', fn ($q) =>
                                // MySQL 8+: РЎС“Р В±Р С‘РЎР‚Р В°Р ВµР С Р Р†РЎРѓРЎвЂ, РЎвЂЎРЎвЂљР С• Р Р…Р Вµ РЎвЂ Р С‘РЎвЂћРЎР‚РЎвЂ№, Р С‘ Р С‘РЎвЂ°Р ВµР С Р С—Р С•Р Т‘РЎРѓРЎвЂљРЎР‚Р С•Р С”РЎС“
                            $q->orWhereRaw("REGEXP_REPLACE(phone, '[^0-9]', '') LIKE ?", ["%{$digits}%"])
                            )
                            ->orderBy('name')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (Client $c) => [
                                $c->id => $c->name . ' Р’В· ' . $c->phone_pretty,
                            ]);
                    })
                    ->optionsLimit(50)
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        // $state РІР‚вЂќ РЎРЊРЎвЂљР С• Р Р†РЎвЂ№Р В±РЎР‚Р В°Р Р…Р Р…РЎвЂ№Р в„– clients_id
                        $phone = $state
                            ? Client::query()->whereKey($state)->value('phone')
                            : null;

                        $set('client_phone_view', $phone ?: (string) ($get('incoming_phone') ?? ''));
                    })
                    ->afterStateHydrated(function (Get $get, Set $set) {
                        // Р СџРЎР‚Р С‘ Р С•РЎвЂљР С”РЎР‚РЎвЂ№РЎвЂљР С‘Р С‘ РЎвЂћР С•РЎР‚Р СРЎвЂ№ (РЎР‚Р ВµР Т‘Р В°Р С”РЎвЂљР С‘РЎР‚Р С•Р Р†Р В°Р Р…Р С‘Р Вµ/РЎРѓР С•Р В·Р Т‘Р В°Р Р…Р С‘Р Вµ) Р С—Р С•Р Т‘РЎРѓРЎвЂљР В°Р Р†Р С‘Р С РЎвЂљР ВµР В»Р ВµРЎвЂћР С•Р Р…,
                        // Р ВµРЎРѓР В»Р С‘ Р С”Р В»Р С‘Р ВµР Р…РЎвЂљ РЎС“Р В¶Р Вµ Р Р†РЎвЂ№Р В±РЎР‚Р В°Р Р…:
                        $id = $get('clients_id');
                        $fallback = (string) ($get('incoming_phone') ?? '');
                        $set('client_phone_view', $id
                            ? Client::query()->whereKey($id)->value('phone')
                            : $fallback
                        );
                    })
                    ->createOptionForm(fn (Form $form) => ClientResource::form($form))
                    ->createOptionUsing(function (array $data) { $client = Client::create($data); return $client->getKey(); })
                    ->createOptionAction(function (FormAction $action) { return $action
                        ->modalHeading('Р РЋР С•Р В·Р Т‘Р В°Р Р…Р С‘Р Вµ Р С”Р В»Р С‘Р ВµР Р…РЎвЂљР В°')
                        ->modalSubmitActionLabel('Р РЋР С•Р В·Р Т‘Р В°РЎвЂљРЎРЉ Р С”Р В»Р С‘Р ВµР Р…РЎвЂљР В°')
                        ->modalWidth('4xl');
               })

                    ->editOptionForm(fn (Form $form) => ClientResource::form($form))
                    ->editOptionAction(fn (FormAction $action) => $action
                        ->modalHeading('Р В Р ВµР Т‘Р В°Р С”РЎвЂљР С‘РЎР‚Р С•Р Р†Р В°Р Р…Р С‘Р Вµ Р С”Р В»Р С‘Р ВµР Р…РЎвЂљР В°')
                        ->modalSubmitActionLabel('Р РЋР С•РЎвЂ¦РЎР‚Р В°Р Р…Р С‘РЎвЂљРЎРЉ')
                        ->modalWidth('4xl') )
                    ->columnSpan(6),

                // 3) Р СћР ВµР В»Р ВµРЎвЂћР С•Р Р… Р С”Р В»Р С‘Р ВµР Р…РЎвЂљР В° РІР‚вЂќ read-only + Р С”Р Р…Р С•Р С—Р С”Р В° "Р С”Р С•Р С—Р С‘РЎР‚Р С•Р Р†Р В°РЎвЂљРЎРЉ"
                TextInput::make('client_phone_view')
                    ->label(__('order.fields.phone'))
                    ->readOnly()            // Р Р…Р ВµР В»РЎРЉР В·РЎРЏ РЎР‚Р ВµР Т‘Р В°Р С”РЎвЂљР С‘РЎР‚Р С•Р Р†Р В°РЎвЂљРЎРЉ
                    ->dehydrated(false)     // Р Р…Р Вµ РЎРѓР С•РЎвЂ¦РЎР‚Р В°Р Р…РЎРЏР ВµР С Р Р† Р СР С•Р Т‘Р ВµР В»РЎРЉ Р В·Р В°Р С”Р В°Р В·Р В°
                    ->reactive()            // РЎвЂЎРЎвЂљР С•Р В±РЎвЂ№ Р С—Р ВµРЎР‚Р ВµРЎР‚Р С‘РЎРѓР С•Р Р†РЎвЂ№Р Р†Р В°Р В»Р С•РЎРѓРЎРЉ
                    ->extraAttributes(['x-data' => '{}'])// Р С•Р В±РЎвЂ°Р С‘Р в„– Alpine-Р С”Р С•Р Р…РЎвЂљР ВµР С”РЎРѓРЎвЂљ
                    ->extraInputAttributes([
                        'x-ref'   => 'cpInput',          // РЎРѓРЎРѓРЎвЂ№Р В»Р С”Р В° Р Р…Р В° input
                        'readonly'=> true,               // Р вЂ™Р С’Р вЂ“Р СњР С›: РЎвЂљР С•Р В»РЎРЉР С”Р С• Р Т‘Р В»РЎРЏ РЎвЂЎРЎвЂљР ВµР Р…Р С‘РЎРЏ, Р Р…Р Вµ disabled
                        'tabindex'=> 0,                  // Р СР С•Р В¶Р Р…Р С• РЎвЂћР С•Р С”РЎС“РЎРѓР Р…РЎС“РЎвЂљРЎРЉ
                    ])
                   // ->dependsOn('clients_id')
                    ->afterStateHydrated(function (TextInput $component, Get $get) {
                        $id = $get('clients_id');
                        $component->state($id ? (Client::find($id)->phone ?? '') : ((string) ($get('incoming_phone') ?? '')));
                    })
                    ->formatStateUsing(function (Get $get) {
                        $id = $get('clients_id');
                        return $id ? (Client::find($id)->phone ?? '') : ((string) ($get('incoming_phone') ?? ''));
                    })



                    ->columnSpan(3),

                Hidden::make('incoming_phone')
                    ->dehydrated(false),
            ]),

               /* ->createOptionAction(function (FormAction $action) {
                    return $action->modalHeading('Р РЋР С•Р В·Р Т‘Р В°Р Р…Р С‘Р Вµ Р С”Р В»Р С‘Р ВµР Р…РЎвЂљР В°')->modalSubmitActionLabel('Р РЋР С•Р В·Р Т‘Р В°РЎвЂљРЎРЉ Р С”Р В»Р С‘Р ВµР Р…РЎвЂљР В°')->modalWidth('4xl');
                })*/

            Hidden::make('client_address_id')->dehydrated(true),
            Hidden::make('time_order_manually_changed')->default(false)->dehydrated(false),
            Hidden::make('time_order_internal_update')->default(false)->dehydrated(false),
            Hidden::make('date_order_manually_changed')->default(false)->dehydrated(false),
            Hidden::make('date_order_internal_update')->default(false)->dehydrated(false),

            Section::make(__('order.sections.time_payment'))
                ->schema([
                    Grid::make(12)->schema([
                        DatePicker::make('dat')
                            ->label(__('order.fields.created_date'))
                            ->default(fn (?Order $record) => $record?->exists ? null : now())
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get, ?Order $record) {
                                if (! (bool) ($get('date_order_manually_changed') ?? false)) {
                                    $set('date_order_internal_update', true);
                                    $set('date_order', $state);
                                }
                            })
                            ->columnSpan(3),

                        Group::make()
                            ->schema([
                                TimePicker::make('time_start')
                                    ->label(__('order.fields.created_time'))
                                    ->seconds(false)
                                    ->default(fn (?Order $record) => $record?->exists ? null : Carbon::now()->format('H:i'))
                                    ->live()
                                    ->reactive(),

                                View::make('filament.components.time-minute-buttons')
                                    ->viewData([
                                        'statePath' => 'data.time_start',
                                    ]),
                            ])
                            ->columnSpan(3),

                        Group::make()
                            ->schema([
                                TimePicker::make('time_order')
                                    ->label(__('order.fields.order_time'))
                                    ->seconds(false)
                                    ->default(fn () => Carbon::now(config('app.timezone'))->addMinutes(60)->format('H:i'))
                                    ->afterStateHydrated(function ($component, $state, Set $set, ?Order $record) {
                                        if ($record?->exists && filled($state)) {
                                            $set('time_order_manually_changed', true);

                                            return;
                                        }

                                        if (blank($state)) {
                                            $set('time_order_internal_update', true);
                                            $set('time_order_manually_changed', false);
                                            $component->state(
                                                Carbon::now(config('app.timezone'))->addMinutes(60)->format('H:i')
                                            );
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ((bool) ($get('time_order_internal_update') ?? false)) {
                                            $set('time_order_internal_update', false);

                                            return;
                                        }

                                        if (filled($state)) {
                                            $set('time_order_manually_changed', true);
                                        }
                                    })
                                    ->live(),

                                View::make('filament.components.time-minute-buttons')
                                    ->viewData([
                                        'statePath' => 'data.time_order',
                                    ]),
                            ])
                            ->columnSpan(3),

                        DatePicker::make('date_order')
                            ->label(__('order.fields.order_date'))
                            ->default(fn (?Order $record, Get $get) => $record?->date_order ?? $record?->dat ?? $get('dat') ?? now()->toDateString())
                            ->afterStateHydrated(function ($component, $state, Set $set, ?Order $record) {
                                if ($record?->exists) {
                                    $resolvedState = $state;

                                    if (blank($resolvedState)) {
                                        $resolvedState = $record->date_order ?? $record->dat;

                                        if (filled($resolvedState)) {
                                            $set('date_order_internal_update', true);
                                            $component->state(
                                                $resolvedState instanceof \DateTimeInterface
                                                    ? Carbon::instance($resolvedState)->toDateString()
                                                    : Carbon::parse((string) $resolvedState)->toDateString()
                                            );
                                        }
                                    }

                                    if (blank($resolvedState)) {
                                        $set('date_order_manually_changed', false);

                                        return;
                                    }

                                    $orderDate = $resolvedState instanceof \DateTimeInterface
                                        ? Carbon::instance($resolvedState)->toDateString()
                                        : Carbon::parse((string) $resolvedState)->toDateString();
                                    $createdDate = filled($record->dat)
                                        ? Carbon::parse((string) $record->dat)->toDateString()
                                        : null;

                                    $set('date_order_manually_changed', $createdDate !== null && $orderDate !== $createdDate);

                                    return;
                                }

                                if (blank($state)) {
                                    $set('date_order_internal_update', true);
                                    $set('date_order_manually_changed', false);
                                    $component->state(now()->toDateString());
                                }
                            })
                            ->afterStateUpdated(function ($state, Set $set, Get $get, ?Order $record) {
                                if ((bool) ($get('date_order_internal_update') ?? false)) {
                                    $set('date_order_internal_update', false);

                                    return;
                                }

                                if (filled($state)) {
                                    $set('date_order_manually_changed', true);
                                }
                            })
                            ->live()
                            ->columnSpan(3),
                    ]),

                    Grid::make(12)->schema([
                        Toggle::make('as_soon_possible')
                            ->label(__('order.fields.asap'))
                            ->inline(false)
                            ->live()
                            ->columnSpan(fn () => static::isCallcenterContext() ? 2 : 3),

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
                            ->afterStateUpdated(function ($state, Set $set, Get $get, ?Order $record) {
                                $add = $state ? 15 : 60;

                                if ($state) {
                                    $set('shipping_method', static::normalizePickupShippingMethod((string) ($get('shipping_method') ?? null)));
                                    $set('shipping_price', 0);
                                    $set('shipping_total', 0);
                                    $set('delivery_price_auto', 'pickup_' . microtime(true));
                                } else {
                                    $set('shipping_method', 'delivery');
                                    $address = $get('address') ?? [];
                                    $latitude = $address['latitude'] ?? null;
                                    $longitude = $address['longitude'] ?? null;

                                    if ($latitude && $longitude) {
                                        $deliveryService = app(\App\Services\DeliveryCalculationService::class);
                                        $orderTotal = static::calcDeliveryBaseFromGet($get, $record);
                                        $tempOrder = new Order();
                                        $tempOrder->address = $address;
                                        $tempOrder->self_pickup = false;

                                        $delivery = $deliveryService->calculateDelivery($tempOrder, $orderTotal);
                                        $calculatedPrice = (float) ($delivery['price'] ?? 0);

                                        $set('shipping_price', $calculatedPrice);
                                        $set('shipping_total', $calculatedPrice);
                                        $set('delivery_price_auto', 'delivery_' . microtime(true));
                                    }
                                }

                                // Р вЂР ВµРЎР‚РЎвЂР С РЎвЂљР ВµР С”РЎС“РЎвЂ°Р ВµР Вµ Р Р†РЎР‚Р ВµР СРЎРЏ Р Р† Р С™Р С‘Р ВµР Р†Р Вµ
                                $dt = Carbon::now(config('app.timezone'))->addMinutes($add);

                                if (! (bool) ($get('time_order_manually_changed') ?? false)) {
                                    $set('time_order_internal_update', true);
                                    $set('time_order', $dt->format('H:i'));
                                }

                                if (! (bool) ($get('date_order_manually_changed') ?? false)) {
                                    $set('date_order_internal_update', true);
                                    $set('date_order', $dt->toDateString());
                                }
                            })

                            ->columnSpan(fn () => static::isCallcenterContext() ? 2 : 3),

                     /*    Select::make('payment')
                            ->label('Р РЋР С—Р С•РЎРѓР С•Р В± Р С•Р С—Р В»Р В°РЎвЂљРЎвЂ№')
                            ->options([
                                1=> 'Р С™РЎР‚Р ВµР Т‘Р С‘РЎвЂљР Р…Р В°РЎРЏ Р С”Р В°РЎР‚РЎвЂљР В°',
                                2 => 'Р СњР В°Р В»Р С‘РЎвЂЎР С”Р С•Р в„–',
                                3 => 'Р С™Р В»РЎС“Р В±Р Р…Р В°РЎРЏ Р С”Р В°РЎР‚РЎвЂљР В° (Р С”РЎР‚Р ВµР Т‘Р С‘РЎвЂљ/Р Т‘Р ВµР С—Р С•Р В·Р С‘РЎвЂљ)',
                                4 => 'Р вЂР ВµР В·Р Р…Р В°Р В»Р С‘РЎвЂЎР Р…Р В°РЎРЏ РЎвЂЎР ВµРЎР‚Р ВµР В· Р С•РЎР‚Р С–Р В°Р Р…Р С‘Р В·Р В°РЎвЂ Р С‘РЎР‹',
                                5 => 'Р вЂР ВµР В· Р С•Р С—Р В»Р В°РЎвЂљРЎвЂ№',
                                9 => 'Р С›Р С—Р В»Р В°РЎвЂљР В° РЎвЂЎР ВµРЎР‚Р ВµР В· POS-РЎвЂљР ВµРЎР‚Р СРЎвЂ“Р Р…Р В°Р В»',
                                10 => 'Р В Р В°РЎвЂ¦РЎС“Р Р…Р С•Р С”-РЎвЂћР В°Р С”РЎвЂљРЎС“РЎР‚Р В°',
                                11 => 'LiqPay',
                            ])*/
                            Select::make('payment')
                                ->label(__('order.fields.payment_method'))
                                ->options(static::paymentOptionsAdmin())
                                ->required()
                                ->native(false)
                                ->searchable()
                            ->default(1)
                            ->live()
                            ->reactive()
                            ->columnSpan(fn () => static::isCallcenterContext() ? 5 : 4),

                        Select::make('currency')
                                ->searchable()
                                ->label(__('order.fields.currency'))
                                ->options(Currency::pluck('name', 'code'))
                                ->default('UAH')
                                ->required()
                                ->hidden(fn () => static::isCallcenterContext())
                                ->columnSpan(2),

                        Group::make()
                            ->visible(fn () => static::isCallcenterContext())
                            ->schema([
                                TimePicker::make('time_issue')
                                    ->label('Р В§Р В°РЎРѓ Р Р†Р С‘Р Т‘Р В°РЎвЂЎРЎвЂ“')
                                    ->seconds(false)
                                    ->afterStateHydrated(function ($component, $state, Get $get) {
                                        if (blank($state)) {
                                            $component->state($get('time_order'));
                                        }
                                    })
                                    ->default(fn (Get $get) => $get('time_order'))
                                    ->live(),

                                View::make('filament.components.time-minute-buttons')
                                    ->viewData([
                                        'statePath' => 'data.time_issue',
                                    ]),
                            ])
                            ->columnSpan(3),

                        Select::make('shipping_method')
                            ->label('Р вЂ™Р С‘Р Т‘ РЎРѓР В°Р СР С•Р Р†Р С‘Р Р†Р С•Р В·РЎС“')
                            ->options(static::pickupMethodOptions())
                            ->default('pickup')
                            ->native(false)
                            ->live()
                            ->reactive()
                            ->afterStateHydrated(function ($component, $state, Get $get) {
                                if (! static::isCallcenterContext()) {
                                    return;
                                }

                                if (! (bool) ($get('self_pickup') ?? false)) {
                                    if ($state !== 'delivery') {
                                        $component->state('delivery');
                                    }

                                    return;
                                }

                                $normalized = static::normalizePickupShippingMethod(is_string($state) ? $state : null);

                                if ($state !== $normalized) {
                                    $component->state($normalized);
                                }
                            })
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (! static::isCallcenterContext()) {
                                    return;
                                }

                                if (! (bool) ($get('self_pickup') ?? false)) {
                                    $set('shipping_method', 'delivery');
                                } elseif (! array_key_exists((string) $state, static::pickupMethodOptions())) {
                                    $set('shipping_method', 'pickup');
                                }
                            })
                            ->visible(fn (Get $get) => static::isCallcenterContext() && (bool) ($get('self_pickup') ?? false))
                            ->columnSpan(3),

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
                                'latitude','longitude','street_place_id','formatted_address',
                            ]);

                            // Р СџР С•Р В»РЎС“РЎвЂЎР В°Р ВµР С Р С”Р С•Р С•РЎР‚Р Т‘Р С‘Р Р…Р В°РЎвЂљРЎвЂ№ Р С‘Р В· РЎРѓР С•РЎвЂ¦РЎР‚Р В°Р Р…Р ВµР Р…Р Р…Р С•Р С–Р С• Р В°Р Т‘РЎР‚Р ВµРЎРѓР В° Р В·Р В°Р С”Р В°Р В·Р В°, Р ВµРЎРѓР В»Р С‘ Р С•Р Р…Р С‘ Р ВµРЎРѓРЎвЂљРЎРЉ
                            $orderAddress = $record->address ?? [];
                            if (isset($orderAddress['latitude']) && isset($orderAddress['longitude'])) {
                                $addressData['latitude'] = $orderAddress['latitude'];
                                $addressData['longitude'] = $orderAddress['longitude'];
                                $addressData['formatted_address'] = $orderAddress['formatted_address'] ?? null;
                            } else {
                                // Р вЂўРЎРѓР В»Р С‘ Р С”Р С•Р С•РЎР‚Р Т‘Р С‘Р Р…Р В°РЎвЂљ Р Р…Р ВµРЎвЂљ, Р С—Р С•Р В»РЎС“РЎвЂЎР В°Р ВµР С Р С‘РЎвЂ¦ РЎвЂЎР ВµРЎР‚Р ВµР В· API
                                $coordinates = static::getCoordinatesForAddress($address);
                                if ($coordinates) {
                                    $addressData['latitude'] = $coordinates['latitude'];
                                    $addressData['longitude'] = $coordinates['longitude'];
                                    $addressData['formatted_address'] = $coordinates['formatted_address'] ?? null;
                                }
                            }

                            $set('address', $addressData);
                            static::persistAddressCoordinatesIfMissing($address, $addressData);

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
                            'city'=> 'Р С™Р С‘РЎвЂ”Р Р†',
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

                    // fallback: Р ВµРЎРѓР В»Р С‘ Р Р† Р В°Р Т‘РЎР‚Р ВµРЎРѓР Вµ Р Р…Р ВµРЎвЂљ coords, Р Р…Р С• Р Р† Р В·Р В°Р С”Р В°Р В·Р Вµ Р В±РЎвЂ№Р В»Р С‘ РІР‚вЂќ Р СР С•Р В¶Р Р…Р С• Р С‘РЎРѓР С—Р С•Р В»РЎРЉР В·Р С•Р Р†Р В°РЎвЂљРЎРЉ Р С‘РЎвЂ¦
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
                    static::persistAddressCoordinatesIfMissing($address, $addressData);
                    $selfPickup = (bool) ($get('self_pickup') ?? false);

                    if ($selfPickup) {
                        $set('shipping_price', 0);
                        $set('shipping_total', 0);
                        return;
                    }

                    $lat = $addressData['latitude'] ?? null;
                    $lng = $addressData['longitude'] ?? null;

                    if ($lat && $lng) {
                        $deliveryService = app(\App\Services\DeliveryCalculationService::class);

                        $orderTotal = static::calcDeliveryBaseFromGet($get, $record);

                        $tempOrder = $record ? clone $record : new Order();
                        $tempOrder->address = $addressData;
                        $tempOrder->self_pickup = $selfPickup;

                        $delivery = $deliveryService->calculateDelivery($tempOrder, $orderTotal);

                        $calculatedPrice = (float) ($delivery['price'] ?? 0);

                        // Р вЂ™Р С’Р вЂ“Р СњР С›: Р С—РЎР‚Р С‘ Р Р†РЎвЂ№Р В±Р С•РЎР‚Р Вµ РЎРѓР С•РЎвЂ¦РЎР‚Р В°Р Р…РЎвЂР Р…Р Р…Р С•Р С–Р С• Р В°Р Т‘РЎР‚Р ВµРЎРѓР В° Р Р†РЎРѓР ВµР С–Р Т‘Р В° Р С•Р В±Р Р…Р С•Р Р†Р В»РЎРЏР ВµР С РЎвЂ Р ВµР Р…РЎС“ Р В°Р Р†РЎвЂљР С•Р СР В°РЎвЂљР С‘РЎвЂЎР ВµРЎРѓР С”Р С‘
                        $set('shipping_price', $calculatedPrice);

                        // Р С•Р С—РЎвЂ Р С‘Р С•Р Р…Р В°Р В»РЎРЉР Р…Р С•: РЎвЂЎРЎвЂљР С•Р В±РЎвЂ№ РЎвЂљРЎР‚Р С‘Р С–Р С–Р ВµРЎР‚РЎвЂ№/Р С—Р В»Р ВµР в„–РЎРѓРЎвЂ¦Р С•Р В»Р Т‘Р ВµРЎР‚РЎвЂ№ РЎвЂљР С•Р В¶Р Вµ РІР‚СљРЎв‚¬Р ВµР Р†Р ВµР В»РЎРЉР Р…РЎС“Р В»Р С‘РЎРѓРЎРЉРІР‚Сњ
                        $set('delivery_coords_trigger', 'coords_' . $lat . '_' . $lng . '_' . time());
                    } else {
                        // Р С”Р С•Р С•РЎР‚Р Т‘Р С‘Р Р…Р В°РЎвЂљ Р Р…Р ВµРЎвЂљ РІР‚вЂќ Р Т‘Р С•РЎРѓРЎвЂљР В°Р Р†Р С”Р В° Р Р…Р Вµ Р СР С•Р В¶Р ВµРЎвЂљ Р С—Р ВµРЎР‚Р ВµРЎРѓРЎвЂЎР С‘РЎвЂљР В°РЎвЂљРЎРЉРЎРѓРЎРЏ
                        // Р СР С•Р В¶Р Р…Р С• Р С•РЎРѓРЎвЂљР В°Р Р†Р С‘РЎвЂљРЎРЉ РЎРѓРЎвЂљР В°РЎР‚РЎС“РЎР‹ РЎРѓРЎС“Р СР СРЎС“ Р С‘Р В»Р С‘ Р С•Р В±Р Р…РЎС“Р В»Р С‘РЎвЂљРЎРЉ (Р Р…Р В° РЎвЂљР Р†Р С•Р в„– Р Р†РЎвЂ№Р В±Р С•РЎР‚)
                        // $set('shipping_price', 0);
                    }
                    // РЎвЂљРЎР‚Р С‘Р С–Р С–Р ВµРЎР‚Р С‘Р С Р С—Р ВµРЎР‚Р ВµРЎРѓРЎвЂЎР ВµРЎвЂљ Р ВµРЎРѓР В»Р С‘ coords Р ВµРЎРѓРЎвЂљРЎРЉ
                    if (!empty($addressData['latitude']) && !empty($addressData['longitude'])) {
                        $key = 'coords_' . $addressData['latitude'] . '_' . $addressData['longitude'] . '_' . time();
                        $set('delivery_coords_trigger', $key);
                        $set('delivery_price_auto', 'auto_' . $key);
                    } else {
                        // Р Р…Р ВµРЎвЂљ coords: Р С—Р С•Р С”Р В°Р В¶Р С‘ Р С—Р С•Р Т‘РЎРѓР С”Р В°Р В·Р С”РЎС“ (Р В° Р С—Р ВµРЎР‚Р ВµРЎРѓРЎвЂЎР ВµРЎвЂљ Р Р…Р ВµР Р†Р С•Р В·Р СР С•Р В¶Р ВµР Р…)
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

            MarkdownEditor::make('notes')->label(__('order.fields.notes'))->columnSpan('full'),
        ];
    }

    protected static function persistAddressCoordinatesIfMissing(ClientAddress $address, array $addressData): void
    {
        $lat = $addressData['latitude'] ?? null;
        $lng = $addressData['longitude'] ?? null;

        if (! $lat || ! $lng) {
            return;
        }

        $needLat = empty($address->latitude);
        $needLng = empty($address->longitude);
        $needFormatted = empty($address->formatted_address) && ! empty($addressData['formatted_address']);
        $needPlace = empty($address->street_place_id) && ! empty($addressData['street_place_id']);

        if (! $needLat && ! $needLng && ! $needFormatted && ! $needPlace) {
            return;
        }

        $payload = [];

        if ($needLat) {
            $payload['latitude'] = (float) $lat;
        }

        if ($needLng) {
            $payload['longitude'] = (float) $lng;
        }

        if ($needFormatted) {
            $payload['formatted_address'] = $addressData['formatted_address'];
        }

        if ($needPlace) {
            $payload['street_place_id'] = $addressData['street_place_id'];
        }

        if ($payload !== []) {
            $address->update($payload);
        }
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

        // Р СџР С•Р С‘РЎРѓР С” (Р Р†Р В°Р В¶Р Р…Р С•: JSON_VALID РЎвЂЎРЎвЂљР С•Р В±РЎвЂ№ Р Р…Р Вµ Р С—Р В°Р Т‘Р В°Р В»Р С• Р Р…Р В° Р В±Р С‘РЎвЂљР С•Р С JSON)
        if ($search !== null && trim($search) !== '') {
            $q->where(function ($qq) use ($like, $locale) {
                // short_name (Р С•Р В±РЎвЂ№РЎвЂЎР Р…Р С• РЎРѓРЎвЂљРЎР‚Р С•Р С”Р В°)
                $qq->orWhere('short_name', 'like', $like);

                // sku
                $qq->orWhere('sku', 'like', $like);

                // title: Р ВµРЎРѓР В»Р С‘ JSON Р Р†Р В°Р В»Р С‘Р Т‘Р Р…РЎвЂ№Р в„– РІР‚вЂќ Р С‘РЎвЂ°Р ВµР С Р С—Р С• Р С—Р ВµРЎР‚Р ВµР Р†Р С•Р Т‘РЎС“, Р ВµРЎРѓР В»Р С‘ Р Р…Р ВµРЎвЂљ РІР‚вЂќ Р С‘РЎвЂ°Р ВµР С Р С”Р В°Р С” Р С—Р С• Р С•Р В±РЎвЂ№РЎвЂЎР Р…Р С•Р в„– РЎРѓРЎвЂљРЎР‚Р С•Р С”Р Вµ
                $path = '$."' . $locale . '"';

                $qq->orWhereRaw(
                    "(JSON_VALID(title) AND JSON_UNQUOTE(JSON_EXTRACT(title, '{$path}')) LIKE ?)",
                    [$like]
                );

                $qq->orWhereRaw(
                    "(NOT JSON_VALID(title) AND title LIKE ?)",
                    [$like]
                );

                // + Р СР С•Р В¶Р Р…Р С• Р ВµРЎвЂ°Р Вµ fallback Р С—Р С• uk Р ВµРЎРѓР В»Р С‘ Р Р…РЎС“Р В¶Р Р…Р С•:
                $qq->orWhereRaw(
                    "(JSON_VALID(title) AND JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"uk\"')) LIKE ?)",
                    [$like]
                );
            });
        }

        $items = $q->limit($limit)->get();

        // РЎвЂЎРЎвЂљР С•Р В±РЎвЂ№ Р Т‘Р В»РЎРЏ Р Т‘Р ВµРЎвЂљР ВµР в„– Р СР С•Р В¶Р Р…Р С• Р В±РЎвЂ№Р В»Р С• Р В±РЎвЂ№РЎРѓРЎвЂљРЎР‚Р С• Р Р†Р В·РЎРЏРЎвЂљРЎРЉ РЎР‚Р С•Р Т‘Р С‘РЎвЂљР ВµР В»РЎРЏ (Р В±Р ВµР В· N+1)
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

            // Р вЂќР В»РЎРЏ Р Т‘Р ВµРЎвЂљР ВµР в„– РЎвЂ¦Р С•РЎвЂљР С‘Р С "РІвЂ С– child РІР‚вЂќ parent"
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

        // [23] [29] РІР‚вЂќ РЎРЊРЎвЂљР С• РЎвЂљР Р†Р С•Р в„– РЎР‚Р В°Р В·Р СР ВµРЎР‚/sku
        $size = $sku !== '' ? " [{$sku}]" : '';

        // РЎвЂЎРЎвЂљР С•Р В±РЎвЂ№ Р Т‘Р С•РЎвЂЎР ВµРЎР‚Р Р…Р С‘Р Вµ РЎв‚¬Р В»Р С‘ Р С—Р С•Р Т‘ Р С–Р В»Р В°Р Р†Р Р…РЎвЂ№Р С Р Р†Р С‘Р В·РЎС“Р В°Р В»РЎРЉР Р…Р С•
        $prefix = $p->parent_id ? "РІвЂ С– " : "";

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

           // Р СџР С•Р С‘РЎРѓР С” (Р Р†Р В°Р В¶Р Р…Р С•: JSON_VALID РЎвЂЎРЎвЂљР С•Р В±РЎвЂ№ Р Р…Р Вµ Р С—Р В°Р Т‘Р В°Р В»Р С• Р Р…Р В° Р В±Р С‘РЎвЂљР С•Р С JSON)
           if ($search !== null && trim($search) !== '') {
               $q->where(function ($qq) use ($like, $locale) {
                   // short_name (Р С•Р В±РЎвЂ№РЎвЂЎР Р…Р С• РЎРѓРЎвЂљРЎР‚Р С•Р С”Р В°)
                   $qq->orWhere('short_name', 'like', $like);

                   // sku
                   $qq->orWhere('sku', 'like', $like);

                   // title: Р ВµРЎРѓР В»Р С‘ JSON Р Р†Р В°Р В»Р С‘Р Т‘Р Р…РЎвЂ№Р в„– РІР‚вЂќ Р С‘РЎвЂ°Р ВµР С Р С—Р С• Р С—Р ВµРЎР‚Р ВµР Р†Р С•Р Т‘РЎС“, Р ВµРЎРѓР В»Р С‘ Р Р…Р ВµРЎвЂљ РІР‚вЂќ Р С‘РЎвЂ°Р ВµР С Р С”Р В°Р С” Р С—Р С• Р С•Р В±РЎвЂ№РЎвЂЎР Р…Р С•Р в„– РЎРѓРЎвЂљРЎР‚Р С•Р С”Р Вµ
                   $path = '$."' . $locale . '"';

                   $qq->orWhereRaw(
                       "(JSON_VALID(title) AND JSON_UNQUOTE(JSON_EXTRACT(title, '{$path}')) LIKE ?)",
                       [$like]
                   );

                   $qq->orWhereRaw(
                       "(NOT JSON_VALID(title) AND title LIKE ?)",
                       [$like]
                   );

                   // + Р СР С•Р В¶Р Р…Р С• Р ВµРЎвЂ°Р Вµ fallback Р С—Р С• uk Р ВµРЎРѓР В»Р С‘ Р Р…РЎС“Р В¶Р Р…Р С•:
                   $qq->orWhereRaw(
                       "(JSON_VALID(title) AND JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"uk\"')) LIKE ?)",
                       [$like]
                   );
               });
           }

           $items = $q->limit($limit)->get();

           // РЎвЂЎРЎвЂљР С•Р В±РЎвЂ№ Р Т‘Р В»РЎРЏ Р Т‘Р ВµРЎвЂљР ВµР в„– Р СР С•Р В¶Р Р…Р С• Р В±РЎвЂ№Р В»Р С• Р В±РЎвЂ№РЎРѓРЎвЂљРЎР‚Р С• Р Р†Р В·РЎРЏРЎвЂљРЎРЉ РЎР‚Р С•Р Т‘Р С‘РЎвЂљР ВµР В»РЎРЏ (Р В±Р ВµР В· N+1)
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

               // Р вЂќР В»РЎРЏ Р Т‘Р ВµРЎвЂљР ВµР в„– РЎвЂ¦Р С•РЎвЂљР С‘Р С "РІвЂ С– child РІР‚вЂќ parent"
               $out[$p->id] = static::formatProductLabel($p, $locale, withParentForChild: true, parent: $parent);
           }

           return $out;
       }*/
    protected static function safeTranslate(?string $raw, string $locale): ?string
    {
        if ($raw === null || $raw === '') return null;

        $trim = ltrim($raw);
        // Р ВµРЎРѓР В»Р С‘ Р СњР вЂў JSON РІР‚вЂќ Р Р†Р С•Р В·Р Р†РЎР‚Р В°РЎвЂ°Р В°Р ВµР С Р С”Р В°Р С” Р ВµРЎРѓРЎвЂљРЎРЉ
        if ($trim === '' || ($trim[0] !== '{' && $trim[0] !== '[')) {
            return $raw;
        }

        $arr = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($arr)) {
            // Р Р…Р ВµР Р†Р В°Р В»Р С‘Р Т‘Р Р…РЎвЂ№Р в„– JSON РІР‚вЂќ Р Р†Р ВµРЎР‚Р Р…РЎвЂР С Р С”Р В°Р С” РЎРѓРЎвЂљРЎР‚Р С•Р С”РЎС“
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

        // Р СџР С•Р С‘РЎРѓР С” (Р Р†Р В°Р В¶Р Р…Р С•: JSON_VALID РЎвЂЎРЎвЂљР С•Р В±РЎвЂ№ Р Р…Р Вµ Р С—Р В°Р Т‘Р В°Р В»Р С• Р Р…Р В° Р В±Р С‘РЎвЂљР С•Р С JSON)
        if ($search !== null && trim($search) !== '') {
            $q->where(function ($qq) use ($like, $locale) {
                // short_name (Р С•Р В±РЎвЂ№РЎвЂЎР Р…Р С• РЎРѓРЎвЂљРЎР‚Р С•Р С”Р В°)
                $qq->orWhere('short_name', 'like', $like);

                // sku
                $qq->orWhere('sku', 'like', $like);

                // title: Р ВµРЎРѓР В»Р С‘ JSON Р Р†Р В°Р В»Р С‘Р Т‘Р Р…РЎвЂ№Р в„– РІР‚вЂќ Р С‘РЎвЂ°Р ВµР С Р С—Р С• Р С—Р ВµРЎР‚Р ВµР Р†Р С•Р Т‘РЎС“, Р ВµРЎРѓР В»Р С‘ Р Р…Р ВµРЎвЂљ РІР‚вЂќ Р С‘РЎвЂ°Р ВµР С Р С”Р В°Р С” Р С—Р С• Р С•Р В±РЎвЂ№РЎвЂЎР Р…Р С•Р в„– РЎРѓРЎвЂљРЎР‚Р С•Р С”Р Вµ
                $path = '$."' . $locale . '"';

                $qq->orWhereRaw(
                    "(JSON_VALID(title) AND JSON_UNQUOTE(JSON_EXTRACT(title, '{$path}')) LIKE ?)",
                    [$like]
                );

                $qq->orWhereRaw(
                    "(NOT JSON_VALID(title) AND title LIKE ?)",
                    [$like]
                );

                // + Р СР С•Р В¶Р Р…Р С• Р ВµРЎвЂ°Р Вµ fallback Р С—Р С• uk Р ВµРЎРѓР В»Р С‘ Р Р…РЎС“Р В¶Р Р…Р С•:
                $qq->orWhereRaw(
                    "(JSON_VALID(title) AND JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"uk\"')) LIKE ?)",
                    [$like]
                );
            });
        }

        $items = $q->limit($limit)->get();

        // РЎвЂЎРЎвЂљР С•Р В±РЎвЂ№ Р Т‘Р В»РЎРЏ Р Т‘Р ВµРЎвЂљР ВµР в„– Р СР С•Р В¶Р Р…Р С• Р В±РЎвЂ№Р В»Р С• Р В±РЎвЂ№РЎРѓРЎвЂљРЎР‚Р С• Р Р†Р В·РЎРЏРЎвЂљРЎРЉ РЎР‚Р С•Р Т‘Р С‘РЎвЂљР ВµР В»РЎРЏ (Р В±Р ВµР В· N+1)
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

            // Р вЂќР В»РЎРЏ Р Т‘Р ВµРЎвЂљР ВµР в„– РЎвЂ¦Р С•РЎвЂљР С‘Р С "РІвЂ С– child РІР‚вЂќ parent"
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

        // РЎР‚Р С•Р Т‘Р С‘РЎвЂљР ВµР В»РЎРЉРЎРѓР С”Р С‘Р в„– РЎвЂљР С•Р Р†Р В°РЎР‚
        if (!$p->parent_id) {
            return $childName . $suffix;
        }

        // Р Т‘Р С•РЎвЂЎР ВµРЎР‚Р Р…Р С‘Р в„– РЎвЂљР С•Р Р†Р В°РЎР‚
        if (!$withParentForChild) {
            return "РІвЂ С– {$childName}" . $suffix;
        }

        $parentName = '';
        if ($parent) {
            $parentName = trim((string) ($parent->short_name ?? ''));
            if ($parentName === '') {
                $parentName = static::safeTranslateJson($parent->getRawOriginal('title'), $locale)
                    ?? (string) ($parent->title ?? '');
            }
        }

        // Р Р…Р С•РЎР‚Р СР В°Р В»Р С‘Р В·РЎС“Р ВµР С, РЎвЂЎРЎвЂљР С•Р В±РЎвЂ№ РЎРѓРЎР‚Р В°Р Р†Р Р…Р ВµР Р…Р С‘Р Вµ Р В±РЎвЂ№Р В»Р С• РЎвЂЎР ВµРЎРѓРЎвЂљР Р…РЎвЂ№Р С
        $norm = fn ($s) => mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $s)));

        // Р ВµРЎРѓР В»Р С‘ РЎРѓР С•Р Р†Р С—Р В°Р Т‘Р В°РЎР‹РЎвЂљ РІР‚вЂќ Р СњР вЂў Р Т‘Р С•Р В±Р В°Р Р†Р В»РЎРЏР ВµР С "РІР‚вЂќ parent"
        if ($parentName !== '' && $norm($childName) === $norm($parentName)) {
            return "РІвЂ С– {$childName}" . $suffix;
        }

        // Р С‘Р Р…Р В°РЎвЂЎР Вµ Р С—Р С•Р С”Р В°Р В·РЎвЂ№Р Р†Р В°Р ВµР С Р С”Р В°Р С” Р В·Р В°Р Т‘РЎС“Р СР В°Р Р…Р С•
        $label = "РІвЂ С– {$childName}";
        if ($parentName !== '') {
            $label .= " РІР‚вЂќ {$parentName}";
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
                        ->preload()        // Р СР С•Р В¶Р Р…Р С• Р С•РЎРѓРЎвЂљР В°Р Р†Р С‘РЎвЂљРЎРЉ, Р Р…Р С• Р С•РЎРѓР Р…Р С•Р Р†Р Р…Р С•Р Вµ РІР‚вЂќ Р С—Р С•Р С‘РЎРѓР С” Р Р…Р С‘Р В¶Р Вµ
                        ->optionsLimit(50)
                        ->getSearchResultsUsing(function (string $search) use ($defaultLocale) {

                            $search = trim($search);
                            $q = \App\Models\Shop\Product::query()
                                ->select(['id','title','short_name','parent_id','sort','sku'])
                                ->where('in_stock', 1); // Р В°Р С”РЎвЂљР С‘Р Р†Р Р…РЎвЂ№Р Вµ (Р С‘ Р С–Р В»Р В°Р Р†Р Р…РЎвЂ№Р Вµ Р С‘ Р Т‘Р С•РЎвЂЎР ВµРЎР‚Р Р…Р С‘Р Вµ)

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

                                // sku (РЎР‚Р В°Р В·Р СР ВµРЎР‚)
                                $q->orWhere('sku', 'like', $like);
                            }

                            // РЎРѓР С•РЎР‚РЎвЂљР С‘РЎР‚Р С•Р Р†Р С”Р В°: РЎР‚Р С•Р Т‘Р С‘РЎвЂљР ВµР В»РЎРЉ -> Р Т‘Р ВµРЎвЂљР С‘
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
                                    ->title('Р СћР С•Р Р†Р В°РЎР‚ Р Р…Р Вµ Р Р…Р В°Р в„–Р Т‘Р ВµР Р…')
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
                            return number_format($total, 2, ',', ' ') . ' Р С–РЎР‚Р Р…';
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
                                            $val->id => "{$val->characteristic->name} - {$val->value} (+{$val->price_modifier}РІвЂљТ‘)",
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
                                ->suffix('РІвЂљТ‘')
                                ->columnSpan(3),
                        ])
                    ])
                    ->collapsed(false)
                    ->itemLabel(fn (array $state): ?string => 'Р СљР С•Р Т‘Р С‘РЎвЂћР С‘Р С”Р В°РЎвЂљР С•РЎР‚')
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
                    ->tooltip('Р вЂќР С•Р В±Р В°Р Р†Р С‘РЎвЂљРЎРЉ РЎвЂ¦Р В°РЎР‚Р В°Р С”РЎвЂљР ВµРЎР‚Р С‘РЎРѓРЎвЂљР С‘Р С”РЎС“')
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
    //   Р СћР В°Р В±Р В»Р С‘РЎвЂ Р В° РЎРѓ Р СР С•Р Т‘Р В°Р В»Р С”Р С•Р в„– Р’В«Р РЋРЎвЂљР В°РЎвЂљРЎС“РЎРѓРЎвЂ№Р’В»
    // =========================
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['clients.group', 'clientAddress', 'lastLiqpayLog', 'items.product', 'source']) // РІвЂ С’ Р С‘РЎРѓР С—РЎР‚Р В°Р Р†Р В»Р ВµР Р…Р С‘Р Вµ N+1 Р С—РЎР‚Р С•Р В±Р В»Р ВµР СРЎвЂ№
                ->when(
                    static::class === \App\Filament\Resources\Callcenter\OrderResource::class,
                    fn (Builder $q) => $q
                        // Important: addSelect() on a query with no explicit columns replaces "*".
                        // Force selecting all order columns so summarizers and actions keep working.
                        ->select('bs_shop_orders.*')
                        ->where('bs_shop_orders.created_at', '>=', '2026-05-01 00:00:00')
                        ->addSelect(DB::raw(
                        'GREATEST('
                        . 'bs_shop_orders.created_at, '
                        . 'TIMESTAMP('
                        . 'COALESCE(bs_shop_orders.dat, DATE(bs_shop_orders.created_at)), '
                        . 'COALESCE(bs_shop_orders.time_start, TIME(bs_shop_orders.created_at))'
                        . ')'
                        . ') AS order_dt'
                    ))
                        ->withExists([
                            'cashalotLogs as has_success_cashalot_log' => fn (Builder $q) => $q
                                ->where('status', 'success'),
                        ])
                )
            )
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50])
            ->columns(array_filter([
                TextColumn::make('number')
                    ->label('')
                    ->extraHeaderAttributes([

                        'style' => 'line-height:1.1;min-width:8rem;width:8rem;',
                        'x-data' => '{}',
                        // Р Р†РЎРѓРЎвЂљР В°Р Р†Р В»РЎРЏР ВµР С "РЎР‚Р С•Р Т‘Р Р…Р С•Р в„–" Р В»Р ВµР в„–Р В±Р В» Filament, РЎвЂЎРЎвЂљР С•Р В±РЎвЂ№ РЎРѓРЎвЂљР С‘Р В»Р С‘ РЎРѓР С•Р Р†Р С—Р В°Р В»Р С‘
                        'x-html' => json_encode(
                            '<span class="fi-ta-header-cell-label text-sm font-medium">'
                            . (static::class === \App\Filament\Resources\Callcenter\OrderResource::class
                                ? __('order.columns.number_dates_delivery')
                                : __('order.columns.number_dates'))
                            .'</span>'
                        ) ])
                    ->grow(false) // РЎвЂЎРЎвЂљР С•Р В±РЎвЂ№ Р С”Р С•Р В»Р С•Р Р…Р С”Р В° Р Р…Р Вµ РЎС“Р В¶Р С‘Р СР В°Р В»Р В°РЎРѓРЎРЉ Р Т‘РЎР‚РЎС“Р С–Р С‘Р СР С‘

                    ->extraCellAttributes(['style' => 'min-width:8rem;width:8rem;'])
                    ->searchable(isIndividual: true)
                    ->verticalAlignment(VerticalAlignment::Center)
                    ->sortable()
                    ->description(function (Order $record) {
                        $isCallcenter = static::class === \App\Filament\Resources\Callcenter\OrderResource::class;
                        $tz = config('app.timezone', 'Europe/Kyiv');

                        $createdMoment = null;
                        if ($record->created_at) {
                            $createdMoment = $record->created_at instanceof \DateTimeInterface
                                ? Carbon::instance($record->created_at)->setTimezone($tz)
                                : Carbon::parse((string) $record->created_at, $tz);
                        }

                        $businessMoment = null;
                        if ($isCallcenter) {
                            $dateStr = '';
                            if ($record->dat instanceof \DateTimeInterface) {
                                $dateStr = Carbon::instance($record->dat)->toDateString();
                            } elseif (! blank($record->dat)) {
                                $dateStr = Carbon::parse((string) $record->dat)->toDateString();
                            } elseif ($record->created_at) {
                                $dateStr = Carbon::instance($record->created_at)->setTimezone($tz)->toDateString();
                            }

                            $timeStr = (string) $record->getRawOriginal('time_start');
                            $timeStr = trim($timeStr);
                            if ($timeStr === '' && $record->created_at) {
                                $timeStr = Carbon::instance($record->created_at)->setTimezone($tz)->format('H:i:s');
                            }

                            if ($dateStr !== '') {
                                $businessMoment = Carbon::parse($dateStr, $tz);
                                if ($timeStr !== '') {
                                    $businessMoment->setTimeFromTimeString($timeStr);
                                }
                            }
                        }

                        $orderMoment = $createdMoment;
                        if ($businessMoment && (! $orderMoment || $businessMoment->greaterThan($orderMoment))) {
                            $orderMoment = $businessMoment;
                        }

                        $orderDateText = $orderMoment ? $orderMoment->format('d.m H:i') : 'РІР‚вЂќ';

                        if (! $isCallcenter) {
                            return $orderDateText;
                        }

                        $siteName = trim((string) ($record->source?->name ?? ''));
                        $siteBadge = $siteName === ''
                            ? ''
                            : '<span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold" style="background:#D9F99D;color:#365314;">'
                                . e($siteName)
                                . '</span>';

                        $deliveryBadge = '';
                        if ((bool) $record->as_soon_possible) {
                            $deliveryBadge = '<span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold" style="background:#DBEAFE;color:#1D4ED8;">ASAP</span>';
                        } elseif ($record->date_order || $record->time_order) {
                            $deliveryDate = $record->date_order ? Carbon::parse($record->date_order)->format('d.m') : 'РІР‚вЂќ';
                            $deliveryTime = $record->time_order ? Carbon::parse($record->time_order)->format('H:i') : 'РІР‚вЂќ';
                            $deliveryBadge = '<span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold" style="background:#DBEAFE;color:#1D4ED8;">'
                                . e($deliveryDate . ' ' . $deliveryTime)
                                . '</span>';
                        }

                        return new HtmlString(
                            '<div class="leading-snug">'
                            . '<div>' . e($orderDateText) . '</div>'
                            . ($deliveryBadge !== '' ? '<div class="mt-1">' . $deliveryBadge . '</div>' : '')
                            . ($siteBadge !== '' ? '<div class="mt-1">' . $siteBadge . '</div>' : '')
                            . '</div>'
                        );
                    }),
                  //  ->extraAttributes(['class' => 'cursor-pointer underline']),
                  //  ->action('statuses'), // Р С”Р В»Р С‘Р С” Р С—Р С• Р Р…Р С•Р СР ВµРЎР‚РЎС“ Р С•РЎвЂљР С”РЎР‚Р С•Р ВµРЎвЂљ Р СР С•Р Т‘Р В°Р В»Р С”РЎС“ РЎРѓРЎвЂљР В°РЎвЂљРЎС“РЎРѓР С•Р Р†

                TextColumn::make('clients.name')
                    ->label(__('order.columns.client'))
                    ->sortable()
                    ->toggleable()
                    ->searchable(isIndividual: true, query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('clients', function (Builder $q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                    })
                    ->extraHeaderAttributes(['style' => 'min-width:10rem;width:10rem;'])
                    ->extraCellAttributes(['style' => 'min-width:10rem;width:10rem;'])
                    ->description(function (Order $record) {
                        $phoneBadge = '';
                        $groupBadge = '';

                        $phone = trim((string) ($record->clients?->phone ?? ''));
                        if ($phone !== '') {
                            $phoneBadge = '<span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs" '
                                . 'style="background-color:#dcfce7;color:#166534;border:1px solid #86efac;">'
                                . e($phone)
                                . '</span>';
                        }

                        if (static::class === \App\Filament\Resources\Callcenter\OrderResource::class) {
                            $group = $record->clients?->group;
                            $groupName = trim((string) ($group?->display_name ?? ''));

                            if ($groupName !== '') {
                                $isBlacklist = (bool) ($group->is_blacklist ?? false);

                                $groupBadge = '<span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs" '
                                    . 'style="background:' . ($isBlacklist ? '#fee2e2' : '#eff6ff') . ';color:' . ($isBlacklist ? '#b91c1c' : '#1d4ed8') . ';border:1px solid ' . ($isBlacklist ? '#fecaca' : '#bfdbfe') . ';font-weight:600;">'
                                    . e(($isBlacklist ? 'СЂСџвЂР‹ ' : '') . $groupName)
                                    . '</span>';
                            }
                        }

                        if ($phoneBadge === '' && $groupBadge === '') {
                            return null;
                        }

                        return new HtmlString(
                            '<div class="space-y-1">'
                            . ($phoneBadge !== '' ? '<div>' . $phoneBadge . '</div>' : '')
                            . ($groupBadge !== '' ? '<div>' . $groupBadge . '</div>' : '')
                            . '</div>'
                        );
                    })
                    ->url(fn (Order $record) =>
                    $record->clients_id
                        ? ClientResource::getUrl('edit', ['record' => $record->clients_id])
                        : null
                    )
                    ->openUrlInNewTab(),

                static::class === \App\Filament\Resources\Callcenter\OrderResource::class
                    ? ViewColumn::make('status_compact')
                        ->label(__('order.columns.status'))
                        ->grow(false)
                        ->extraHeaderAttributes(['class' => 'min-w-[9rem] w-[9rem]'])
                        ->extraCellAttributes(['class' => 'min-w-[9rem] w-[9rem]'])
                        ->view('filament.tables.columns.callcenter-status')
                        ->viewData(function (Order $record): array {
                            $status = $record->status instanceof OrderStatus
                                ? $record->status
                                : OrderStatus::tryFrom((string) $record->status);

                            return [
                                'statusLabel' => $status?->getLabel() ?? (string) $record->status,
                                'statusColors' => $status?->getFrontendColors() ?? ['bg' => '#E5E7EB', 'text' => '#374151'],
                            ];
                        })
                    : TextColumn::make('status')->label(__('order.columns.status'))->badge(),

                TextColumn::make('operator_time')
                    ->label(new HtmlString(__('order.columns.operator_time')))
                    ->html()
                    ->getStateUsing(fn (Order $record) => static::formatOperatorTime($record))
                    ->grow(false)
                    ->extraHeaderAttributes(['class' => 'min-w-[7rem]'])
                    ->extraCellAttributes(['class' => 'min-w-[7rem]']),

                TextColumn::make('kitchen_time')
                    ->label(new HtmlString(__('order.columns.kitchen_time')))
                    ->html()
                    ->getStateUsing(fn (Order $record) => static::formatKitchenTime($record))
                    ->grow(false)
                    ->extraHeaderAttributes(['class' => 'min-w-[7rem]'])
                    ->extraCellAttributes(['class' => 'min-w-[7rem]']),

                TextColumn::make('delivery_time')
                    ->label(new HtmlString(__('order.columns.delivery_time')))
                    ->html()
                    ->getStateUsing(fn (Order $record) => static::formatDeliveryTime($record))
                    ->grow(false)
                    ->extraHeaderAttributes(['class' => 'min-w-[7rem]'])
                    ->extraCellAttributes(['class' => 'min-w-[7rem]']),

                TextColumn::make('total_time')
                    ->label(new HtmlString(__('order.columns.total_time')))
                    ->html()
                    ->getStateUsing(fn (Order $record) => static::formatTotalTime($record))
                    ->grow(false)
                    ->extraHeaderAttributes(['class' => 'min-w-[7rem]'])
                    ->extraCellAttributes(['class' => 'min-w-[7rem]']),

                TextColumn::make('total_price')
                    ->label('')
                    ->extraHeaderAttributes([
                        'style' => 'line-height:1.1;',
                        'x-data' => '{}',
                        'x-html' => json_encode(
                            '<span class="fi-ta-header-cell-label text-sm font-medium">'
                            .__('order.columns.amount_discount_delivery_total')
                            .'</span>'
                        ),
                    ])
                    ->formatStateUsing(function ($state, Order $record) {
                        $total = number_format((float) ($record->total_price ?? 0), 2, ',', ' ') . ' Р С–РЎР‚Р Р…';
                        $discountValue = (float) ($record->discount_total ?? 0);
                        $discount = $discountValue != 0
                            ? number_format($discountValue, 2, ',', ' ') . ' Р С–РЎР‚Р Р…'
                            : 'РІР‚вЂќ';
                        $shippingValue = $record->resolveDeliveryAmount();
                        $shipping = $shippingValue != 0
                            ? number_format($shippingValue, 2, ',', ' ') . ' Р С–РЎР‚Р Р…'
                            : 'РІР‚вЂќ';
                        $grand = number_format((float) ($record->grand_total ?? 0), 2, ',', ' ') . ' Р С–РЎР‚Р Р…';

                        $isCallcenter = static::class === \App\Filament\Resources\Callcenter\OrderResource::class;
                        $isCash = (($record->payment instanceof PaymentMethodEnum ? $record->payment->value : (int) $record->payment) === PaymentMethodEnum::CASH->value);
                        $cashFromValue = (float) ($record->cash_from ?? 0);

                        $cashHint = '';
                        if ($isCallcenter && $isCash && $cashFromValue > 0) {
                            $changeValue = max(0, $cashFromValue - (float) ($record->grand_total ?? 0));

                            $cashFromText = number_format($cashFromValue, 2, ',', ' ');
                            $changeText = number_format($changeValue, 2, ',', ' ');

                            $cashFromText = rtrim(rtrim($cashFromText, '0'), ',');
                            $changeText = rtrim(rtrim($changeText, '0'), ',');

                            $cashHint = '<div class="mt-1 inline-block rounded px-1.5 py-0.5 text-[11px] font-semibold" style="background:#dcfce7;color:#166534;">'
                                . '<span style="color:#1d4ed8;">' . e($cashFromText) . '</span>'
                                . '<span>/</span>'
                                . '<span>' . e($changeText) . '</span>'
                                . '</div>';
                        }

                        return new HtmlString(
                            '<div class="leading-snug">'
                            . '<div>' . e($total) . '</div>'
                            . '<div style="color:#dc2626;">' . e($discount) . '</div>'
                            . '<div style="color:#15803d;">' . e($shipping) . '</div>'
                            . '<div style="color:#1d4ed8;font-weight:600;">' . e($grand) . '</div>'
                            . $cashHint
                            . '</div>'
                        );
                    })
                    ->html()
                    ->alignRight()
                    ->summarize([
                        Summarizer::make('totals_compact')
                            ->label('')
                            ->using(function ($query) {
                                $sumTotal = (float) (clone $query)->sum('total_price');
                                $sumDiscount = (float) (clone $query)->sum('discount_total');
                                $sumShipping = (float) (clone $query)->sum('shipping_total');
                                $sumGrand = (float) (clone $query)->sum('grand_total');

                                return new HtmlString(
                                    '<span style="display:block;line-height:1.2;margin:0;padding:0;">'
                                    . '<span style="display:block;margin:0;padding:0;">' . number_format($sumTotal, 2, ',', ' ') . ' Р С–РЎР‚Р Р…</span>'
                                    . '<span style="display:block;margin:0;padding:0;color:#dc2626;">' . number_format($sumDiscount, 2, ',', ' ') . ' Р С–РЎР‚Р Р…</span>'
                                    . '<span style="display:block;margin:0;padding:0;color:#15803d;">' . number_format($sumShipping, 2, ',', ' ') . ' Р С–РЎР‚Р Р…</span>'
                                    . '<span style="display:block;margin:0;padding:0;color:#1d4ed8;font-weight:600;">' . number_format($sumGrand, 2, ',', ' ') . ' Р С–РЎР‚Р Р…</span>'
                                    . '</span>'
                                );
                            }),
                    ]),

                static::class !== \App\Filament\Resources\Callcenter\OrderResource::class
                    ? TextColumn::make('date_order')->label('')
                        ->extraHeaderAttributes([
                            'class' => 'th-wrap min-w-[10rem]',
                            'x-data' => '{}',
                            'x-html' => json_encode(
                                '<span class="fi-ta-header-cell-label text-sm font-medium">'
                                .'Р вЂќР В°РЎвЂљР В°<br>Р Т‘Р С•РЎРѓРЎвЂљР В°Р Р†Р С”Р С‘'
                                .'</span>'
                            ),
                            'style'  => 'line-height: 1.1;',
                        ])
                        ->formatStateUsing(function ($state, Order $record) {
                            if (! $state) {
                                return 'РІР‚вЂќ';
                            }

                            $date = Carbon::parse($state)->format('d.m');
                            $time = $record->time_order ? Carbon::parse($record->time_order)->format('H:i') : 'РІР‚вЂќ';

                            return new HtmlString($date . '<br>' . $time);
                        })
                        ->html()
                        ->toggleable()
                    : null,
                TextColumn::make('delivery_info')
                    ->label(__('order.columns.delivery_method'))
                    ->getStateUsing(fn (Order $record) => static::deliveryMethodLabel($record))
                    ->badge() // Р С”РЎР‚Р В°РЎРѓР С‘Р Р†РЎвЂ№Р в„– Р В±Р ВµР в„–Р Т‘Р В¶
                    ->grow(false) // РЎвЂЎРЎвЂљР С•Р В±РЎвЂ№ РЎв‚¬Р С‘РЎР‚Р С‘Р Р…Р В° Р Р…Р Вµ РІР‚СљРЎРѓРЎР‰Р ВµР Т‘Р В°Р В»Р В°РЎРѓРЎРЉРІР‚Сњ Р Т‘РЎР‚РЎС“Р С–Р С‘Р СР С‘ Р С”Р С•Р В»Р С•Р Р…Р С”Р В°Р СР С‘
                    ->extraHeaderAttributes(['class' => 'min-w-[22rem] w-[22rem]'])
                    ->extraCellAttributes(['class' => 'min-w-[22rem] w-[22rem]'])
                    ->searchable(isIndividual: true, query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            $q->whereHas('clientAddress', function (Builder $addr) use ($search): void {
                                $addr->where('street', 'like', "%{$search}%")
                                    ->orWhere('house', 'like', "%{$search}%")
                                    ->orWhere('apartment', 'like', "%{$search}%")
                                    ->orWhere('entrance', 'like', "%{$search}%")
                                    ->orWhere('floor', 'like', "%{$search}%");
                            });
                        });
                    })
                    ->icon(fn ($record) => $record->self_pickup ? 'heroicon-m-shopping-bag' : 'heroicon-m-map-pin')
                    ->color(fn ($record) => $record->self_pickup ? 'warning' : 'primary') // Р Т‘РЎР‚РЎС“Р С–Р С•Р в„– РЎвЂ Р Р†Р ВµРЎвЂљ Р Т‘Р В»РЎРЏ РЎРѓР В°Р СР С•Р Р†РЎвЂ№Р Р†Р С•Р В·Р В°
                    // Р СџР С•Р Т‘Р С—Р С‘РЎРѓРЎРЉ Р СР ВµР В»Р С”Р С‘Р С РЎвЂљР ВµР С”РЎРѓРЎвЂљР С•Р С РІР‚вЂќ Р В°Р Т‘РЎР‚Р ВµРЎРѓ (РЎвЂљР С•Р В»РЎРЉР С”Р С• Р ВµРЎРѓР В»Р С‘ Р Р…Р Вµ РЎРѓР В°Р СР С•Р Р†РЎвЂ№Р Р†Р С•Р В·)
                    ->description(function (Order $record) {
                        if ($record->self_pickup) return null;

                        $addressLine = null;

                        // 1) РЎРѓР Р…Р В°РЎвЂЎР В°Р В»Р В° Р С—РЎР‚Р С•Р В±РЎС“Р ВµР С Р С—РЎР‚Р С‘Р Р†РЎРЏР В·Р В°Р Р…Р Р…РЎвЂ№Р в„– Р В°Р Т‘РЎР‚Р ВµРЎРѓ
                        if ($a = $record->clientAddress) {
                            $parts = [
                                $a->street,
                                $a->house,
                                $a->apartment ? __('order.address_prefixes.apartment') . ' ' . $a->apartment : null,
                                $a->entrance  ? __('order.address_prefixes.entrance') . ' ' . $a->entrance : null,
                                $a->floor     ? __('order.address_prefixes.floor') . ' ' . $a->floor : null,
                            ];
                            $line = trim(implode(', ', array_filter($parts)));
                            $addressLine = $line !== '' ? $line : null;
                        }

                        // 2) Р С‘Р Р…Р В°РЎвЂЎР Вµ РІР‚вЂќ Р С‘Р В· JSON Р С—Р С•Р В»РЎРЏ order.address (Р ВµРЎРѓР В»Р С‘ Р ВµРЎРѓРЎвЂљРЎРЉ)
                        if ($addressLine === null) {
                            $addr = (array) ($record->address ?? []);
                            $parts = [
                                $addr['street']   ?? null,
                                $addr['house']    ?? null,
                                !empty($addr['apartment']) ? __('order.address_prefixes.apartment') . ' ' . $addr['apartment'] : null,
                                !empty($addr['entrance'])  ? __('order.address_prefixes.entrance') . ' ' . $addr['entrance']  : null,
                                !empty($addr['floor'])     ? __('order.address_prefixes.floor') . ' ' . $addr['floor']       : null,
                            ];
                            $line = trim(implode(', ', array_filter($parts)));
                            $addressLine = $line !== '' ? $line : 'РІР‚вЂќ';
                        }

                        if (static::class !== \App\Filament\Resources\Callcenter\OrderResource::class) {
                            return $addressLine;
                        }

                        $courierComment = trim((string) ($record->courier_comment ?? ''));

                        if ($courierComment === '') {
                            return $addressLine;
                        }

                        return new HtmlString(
                            '<div class="leading-snug">'
                            . '<div>' . e($addressLine) . '</div>'
                            . '<div style="margin-top:4px;display:inline-block;background:#fee2e2;color:#b91c1c;padding:2px 6px;border-radius:6px;font-weight:600;">'
                            . e($courierComment)
                            . '</div>'
                            . '</div>'
                        );
                    })
                    ->wrap()        // Р С—Р ВµРЎР‚Р ВµР Р…Р С•РЎРѓ Р Т‘Р В»Р С‘Р Р…Р Р…РЎвЂ№РЎвЂ¦ Р В°Р Т‘РЎР‚Р ВµРЎРѓР С•Р Р†
                    ->toggleable(), // Р СР С•Р В¶Р Р…Р С• РЎРѓР С—РЎР‚РЎРЏРЎвЂљР В°РЎвЂљРЎРЉ Р Р† Р Р…Р В°РЎРѓРЎвЂљРЎР‚Р С•Р в„–Р С”Р В°РЎвЂ¦ РЎвЂљР В°Р В±Р В»Р С‘РЎвЂ РЎвЂ№

                static::class === \App\Filament\Resources\Callcenter\OrderResource::class
                    ? ViewColumn::make('items_inline')
                        ->label(__('order.columns.items'))
                        ->grow(false)
                        ->extraHeaderAttributes(['class' => 'min-w-[16rem]'])
                        ->extraCellAttributes(['class' => 'min-w-[16rem]'])
                        ->view('filament.tables.columns.order-items-inline')
                    : null,

                TextColumn::make('payment')
                    ->label(__('order.columns.payment'))
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(
                        function (null|PaymentMethodEnum $state): string {
                            if (! $state) {
                                return 'РІР‚вЂќ';
                            }

                            if ($state === PaymentMethodEnum::INVOICE) {
                                return static::invoiceLabel();
                            }

                            return $state->label();
                        }
                    )
                    ->description(function (Order $record): ?HtmlString {
                        if (static::class !== \App\Filament\Resources\Callcenter\OrderResource::class) {
                            return null;
                        }

                        $isFiscalized = (bool) ($record->has_success_cashalot_log ?? false);
                        $isReturnedFiscal = (bool) ($record->has_success_cashalot_return_log ?? false);

                        if (! $isFiscalized && ! $isReturnedFiscal) {
                            return null;
                        }

                        $label = $isReturnedFiscal ? 'С‡РµРє С„РёСЃРєР°Р»СЊРЅРѕ РѕС‚РјРµРЅРµРЅ' : 'С„РёСЃРєР°Р»РёР·РѕРІР°РЅРѕ';

                        return new HtmlString('<span style="display:inline-block;margin-top:3px;border-radius:999px;background:#fee2e2;color:#b91c1c;padding:2px 7px;font-size:11px;font-weight:700;line-height:1.2;">'.e($label).'</span>');
                    }),
                // РІВ¬вЂЎРїС‘РЏ Р СњР С›Р вЂ™Р С’Р Р‡ Р С™Р С›Р вЂєР С›Р СњР С™Р С’ L i q P a y
                BadgeColumn::make('liqpay_status')
                    ->label(__('order.columns.liqpay'))
                    ->getStateUsing(fn (Order $record) => $record->lastLiqpayLog?->status)
                    // РЎвЂЎРЎвЂљР С• Р С—Р С‘РЎРѓР В°РЎвЂљРЎРЉ Р Р…Р В° Р В±Р ВµР в„–Р Т‘Р В¶Р Вµ
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'success', 'sandbox'        => __('order.liqpay.success'),
                            'wait_accept', 'processing' => __('order.liqpay.processing'),
                            'failure', 'error'          => __('order.liqpay.failure'),
                            'reversed', 'refunded'      => __('order.liqpay.refund'),
                            default                     => __('order.liqpay.none'),
                        };
                    })
                    // РЎвЂ Р Р†Р ВµРЎвЂљ Р В±Р ВµР в„–Р Т‘Р В¶Р В°
                    ->color(function ($state) {
                        return match ($state) {
                            'success', 'sandbox'        => 'success',   // Р В·Р ВµР В»РЎвЂР Р…РЎвЂ№Р в„–
                            'wait_accept', 'processing' => 'warning',   // Р В¶РЎвЂР В»РЎвЂљРЎвЂ№Р в„–
                            'failure', 'error'          => 'danger',    // Р С”РЎР‚Р В°РЎРѓР Р…РЎвЂ№Р в„–
                            'reversed', 'refunded'      => 'gray',      // РЎРѓР ВµРЎР‚РЎвЂ№Р в„–
                            default                     => 'secondary', // Р С•Р В±РЎвЂ№РЎвЂЎР Р…РЎвЂ№Р в„–
                        };
                    })
                    // Р С”РЎР‚Р В°РЎвЂљР С”Р С‘Р в„– Р С”Р С•Р СР СР ВµР Р…РЎвЂљ Р С—РЎР‚Р С‘ Р Р…Р В°Р Р†Р ВµР Т‘Р ВµР Р…Р С‘Р С‘
                    ->tooltip(function (Order $record) {
                        $log = $record->lastLiqpayLog;

                        if (! $log) {
                            return __('order.liqpay.no_callback');
                        }

                        $payload = is_array($log->payload)
                            ? $log->payload
                            : (json_decode($log->payload ?? '[]', true) ?: []);

                        $err = $payload['err_description'] ?? $payload['err_code'] ?? null;

                        return match ($log->status) {
                            'success', 'sandbox'        => 'Р С›Р С—Р В»Р В°РЎвЂљР В° Р С—РЎР‚Р С•Р в„–РЎв‚¬Р В»Р В° РЎС“РЎРѓР С—РЎвЂ“РЎв‚¬Р Р…Р С•',
                            'wait_accept', 'processing' => 'Р СџР В»Р В°РЎвЂљРЎвЂ“Р В¶ РЎвЂ°Р Вµ Р С•Р В±РЎР‚Р С•Р В±Р В»РЎРЏРЎвЂќРЎвЂљРЎРЉРЎРѓРЎРЏ LiqPay',
                            'failure', 'error'          => $err
                                ? 'Р СџР С•Р СР С‘Р В»Р С”Р В° Р С•Р С—Р В»Р В°РЎвЂљР С‘: ' . $err
                                : 'Р СџР С•Р СР С‘Р В»Р С”Р В° Р С•Р С—Р В»Р В°РЎвЂљР С‘ Р Р…Р В° РЎРѓРЎвЂљР С•РЎР‚Р С•Р Р…РЎвЂ“ LiqPay',
                            'reversed', 'refunded'      => 'Р СџР В»Р В°РЎвЂљРЎвЂ“Р В¶ Р С—Р С•Р Р†Р ВµРЎР‚Р Р…РЎС“РЎвЂљР С• / Р Р†РЎвЂ“Р Т‘РЎв‚¬Р С”Р С•Р Т‘Р С•Р Р†Р В°Р Р…Р С•',
                            default                     => 'Р РЋРЎвЂљР В°РЎвЂљРЎС“РЎРѓ LiqPay: Р Р…Р ВµР Р†РЎвЂ“Р Т‘Р С•Р СР С‘Р в„–',
                        };
                    })
                    ->sortable(false)
                    ->toggleable(),
            ]))
            ->defaultSort(
                static::class === \App\Filament\Resources\Callcenter\OrderResource::class ? 'order_dt' : 'created_at',
                'desc'
            ) // СЂСџвЂв‚¬ РЎРѓР С•РЎР‚РЎвЂљР С‘РЎР‚Р С•Р Р†Р С”Р В° Р С—Р С• РЎС“Р СР С•Р В»РЎвЂЎР В°Р Р…Р С‘РЎР‹
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(12)
            ->filters([
                SelectFilter::make('source_id')
                    ->label(__('order.filters.site'))
                    ->options(fn () => CallcenterSource::query()->orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->preload()
                    ->columnSpan(1),

                SelectFilter::make('import_type')
                    ->label(__('order.filters.type'))
                    ->options([
                        'imported' => __('order.filters.imported'),
                        'local' => __('order.filters.local'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'imported' => $query->whereNotNull('source_id'),
                            'local' => $query->whereNull('source_id'),
                            default => $query,
                        };
                    })
                    ->columnSpan(1),

                SelectFilter::make('payment')     // РЎвЂљР С• Р В¶Р Вµ Р С‘Р СРЎРЏ Р С—Р С•Р В»РЎРЏ
                ->label(__('order.columns.payment'))
                    ->options(static::paymentOptionsAdmin())
                    ->multiple()
                    ->preload()
                    ->columnSpan(1),

                Filter::make('cashalot_fiscalized')
                    ->label('Р В¤РЎвЂ“РЎРѓР С”Р В°Р В»РЎРЉР Р…РЎвЂ“ РЎвЂљРЎвЂ“Р В»РЎРЉР С”Р С‘')
                    ->visible(fn (): bool => static::class === \App\Filament\Resources\Callcenter\OrderResource::class)
                    ->query(fn (Builder $query): Builder => $query->whereHas(
                        'cashalotLogs',
                        fn (Builder $cashalotLogs): Builder => $cashalotLogs->where('status', 'success')
                    ))
                    ->columnSpan(1),

                TrashedFilter::make()
                    ->label(__('order.filters.deleted_records'))
                    ->placeholder(__('order.filters.without_deleted'))
                    ->trueLabel(__('order.filters.with_deleted_only'))
                    ->falseLabel(__('order.filters.without_deleted'))
                    ->columnSpan(1),
                Filter::make('created_at')
                    ->columnSpan(7)
                    ->form([
                        ToggleButtons::make('quick_range')
                            ->label(__('order.filters.quick'))
                            ->inline()
                            ->options([
                                'today' => __('order.filters.today'),
                                'tomorrow' => __('order.filters.tomorrow'),
                                'yesterday' => __('order.filters.yesterday'),
                                'day_before' => __('order.filters.day_before'),
                                'this_week' => __('order.filters.this_week'),
                                'this_month' => __('order.filters.this_month'),
                            ])
                            ->columnSpan(8),
                        DatePicker::make('created_from')
                            ->label(__('order.filters.date_from'))
                            ->placeholder(fn ($state): string => now()->subYear()->format('d.m.Y'))
                            ->extraInputAttributes(['class' => 'w-[7.5rem] text-sm'])
                            ->columnSpan(2),
                        DatePicker::make('created_until')
                            ->label(__('order.filters.date_to'))
                            ->placeholder(fn ($state): string => now()->format('d.m.Y'))
                            ->extraInputAttributes(['class' => 'w-[7.5rem] text-sm'])
                            ->columnSpan(2),
                    ])
                    ->columns(12)
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['created_from'] ?? null;
                        $until = $data['created_until'] ?? null;

                        if ((! $from && ! $until) && ($data['quick_range'] ?? null)) {
                            $today = now()->startOfDay();
                            $range = match ($data['quick_range']) {
                                'today' => [$today, now()->endOfDay()],
                                'tomorrow' => [now()->addDay()->startOfDay(), now()->addDay()->endOfDay()],
                                'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
                                'day_before' => [now()->subDays(2)->startOfDay(), now()->subDays(2)->endOfDay()],
                                'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
                                'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
                                default => [null, null],
                            };
                            $from = $range[0] ?? null;
                            $until = $range[1] ?? null;
                        }

                        return $query
                            ->when($from, fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))
                            ->when($until, fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['quick_range'] ?? null) {
                            $indicators['quick_range'] = __('order.filters.quick') . ': ' . __('order.filters.' . $data['quick_range']);
                        }
                        if ($data['created_from'] ?? null) {
                            $indicators['created_from'] = __('order.filters.date_from') . ': ' . Carbon::parse($data['created_from'])->toFormattedDateString();
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = __('order.filters.date_to') . ': ' . Carbon::parse($data['created_until'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
            ])

            ->actions([
                // Р СљР С›Р вЂќР С’Р вЂєР В¬Р СњР С›Р вЂў Р вЂќР вЂўР в„ўР РЋР СћР вЂ™Р ВР вЂў Р’В«Р РЋРЎвЂљР В°РЎвЂљРЎС“РЎРѓРЎвЂ№Р’В»
                Action::make('statuses')
                    ->label(static::class === \App\Filament\Resources\Callcenter\OrderResource::class ? '' : __('order.actions.statuses'))
                    ->icon(static::class === \App\Filament\Resources\Callcenter\OrderResource::class ? '' : 'heroicon-m-adjustments-horizontal')
                    ->color('gray')
                    ->extraAttributes(static::class === \App\Filament\Resources\Callcenter\OrderResource::class ? ['class' => 'hidden'] : [])
                    ->modalHeading(fn (Order $r) => __('order.actions.statuses_modal_heading', ['number' => $r->number]))
                    ->modalWidth('lg')
                    ->fillForm(fn (Order $record): array => [
                        'current' => $record->status?->value,
                        'status_ui' => $record->status?->value ?? OrderStatus::New->value,
                    ])
                    ->form(fn (Order $record) => static::statusModalForm())
                    ->action(function (array $data, Order $record) {
                        $user = auth('admin')->user();
                        if (!$user || !$user instanceof \App\Models\User) {
                            Notification::make()->danger()->title('Р С›РЎв‚¬Р С‘Р В±Р С”Р В° Р В°Р Р†РЎвЂљР С•РЎР‚Р С‘Р В·Р В°РЎвЂ Р С‘Р С‘')->send();
                            return;
                        }

                        $from = $record->status;
                        $to   = OrderStatus::from($data['status_ui']);

                        if ($to->value === $from->value) {
                            Notification::make()->title(__('order.notifications.status_not_changed'))->info()->send();
                            return;
                        }

                        if (! static::canSetStatus($to)) {
                            Notification::make()->danger()->title('Р СњР ВµРЎвЂљ Р С—РЎР‚Р В°Р Р† Р Р…Р В° РЎС“РЎРѓРЎвЂљР В°Р Р…Р С•Р Р†Р С”РЎС“ РЎРЊРЎвЂљР С•Р С–Р С• РЎРѓРЎвЂљР В°РЎвЂљРЎС“РЎРѓР В°')->send();
                            return;
                        }

                        $oldRank = $from->rank();
                        $newRank = $to->rank();

                        if ($newRank < $oldRank && ! static::canDowngrade()) {
                            Notification::make()->danger()->title('Р СњР ВµРЎвЂљ Р С—РЎР‚Р В°Р Р† Р Р†Р С•Р В·Р Р†РЎР‚Р В°РЎвЂ°Р В°РЎвЂљРЎРЉ РЎРѓРЎвЂљР В°РЎвЂљРЎС“РЎРѓ Р Р…Р В°Р В·Р В°Р Т‘')->send();
                            return;
                        }

                        $reason = null;
                        if ($newRank < $oldRank) {
                            $reason = trim((string)($data['downgrade_reason'] ?? ''));
                            if ($reason === '') {
                                Notification::make()->danger()->title('Р Р€Р С”Р В°Р В¶Р С‘РЎвЂљР Вµ Р С—РЎР‚Р С‘РЎвЂЎР С‘Р Р…РЎС“ Р С•РЎвЂљР С”Р В°РЎвЂљР В°')->send();
                                return;
                            }
                            $record->extra_reason = $reason;
                        }

                        $record->status = $to;
                        $record->touchStatusTime($to, now(), true);
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
                            ->title($newRank < $oldRank ? 'Р РЋРЎвЂљР В°РЎвЂљРЎС“РЎРѓ Р С•РЎвЂљР С”Р В°РЎвЂљР В°Р Р…' : 'Р РЋРЎвЂљР В°РЎвЂљРЎС“РЎРѓ Р С•Р В±Р Р…Р С•Р Р†Р В»РЎвЂР Р…')
                            ->send();
                    }),

                Action::make('refund_payparts')
                    ->label('Р СџР С•Р В»Р Р…РЎвЂ№Р в„– Р Р†Р С•Р В·Р Р†РЎР‚Р В°РЎвЂљ')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(function (Order $record): bool {
                        $user = auth('admin')->user();
                        $allowed = $user instanceof \App\Models\User
                            && ((method_exists($user, 'hasRole') && $user->hasRole(config('shield.super_admin.name', 'super_admin')))
                                || $user->can('refund_payparts_payment'));
                        $payment = $record->payment instanceof PaymentMethodEnum
                            ? $record->payment
                            : PaymentMethodEnum::tryFrom((int) $record->payment);

                        return $allowed
                            && $payment === PaymentMethodEnum::PAYPARTS
                            && in_array($record->payparts_status, ['payment_success', 'refund_failed'], true);
                    })
                    ->requiresConfirmation()
                    ->modalHeading(fn (Order $record): string => 'Р СџР С•Р В»Р Р…РЎвЂ№Р в„– Р Р†Р С•Р В·Р Р†РЎР‚Р В°РЎвЂљ Р С—Р С• Р В·Р В°Р С”Р В°Р В·РЎС“ РІвЂћвЂ“' . ($record->number ?: $record->id))
                    ->modalDescription(fn (Order $record): string => sprintf(
                        'Р вЂ™Р ВµРЎР‚Р Р…РЎС“РЎвЂљРЎРЉ %.2f Р С–РЎР‚Р Р… РЎвЂЎР ВµРЎР‚Р ВµР В· Р СџРЎР‚Р С‘Р Р†Р В°РЎвЂљР вЂР В°Р Р…Р С”? Р С›Р С—Р ВµРЎР‚Р В°РЎвЂ Р С‘РЎР‹ Р Р…Р ВµР В»РЎРЉР В·РЎРЏ Р В·Р В°Р С—РЎС“РЎРѓР С”Р В°РЎвЂљРЎРЉ Р С—Р С•Р Р†РЎвЂљР С•РЎР‚Р Р…Р С•, Р С—Р С•Р С”Р В° Р В±Р В°Р Р…Р С” Р С•Р В±РЎР‚Р В°Р В±Р В°РЎвЂљРЎвЂ№Р Р†Р В°Р ВµРЎвЂљ Р Р†Р С•Р В·Р Р†РЎР‚Р В°РЎвЂљ. Р вЂ™Р С•Р В·Р Р†РЎР‚Р В°РЎвЂљР Р…РЎвЂ№Р в„– РЎвЂЎР ВµР С” Cashalot Р С•РЎвЂћР С•РЎР‚Р СР В»РЎРЏР ВµРЎвЂљРЎРѓРЎРЏ Р С•РЎвЂљР Т‘Р ВµР В»РЎРЉР Р…Р С•.',
                        (float) $record->grand_total
                    ))
                    ->modalSubmitActionLabel('Р вЂ™РЎвЂ№Р С—Р С•Р В»Р Р…Р С‘РЎвЂљРЎРЉ Р Р†Р С•Р В·Р Р†РЎР‚Р В°РЎвЂљ')
                    ->action(function (Order $record): void {
                        $user = auth('admin')->user();

                        try {
                            $allowed = $user instanceof \App\Models\User
                                && ((method_exists($user, 'hasRole') && $user->hasRole(config('shield.super_admin.name', 'super_admin')))
                                    || $user->can('refund_payparts_payment'));
                            if (! $allowed) {
                                throw new \RuntimeException('Р СњР ВµР Т‘Р С•РЎРѓРЎвЂљР В°РЎвЂљР С•РЎвЂЎР Р…Р С• Р С—РЎР‚Р В°Р Р† Р Т‘Р В»РЎРЏ Р Р†Р С•Р В·Р Р†РЎР‚Р В°РЎвЂљР В° Р С—Р В»Р В°РЎвЂљР ВµР В¶Р В°.');
                            }

                            $refund = PrivatBankPaypartsRefundService::make()
                                ->initiateFullRefund($record, $user?->id);

                            activity('order')
                                ->performedOn($record)
                                ->causedBy($user)
                                ->event('payparts_refund_requested')
                                ->withProperties([
                                    'refund_id' => $refund->id,
                                    'amount' => $refund->amount,
                                    'status' => $refund->status,
                                ])
                                ->log('Р вЂ”Р В°Р С—РЎР‚Р С•РЎв‚¬Р ВµР Р… Р С—Р С•Р В»Р Р…РЎвЂ№Р в„– Р Р†Р С•Р В·Р Р†РЎР‚Р В°РЎвЂљ Р’В«Р С›Р С—Р В»Р В°РЎвЂљРЎвЂ№ РЎвЂЎР В°РЎРѓРЎвЂљРЎРЏР СР С‘Р’В»');

                            Notification::make()
                                ->title($refund->status === 'refunded'
                                    ? 'Р вЂ™Р С•Р В·Р Р†РЎР‚Р В°РЎвЂљ РЎС“РЎРѓР С—Р ВµРЎв‚¬Р Р…Р С• Р Р†РЎвЂ№Р С—Р С•Р В»Р Р…Р ВµР Р…'
                                    : 'Р вЂ™Р С•Р В·Р Р†РЎР‚Р В°РЎвЂљ Р С—РЎР‚Р С‘Р Р…РЎРЏРЎвЂљ Р С‘ Р С•Р В¶Р С‘Р Т‘Р В°Р ВµРЎвЂљ Р С—Р С•Р Т‘РЎвЂљР Р†Р ВµРЎР‚Р В¶Р Т‘Р ВµР Р…Р С‘РЎРЏ Р В±Р В°Р Р…Р С”Р В°')
                                ->{$refund->status === 'refunded' ? 'success' : 'warning'}()
                                ->send();
                        } catch (\Throwable $e) {
                            Log::error('Payparts refund failed', [
                                'order_id' => $record->id,
                                'user_id' => $user?->id,
                                'error' => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->danger()
                                ->title('Р вЂ™Р С•Р В·Р Р†РЎР‚Р В°РЎвЂљ Р Р…Р Вµ Р Р†РЎвЂ№Р С—Р С•Р В»Р Р…Р ВµР Р…')
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();
                        }
                    }),

                Action::make('cashalot_return')
                    ->label('Р С›РЎвЂљР СР ВµР Р…Р С‘РЎвЂљРЎРЉ РЎвЂћР С‘РЎРѓР С”Р В°Р В»РЎРЉР Р…РЎвЂ№Р в„– РЎвЂЎР ВµР С”')
                    ->icon('heroicon-o-receipt-refund')
                    ->color('warning')
                    ->visible(function (Order $record): bool {
                        if (static::class === \App\Filament\Resources\Callcenter\OrderResource::class) {
                            return false;
                        }

                        $user = auth('admin')->user();
                        $allowed = $user instanceof \App\Models\User
                            && ((method_exists($user, 'hasRole') && $user->hasRole(config('shield.super_admin.name', 'super_admin')))
                                || $user->can('refund_payparts_payment'));

                        $hasSaleCashalot = $record->cashalotLogs()
                            ->where('status', 'success')
                            ->where(function ($query) {
                                $query->whereNull('payment_type')
                                    ->orWhere('payment_type', '!=', 'Cashalot return');
                            })
                            ->exists();

                        $hasReturnCashalot = $record->cashalotLogs()
                            ->where('status', 'success')
                            ->where('payment_type', 'Cashalot return')
                            ->exists();

                        return $allowed && $hasSaleCashalot && ! $hasReturnCashalot;
                    })
                    ->requiresConfirmation()
                    ->modalHeading(fn (Order $record): string => 'Р С›РЎвЂљР СР ВµР Р…Р В° РЎвЂћР С‘РЎРѓР С”Р В°Р В»РЎРЉР Р…Р С•Р С–Р С• РЎвЂЎР ВµР С”Р В° Р С—Р С• Р В·Р В°Р С”Р В°Р В·РЎС“ РІвЂћвЂ“' . ($record->number ?: $record->id))
                    ->modalDescription(fn (Order $record): string => sprintf(
                        'Р С›РЎвЂљР СР ВµР Р…Р С‘РЎвЂљРЎРЉ РЎвЂћР С‘РЎРѓР С”Р В°Р В»РЎРЉР Р…РЎвЂ№Р в„– РЎвЂЎР ВµР С” Р Р…Р В° %.2f Р С–РЎР‚Р Р…? Р СџР С•РЎРѓР В»Р Вµ Р С—Р С•Р Т‘РЎвЂљР Р†Р ВµРЎР‚Р В¶Р Т‘Р ВµР Р…Р С‘РЎРЏ Р В±РЎС“Р Т‘Р ВµРЎвЂљ РЎРѓРЎвЂћР С•РЎР‚Р СР С‘РЎР‚Р С•Р Р†Р В°Р Р… РЎРѓРЎвЂљР С•РЎР‚Р Р…Р С•-РЎвЂЎР ВµР С” Cashalot. Р СџР С•Р Р†РЎвЂљР С•РЎР‚Р Р…Р С• Р В·Р В°Р С—РЎС“РЎРѓР С”Р В°РЎвЂљРЎРЉ Р С•Р С—Р ВµРЎР‚Р В°РЎвЂ Р С‘РЎР‹ Р Р…Р ВµР В»РЎРЉР В·РЎРЏ, Р С—Р С•Р С”Р В° Р Р†Р С•Р В·Р Р†РЎР‚Р В°РЎвЂљ Р Р…Р Вµ Р В·Р В°Р Р†Р ВµРЎР‚РЎв‚¬Р ВµР Р….',
                        (float) $record->grand_total
                    ))
                    ->modalSubmitActionLabel('Р С›РЎвЂљР СР ВµР Р…Р С‘РЎвЂљРЎРЉ РЎвЂЎР ВµР С”')
                    ->action(function (Order $record): void {
                        $user = auth('admin')->user();

                        try {
                            $allowed = $user instanceof \App\Models\User
                                && ((method_exists($user, 'hasRole') && $user->hasRole(config('shield.super_admin.name', 'super_admin')))
                                    || $user->can('refund_payparts_payment'));

                            if (! $allowed) {
                                throw new \RuntimeException('Р СњР ВµР Т‘Р С•РЎРѓРЎвЂљР В°РЎвЂљР С•РЎвЂЎР Р…Р С• Р С—РЎР‚Р В°Р Р† Р Т‘Р В»РЎРЏ Р С•РЎвЂљР СР ВµР Р…РЎвЂ№ РЎвЂћР С‘РЎРѓР С”Р В°Р В»РЎРЉР Р…Р С•Р С–Р С• РЎвЂЎР ВµР С”Р В°.');
                            }

                            $cashalotLog = $record->cashalotLogs()
                                ->where('status', 'success')
                                ->where(function ($query) {
                                    $query->whereNull('payment_type')
                                        ->orWhere('payment_type', '!=', 'Cashalot return');
                                })
                                ->latest('id')
                                ->first();

                            if (! $cashalotLog) {
                                throw new \RuntimeException('Р СњР Вµ Р Р…Р В°Р в„–Р Т‘Р ВµР Р… РЎС“РЎРѓР С—Р ВµРЎв‚¬Р Р…РЎвЂ№Р в„– РЎвЂћР С‘РЎРѓР С”Р В°Р В»РЎРЉР Р…РЎвЂ№Р в„– РЎвЂЎР ВµР С” Р Т‘Р В»РЎРЏ РЎРѓРЎвЂљР С•РЎР‚Р Р…Р С•.');
                            }

                            $returnLog = app(CashalotFiscalService::class)
                                ->fiscalizeReturnCheck($record, $cashalotLog, $user?->id);

                            activity('order')
                                ->performedOn($record)
                                ->causedBy($user)
                                ->event('cashalot_return_requested')
                                ->withProperties([
                                    'cashalot_log_id' => $cashalotLog->id,
                                    'return_log_id' => $returnLog?->id,
                                    'status' => $returnLog?->status,
                                ])
                                ->log('Р вЂ”Р В°Р С—РЎР‚Р С•РЎв‚¬Р ВµР Р…Р В° Р С•РЎвЂљР СР ВµР Р…Р В° РЎвЂћР С‘РЎРѓР С”Р В°Р В»РЎРЉР Р…Р С•Р С–Р С• РЎвЂЎР ВµР С”Р В°');

                            Notification::make()
                                ->title($returnLog?->status === 'success'
                                    ? 'Р В¤Р С‘РЎРѓР С”Р В°Р В»РЎРЉР Р…РЎвЂ№Р в„– РЎвЂЎР ВµР С” Р С•РЎвЂљР СР ВµР Р…Р ВµР Р…'
                                    : 'Р РЋРЎвЂљР С•РЎР‚Р Р…Р С• РЎвЂЎР ВµР С”Р В° Р С—РЎР‚Р С‘Р Р…РЎРЏРЎвЂљР С• Р С‘ Р С•Р В¶Р С‘Р Т‘Р В°Р ВµРЎвЂљ Р С•Р В±РЎР‚Р В°Р В±Р С•РЎвЂљР С”Р С‘')
                                ->{$returnLog?->status === 'success' ? 'success' : 'warning'}()
                                ->send();
                        } catch (\Throwable $e) {
                            Log::error('Cashalot return failed', [
                                'order_id' => $record->id,
                                'user_id' => $user?->id,
                                'error' => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->danger()
                                ->title('Р С›РЎвЂљР СР ВµР Р…Р В° РЎвЂћР С‘РЎРѓР С”Р В°Р В»РЎРЉР Р…Р С•Р С–Р С• РЎвЂЎР ВµР С”Р В° Р Р…Р Вµ Р Р†РЎвЂ№Р С—Р С•Р В»Р Р…Р ВµР Р…Р В°')
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();
                        }
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
                Tables\Grouping\Group::make('created_at')->label(__('order.filters.order_date'))->date()->collapsible()
                    // СЂСџвЂвЂЎ Р В¤Р С‘Р С”РЎРѓ: Р С—РЎР‚Р С‘Р Р…РЎС“Р Т‘Р С‘РЎвЂљР ВµР В»РЎРЉР Р…Р С• РЎРѓР С•РЎР‚РЎвЂљР С‘РЎР‚РЎС“Р ВµР С Р С—Р С• Р Т‘Р В°РЎвЂљР Вµ Р СњР С›Р вЂ™Р В«Р вЂў РІвЂ вЂ™ Р РЋР СћР С’Р В Р В«Р вЂў
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
            // ... РЎвЂљР Р†Р С•Р С‘ Р Т‘РЎР‚РЎС“Р С–Р С‘Р Вµ relation managers (Р Р…Р В°Р С—РЎР‚Р С‘Р СР ВµРЎР‚, ItemsRelationManager)
            \App\Filament\Resources\Shop\OrderResource\RelationManagers\ClientOrdersRelationManager::class,
        ];
    }

    /**
     * Р СџР С•Р В»РЎС“РЎвЂЎР В°Р ВµРЎвЂљ Р С”Р С•Р С•РЎР‚Р Т‘Р С‘Р Р…Р В°РЎвЂљРЎвЂ№ Р В°Р Т‘РЎР‚Р ВµРЎРѓР В° РЎвЂЎР ВµРЎР‚Р ВµР В· Google Places API (Р С—РЎР‚Р ВµР Т‘Р С—Р С•РЎвЂЎРЎвЂљР С‘РЎвЂљР ВµР В»РЎРЉР Р…Р С•) Р С‘Р В»Р С‘ Geocoding API
     *
     * Р вЂ™Р С’Р вЂ“Р СњР С›: Р вЂўРЎРѓР В»Р С‘ API Р С”Р В»РЎР‹РЎвЂЎ Р С‘Р СР ВµР ВµРЎвЂљ Р С•Р С–РЎР‚Р В°Р Р…Р С‘РЎвЂЎР ВµР Р…Р С‘РЎРЏ Р С—Р С• referer, РЎРѓР ВµРЎР‚Р Р†Р ВµРЎР‚Р Р…РЎвЂ№Р Вµ Р В·Р В°Р С—РЎР‚Р С•РЎРѓРЎвЂ№ Р Р…Р Вµ Р В±РЎС“Р Т‘РЎС“РЎвЂљ РЎР‚Р В°Р В±Р С•РЎвЂљР В°РЎвЂљРЎРЉ.
     * Р вЂ™ РЎРЊРЎвЂљР С•Р С РЎРѓР В»РЎС“РЎвЂЎР В°Р Вµ Р С”Р С•Р С•РЎР‚Р Т‘Р С‘Р Р…Р В°РЎвЂљРЎвЂ№ Р Т‘Р С•Р В»Р В¶Р Р…РЎвЂ№ Р В±РЎвЂ№РЎвЂљРЎРЉ Р С—Р С•Р В»РЎС“РЎвЂЎР ВµР Р…РЎвЂ№ РЎвЂЎР ВµРЎР‚Р ВµР В· Р С”Р В»Р С‘Р ВµР Р…РЎвЂљРЎРѓР С”Р С‘Р в„– JavaScript (Р С—Р С•Р В»Р Вµ "Р вЂ™РЎС“Р В»Р С‘РЎвЂ РЎРЏ (Р С™Р С‘РЎвЂ”Р Р†)").
     *
     * @param ClientAddress $address
     * @param string|null $placeId Place ID Р С‘Р В· РЎРѓР С•РЎвЂ¦РЎР‚Р В°Р Р…Р ВµР Р…Р Р…Р С•Р С–Р С• Р В°Р Т‘РЎР‚Р ВµРЎРѓР В° Р В·Р В°Р С”Р В°Р В·Р В° (Р С•Р С—РЎвЂ Р С‘Р С•Р Р…Р В°Р В»РЎРЉР Р…Р С•)
     * @return array|null ['latitude' => float, 'longitude' => float, 'formatted_address' => string]
     */
    protected static function getCoordinatesForAddress(ClientAddress $address, ?string $placeId = null): ?array
    {
        // Р вЂўРЎРѓР В»Р С‘ API Р С”Р В»РЎР‹РЎвЂЎ Р С‘Р СР ВµР ВµРЎвЂљ Р С•Р С–РЎР‚Р В°Р Р…Р С‘РЎвЂЎР ВµР Р…Р С‘РЎРЏ Р С—Р С• referer, РЎРѓР ВµРЎР‚Р Р†Р ВµРЎР‚Р Р…РЎвЂ№Р Вµ Р В·Р В°Р С—РЎР‚Р С•РЎРѓРЎвЂ№ Р Р…Р Вµ РЎР‚Р В°Р В±Р С•РЎвЂљР В°РЎР‹РЎвЂљ
        // Р вЂ™ РЎРЊРЎвЂљР С•Р С РЎРѓР В»РЎС“РЎвЂЎР В°Р Вµ Р С”Р С•Р С•РЎР‚Р Т‘Р С‘Р Р…Р В°РЎвЂљРЎвЂ№ Р Т‘Р С•Р В»Р В¶Р Р…РЎвЂ№ Р В±РЎвЂ№РЎвЂљРЎРЉ Р С—Р С•Р В»РЎС“РЎвЂЎР ВµР Р…РЎвЂ№ РЎвЂЎР ВµРЎР‚Р ВµР В· Р С”Р В»Р С‘Р ВµР Р…РЎвЂљРЎРѓР С”Р С‘Р в„– JavaScript
        // Р СџРЎР‚Р С•Р С—РЎС“РЎРѓР С”Р В°Р ВµР С Р С—Р С•Р С—РЎвЂ№РЎвЂљР С”РЎС“ Р С—Р С•Р В»РЎС“РЎвЂЎР С‘РЎвЂљРЎРЉ Р С”Р С•Р С•РЎР‚Р Т‘Р С‘Р Р…Р В°РЎвЂљРЎвЂ№ РЎвЂЎР ВµРЎР‚Р ВµР В· РЎРѓР ВµРЎР‚Р Р†Р ВµРЎР‚Р Р…РЎвЂ№Р в„– API
        Log::info('Skipping server-side coordinate lookup due to API key referer restrictions. Coordinates should be obtained via client-side JavaScript (Street field).');
        return null;

        /* Р вЂ”Р В°Р С”Р С•Р СР СР ВµР Р…РЎвЂљР С‘РЎР‚Р С•Р Р†Р В°Р Р…Р С•, РЎвЂљР В°Р С” Р С”Р В°Р С” API Р С”Р В»РЎР‹РЎвЂЎ РЎРѓ Р С•Р С–РЎР‚Р В°Р Р…Р С‘РЎвЂЎР ВµР Р…Р С‘РЎРЏР СР С‘ Р С—Р С• referer Р Р…Р Вµ РЎР‚Р В°Р В±Р С•РЎвЂљР В°Р ВµРЎвЂљ Р Т‘Р В»РЎРЏ РЎРѓР ВµРЎР‚Р Р†Р ВµРЎР‚Р Р…РЎвЂ№РЎвЂ¦ Р В·Р В°Р С—РЎР‚Р С•РЎРѓР С•Р Р†
        try {
            $key = config('services.google_maps.key');
            if (!$key) {
                Log::warning('Google Maps API key not configured for address geocoding');
                return null;
            }

            if ($placeId) {
                // Р ВРЎРѓР С—Р С•Р В»РЎРЉР В·РЎС“Р ВµР С Google Places API Р Т‘Р В»РЎРЏ Р С—Р С•Р В»РЎС“РЎвЂЎР ВµР Р…Р С‘РЎРЏ Р С”Р С•Р С•РЎР‚Р Т‘Р С‘Р Р…Р В°РЎвЂљ Р С—Р С• place_id
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
                    // Р СџРЎР‚Р С•Р Т‘Р С•Р В»Р В¶Р В°Р ВµР С РЎРѓ Geocoding API Р С”Р В°Р С” fallback
                }
            }

            // Fallback: Р С‘РЎРѓР С—Р С•Р В»РЎРЉР В·РЎС“Р ВµР С Google Places Autocomplete API Р Т‘Р В»РЎРЏ Р С—Р С•Р С‘РЎРѓР С”Р В° Р В°Р Т‘РЎР‚Р ВµРЎРѓР В°, Р В·Р В°РЎвЂљР ВµР С Places Details API
            // Р В­РЎвЂљР С• Р В±Р С•Р В»Р ВµР Вµ Р Р…Р В°Р Т‘Р ВµР В¶Р Р…РЎвЂ№Р в„– РЎРѓР С—Р С•РЎРѓР С•Р В±, РЎвЂљР В°Р С” Р С”Р В°Р С” Places API Р С•Р В±РЎвЂ№РЎвЂЎР Р…Р С• Р Р†Р С”Р В»РЎР‹РЎвЂЎР ВµР Р… Р С—Р С• РЎС“Р СР С•Р В»РЎвЂЎР В°Р Р…Р С‘РЎР‹
            $street = $address->street;
            $house = $address->house;
            $city = $address->city ?: 'Р С™Р С‘РЎвЂ”Р Р†';

            // Р вЂўРЎРѓР В»Р С‘ Р Р† street РЎС“Р В¶Р Вµ Р ВµРЎРѓРЎвЂљРЎРЉ Р С–Р С•РЎР‚Р С•Р Т‘ (Р Р…Р В°Р С—РЎР‚Р С‘Р СР ВµРЎР‚, "Р Р†РЎС“Р В». Р С™РЎС“РЎР‚Р С•РЎР‚РЎвЂљР Р…Р В°, Р вЂ™Р С•РЎР‚Р В·Р ВµР В»РЎРЉ Р С™Р С‘РЎвЂ”Р Р†РЎРѓРЎРЉР С”Р В° Р С•Р В±Р В»Р В°РЎРѓРЎвЂљРЎРЉ")
            // РЎС“Р В±Р С‘РЎР‚Р В°Р ВµР С Р ВµР С–Р С• Р С‘ Р С‘РЎРѓР С—Р С•Р В»РЎРЉР В·РЎС“Р ВµР С РЎвЂљР С•Р В»РЎРЉР С”Р С• РЎС“Р В»Р С‘РЎвЂ РЎС“ Р С‘ Р Т‘Р С•Р С
            if (str_contains($street, ',')) {
                $streetParts = explode(',', $street);
                $street = trim($streetParts[0]); // Р вЂР ВµРЎР‚Р ВµР С РЎвЂљР С•Р В»РЎРЉР С”Р С• Р С—Р ВµРЎР‚Р Р†РЎС“РЎР‹ РЎвЂЎР В°РЎРѓРЎвЂљРЎРЉ (РЎС“Р В»Р С‘РЎвЂ РЎС“)
            }

            // Р В¤Р С•РЎР‚Р СР С‘РЎР‚РЎС“Р ВµР С Р В°Р Т‘РЎР‚Р ВµРЎРѓ Р Т‘Р В»РЎРЏ Р С—Р С•Р С‘РЎРѓР С”Р В°: РЎС“Р В»Р С‘РЎвЂ Р В°, Р Т‘Р С•Р С, Р С–Р С•РЎР‚Р С•Р Т‘
            $addressParts = array_filter([
                $street,
                $house,
                $city,
            ]);
            $addressString = implode(', ', $addressParts);

            if (empty($addressString)) {
                return null;
            }

            // Р ВРЎРѓР С—Р С•Р В»РЎРЉР В·РЎС“Р ВµР С Google Places Autocomplete API Р Т‘Р В»РЎРЏ Р С—Р С•Р С‘РЎРѓР С”Р В° Р В°Р Т‘РЎР‚Р ВµРЎРѓР В°
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
                        'location' => '50.4501,30.5234', // Р В¦Р ВµР Р…РЎвЂљРЎР‚ Р С™Р С‘Р ВµР Р†Р В°
                        'radius' => 30000,
                        'sessiontoken' => $token,
                        'key' => $key,
                    ]
                );

                if ($autocompleteResponse->ok()) {
                    $autocompleteData = $autocompleteResponse->json();
                    if ($autocompleteData['status'] === 'OK' && !empty($autocompleteData['predictions'])) {
                        // Р вЂР ВµРЎР‚Р ВµР С Р С—Р ВµРЎР‚Р Р†РЎвЂ№Р в„– РЎР‚Р ВµР В·РЎС“Р В»РЎРЉРЎвЂљР В°РЎвЂљ
                        $prediction = $autocompleteData['predictions'][0];
                        $foundPlaceId = $prediction['place_id'] ?? null;

                        if ($foundPlaceId) {
                            // Р СџР С•Р В»РЎС“РЎвЂЎР В°Р ВµР С Р Т‘Р ВµРЎвЂљР В°Р В»Р С‘ Р СР ВµРЎРѓРЎвЂљР В° РЎвЂЎР ВµРЎР‚Р ВµР В· Places Details API
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

            // Р вЂўРЎРѓР В»Р С‘ Places API Р Р…Р Вµ РЎРѓРЎР‚Р В°Р В±Р С•РЎвЂљР В°Р В», Р Р†Р С•Р В·Р Р†РЎР‚Р В°РЎвЂ°Р В°Р ВµР С null
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

    protected static function formatOperatorTime(Order $record): string|HtmlString
    {
        $start = $record->statusTime(OrderStatus::New);
        $end = static::earliestTimeAfter($start, [
            $record->statusTime(OrderStatus::Processing),
            $record->statusTime(OrderStatus::OnHold),
            $record->statusTime(OrderStatus::Cancelled),
        ]);

        return static::formatDurationCell($start, $end, '#2563eb');
    }

    protected static function formatKitchenTime(Order $record): string|HtmlString
    {
        $start = $record->statusTime(OrderStatus::Processing);
        $end = static::earliestTimeAfter($start, [
            $record->statusTime(OrderStatus::Prepared),
            $record->statusTime(OrderStatus::Cancelled),
        ]);

        return static::formatDurationCell($start, $end, '#f97316');
    }

    protected static function formatDeliveryTime(Order $record): string|HtmlString
    {
        if ($record->self_pickup) {
            return 'РІР‚вЂќ';
        }

        $start = $record->statusTime(OrderStatus::Prepared);
        $end = static::earliestTimeAfter($start, [
            $record->statusTime(OrderStatus::Delivered),
            $record->statusTime(OrderStatus::Cancelled),
        ]);

        return static::formatDurationCell($start, $end, '#16a34a');
    }

    protected static function formatTotalTime(Order $record): string|HtmlString
    {
        $start = $record->statusTime(OrderStatus::New);
        $endCandidates = [
            $record->statusTime(OrderStatus::Delivered),
            $record->statusTime(OrderStatus::Cancelled),
        ];

        if ($record->self_pickup) {
            $endCandidates[] = $record->statusTime(OrderStatus::Prepared);
        }

        $end = static::earliestTimeAfter($start, $endCandidates);

        if (! $start || ! $end) {
            return 'РІР‚вЂќ';
        }

        $minutes = max(0, (int) floor($start->diffInSeconds($end) / 60));
        $hours = (int) floor($minutes / 60);
        $mins = $minutes % 60;

        $value = sprintf('%02d%s<br>%02d%s', $hours, __('order.time_units.hours_short'), $mins, __('order.time_units.minutes_short'));

        return new HtmlString('<span style="color:#6b7280;">' . $value . '</span>');
    }

    protected static function earliestTimeAfter(?Carbon $start, array $candidates): ?Carbon
    {
        $filtered = collect($candidates)
            ->filter(fn ($t) => $t instanceof Carbon)
            ->filter(fn (Carbon $t) => ! $start || $t->greaterThanOrEqualTo($start))
            ->sort();

        return $filtered->first();
    }

    protected static function formatDurationCell(?Carbon $start, ?Carbon $end, string $color): string|HtmlString
    {
        if (! $start || ! $end) {
            return 'РІР‚вЂќ';
        }

        $minutes = max(0, (int) floor($start->diffInSeconds($end) / 60));
        $endLabel = $end->format('d.m') . '<br>' . $end->format('H:i');
        $value = $endLabel . '<br>(' . $minutes . ' ' . __('order.time_units.minutes_short') . ')';

        return new HtmlString('<span style="color:' . e($color) . ';">' . $value . '</span>');
    }

    protected static function invoiceLabel(): string
    {
        return match (app()->getLocale()) {
            'ru' => 'Р РЋРЎвЂЎР ВµРЎвЂљ-РЎвЂћР В°Р С”РЎвЂљРЎС“РЎР‚Р В°',
            'uk' => 'Р В Р В°РЎвЂ¦РЎС“Р Р…Р С•Р С”-РЎвЂћР В°Р С”РЎвЂљРЎС“РЎР‚Р В°',
            default => 'Invoice',
        };
    }

    protected static function paymentOptionsAdmin(): array
    {
        $labels = PaymentMethodEnum::options();
        $labels[PaymentMethodEnum::INVOICE->value] = static::invoiceLabel();

        $order = [
            PaymentMethodEnum::LIQPAY,
            PaymentMethodEnum::PAYPARTS,
            PaymentMethodEnum::ORG_TRANSFER,
            PaymentMethodEnum::CARD,
            PaymentMethodEnum::POS,
            PaymentMethodEnum::CASH,
            PaymentMethodEnum::INVOICE,
            PaymentMethodEnum::FREE,
            // PaymentMethodEnum::CLUB,
        ];

        return collect($order)
            ->mapWithKeys(fn (PaymentMethodEnum $method) => [$method->value => $labels[$method->value] ?? $method->label()])
            ->toArray();
    }
    protected static function calcBaseTotalFromGet(Get $get): float
    {
        return static::calcItemsSubtotal((array) ($get('items') ?? []));
    }

    protected static function calcItemsSubtotal(array $items): float
    {
        return (float) collect($items)
            ->map(fn ($i) => is_object($i) ? (array) $i : (array) $i)
            ->sum(function (array $item) {
            $qty  = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['unit_price'] ?? 0);
            $mods  = collect($item['modifiers'] ?? [])
                ->map(fn ($m) => is_object($m) ? (array) $m : $m);
            $modsSum = (float) $mods->sum(fn ($m) => (float) ($m['price_modifier'] ?? 0));
            return $qty * ($price + $modsSum);
        });
    }

    protected static function calcDeliveryBaseFromGet(Get $get, ?Order $record = null): float
    {
        return static::calcDeliveryBaseFromStateArray([
            'items' => $get('items') ?? [],
            'discount_total' => $get('discount_total'),
            'sale_sum' => $get('sale_sum'),
            'ui_promo_preview_discount' => $get('ui_promo_preview_discount'),
        ], $record);
    }

    protected static function recalculateShippingFromCurrentForm(Get $get, Set $set, ?Order $record): void
    {
        $selfPickup = (bool) ($get('self_pickup') ?? false);
        if ($selfPickup) {
            $set('shipping_price', 0);
            $set('shipping_total', 0);
            return;
        }

        $address = (array) ($get('address') ?? []);
        $lat = $address['latitude'] ?? null;
        $lng = $address['longitude'] ?? null;

        if (! $lat || ! $lng) {
            return;
        }

        $deliveryService = app(\App\Services\DeliveryCalculationService::class);
        $orderTotal = static::calcDeliveryBaseFromGet($get, $record);

        $tempOrder = $record ? clone $record : new Order();
        $tempOrder->address = $address;
        $tempOrder->self_pickup = $selfPickup;

        $delivery = $deliveryService->calculateDelivery($tempOrder, $orderTotal);
        $calculatedPrice = (float) ($delivery['price'] ?? 0);

        $set('shipping_price', $calculatedPrice);
        $set('shipping_total', $calculatedPrice);
    }

    protected static function calcDeliveryBaseFromStateArray(array $state, ?Order $record = null): float
    {
        $baseTotal = static::calcItemsSubtotal((array) ($state['items'] ?? []));

        $discountTotal = 0.0;
        $bonuses = max(0, (float) ($state['sale_sum'] ?? 0));

        if ($record) {
            $record->refresh();
            $discountTotal = $record->resolveDiscountAmount();
            $bonuses = max($bonuses, $record->resolveSpentBonuses());
        }

        $stateDiscount = (float) ($state['discount_total'] ?? 0);
        if (abs($discountTotal) < 0.0001 && $stateDiscount < 0) {
            $discountTotal = $stateDiscount;
        }

        $previewDiscount = max(0, (float) ($state['ui_promo_preview_discount'] ?? 0));
        if (abs($discountTotal) < 0.0001 && $previewDiscount > 0) {
            $discountTotal = -1 * $previewDiscount;
        }

        return max(0, round($baseTotal + min(0, $discountTotal) - $bonuses, 2));
    }

    protected static function resolveSpentBonuses(?Order $order): float
    {
        return $order?->resolveSpentBonuses() ?? 0.0;
    }

    /**
     * @return array{final: float, bonuses: float}
     */
    protected static function resolveFinalAmount(?Order $record, float $baseTotal, float $deliveryPrice): array
    {
        $deliveryPrice = max(0, (float) $deliveryPrice);

        if (! $record) {
            return [
                'final' => max(0, $baseTotal + $deliveryPrice),
                'bonuses' => 0.0,
            ];
        }

        $record->refresh();

        $bonuses = static::resolveSpentBonuses($record);
        $final = $record->resolvePayableAmount($baseTotal, $deliveryPrice);

        return [
            'final' => max(0, (float) $final),
            'bonuses' => $bonuses,
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.shop');
    }

}

