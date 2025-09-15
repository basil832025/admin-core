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
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use App\Models\Shop\Client;
use App\Filament\Resources\ClientResource;


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
            ->components([
                Group::make()
                    ->schema([
                        Forms\Components\Tabs::make('order_tabs')
                            ->tabs([
                                Forms\Components\Tabs\Tab::make('Инфо по заказу')
                                    ->schema(static::getInfoTabSchema())
                                    ->columns(2),
                                Forms\Components\Tabs\Tab::make('Товары')
                                    ->schema(static::getProductsTabSchema()),
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
                ->label('Статус')
                ->inline()
                ->required()
                ->options(fn () => static::allowedStatuses())
                ->icons(OrderStatus::iconsMap())
                ->colors(OrderStatus::colorsMap())
                ->default(fn (?Order $r) => $r?->status?->value ?? OrderStatus::New->value)
                ->reactive(),

            Textarea::make('downgrade_reason')
                ->label('Причина отката')
                ->placeholder('Коротко опишите причину…')
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
            Section::make('Товары заказа')
                ->headerActions([
                    FormAction::make('Очистить')
                        ->modalHeading('Are you sure?')
                        ->modalDescription('All existing items will be removed from the order.')
                        ->requiresConfirmation()
                        ->color('danger')
                        ->action(fn (Set $set) => $set('items', [])),
                ])
                ->schema([
                    Placeholder::make('order_total')
                        ->label('Сума замовлення')
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
            Section::make('Сумма и скидки')
                ->schema([
                    Placeholder::make('order_total_right')
                        ->label('Сума замовлення')
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
                    Select::make('ui_fixed_discount_id')
                        ->label('Фіксована знижка')
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
                        }),

                    // 2) Знижки за часом (happy hours)
                    Select::make('ui_time_discount_id')
                        ->label('Знижка за часом')
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
                        ->label('Промокод')
                        ->placeholder('Введіть код')
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
                                ->tooltip('Скасувати промокод')
                                ->requiresConfirmation()
                                ->action(function (Set $set, ?Order $record) {
                                    if (! $record) return;

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
                                })
                        ]),

                    TextInput::make('ui_manual_percent')
                        ->label('Ручна знижка, %')
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
                        ->label('Ручна знижка, грн')
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
                        ->label('Застосовані знижки')
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
                    Placeholder::make('total_after_discount')
                        ->label('Разом зі знижкою')
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

                            $val = number_format($amount, 2, ',', ' ') . ' грн';
                            return new \Illuminate\Support\HtmlString('<div class="text-lg font-semibold">'.$val.'</div>');
                        })
                       /* ->content(function (?Order $record) {
                            if (! $record) return new HtmlString('—');
                            $record->refresh();
                            $val = number_format((float)$record->grand_total, 2, ',', ' ') . ' грн';
                            return new HtmlString('<div class="text-lg font-semibold">'.$val.'</div>');
                        }),*/
                ]),

            // ——— Статусы (инлайн блок остаётся на форме редактирования) ———
            Section::make('Статусы')
                ->reactive()
                ->schema([
                    Hidden::make('status')->default(fn (?Order $r) => $r?->status->value)->dehydrated(true),
                    Hidden::make('downgrade_pending')->default(false)->dehydrated(false),
                    Hidden::make('pending_status')->dehydrated(false),
                    Hidden::make('downgrade_reason')->dehydrated(false),

                    ToggleButtons::make('status_ui')
                        ->label('Статус')
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
                            ->label('Причина')
                            ->placeholder('Коротко опишите причину возврата статуса')
                            ->required()
                            ->rows(3)
                            ->dehydrated(false),

                        Actions::make([
                            FormAction::make('confirmDowngradeInline')
                                ->label('Подтвердить откат')
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

                                    activity('order')->performedOn($record)->causedBy(auth()->user())
                                        ->event('status_downgraded')->withProperties([
                                            'action' => 'status_downgraded',
                                            'from'   => $from,
                                            'to'     => $to,
                                            'reason' => $reason,
                                        ])->log('Статус возвращён назад');

                                    $set('status', $to);
                                    $set('status_ui', $to);
                                    $set('downgrade_pending', false);
                                    $set('pending_status', null);
                                    $set('downgrade_reason', null);
                                    $livewire->prevStatus = $to;

                                    Notification::make()->success()->title('Статус откатан')->send();
                                }),
                            FormAction::make('cancelDowngradeInline')
                                ->label('Отмена')
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

            Section::make('Служебная информация')
                ->schema([
                    Placeholder::make('created_at')
                        ->label('Создан')
                        ->content(fn (Order $record): ?string => $record->created_at?->diffForHumans()),
                    Placeholder::make('updated_at')
                        ->label('Изменён')
                        ->content(fn (Order $record): ?string => $record->updated_at?->diffForHumans()),
                ]),
        ];
    }

  /*  public static function clientCreateForm(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('name')->label('Имя')->required()->maxLength(255),
                Grid::make(['default' => 1, 'lg' => 12])->schema([
                    TextInput::make('phone')
                        ->label('Телефон')
                        ->required()
                        ->tel()
                        ->columnSpan(['lg' => 8])
                        // Маска только для UA (цифра в маске — 9)
                        ->mask(fn (Get $get) => $get('is_foreign_phone') ? null : '(999) 999-99-99')
                        ->placeholder(fn (Get $get) => $get('is_foreign_phone')
                            ? 'Напр.: 491512345678 (лише цифри, 6–15)'
                            : '(067) 123-45-67')
                        ->extraAttributes(fn (Get $get) => [
                            'inputmode'    => 'numeric',
                            'autocomplete' => 'tel',
                            'pattern'      => $get('is_foreign_phone')
                                ? '\+?\d{6,15}'
                                : '\(0\d{2}\)\s\d{3}-\d{2}-\d{2}',
                        ])
                        // Авто-детект “иностранного” номера при редактировании
                        ->afterStateHydrated(function (TextInput $component, $state, Get $get, Set $set) {
                            $d = preg_replace('/\D+/', '', (string) $state);
                            if ($d === '') return;

                            // Если не 0XXXXXXXXX — считаем иностранным и включаем тумблер
                            if (! preg_match('/^0\d{9}$/', $d)) {
                                $set('is_foreign_phone', true);          // тумблер “оживит” маску из-за ->live() ниже
                                $component->state(substr($d, 0, 15));     // отобразим просто цифры
                                return;
                            }

                            // Украина — красиво форматируем
                            if (str_starts_with($d, '380'))      $d = '0' . substr($d, 3);
                            elseif (str_starts_with($d, '80'))   $d = '0' . substr($d, 2);
                            elseif (strlen($d) === 9)            $d = '0' . $d;

                            $d = substr($d, 0, 10);
                            if (preg_match('/^(0\d{2})(\d{3})(\d{2})(\d{2})$/', $d, $m)) {
                                $component->state(sprintf('(%s) %s-%s-%s', $m[1], $m[2], $m[3], $m[4]));
                            }
                        })
                        // В БД — только цифры (UA: 10; Intl: до 15)
                        ->dehydrateStateUsing(function ($state, Get $get) {
                            $d = preg_replace('/\D+/', '', (string) $state);

                            if ($get('is_foreign_phone')) {
                                return substr($d, 0, 15);
                            }

                            if (str_starts_with($d, '380'))      $d = '0' . substr($d, 3);
                            elseif (str_starts_with($d, '80'))   $d = '0' . substr($d, 2);
                            elseif (strlen($d) === 9)            $d = '0' . $d;

                            return substr($d, 0, 10);
                        })
                        // Валидация по режиму
                        ->rule(fn (Get $get) => $get('is_foreign_phone')
                            ? 'regex:/^\+?\d{6,15}$/'
                            : 'regex:/^\(0\d{2}\)\s\d{3}-\d{2}-\d{2}$|^0\d{9}$/')
                        ->validationAttribute('телефон'),

                    Toggle::make('is_foreign_phone')
                        ->label('Телефон іншої країни')
                        ->helperText('Увімкніть, якщо номер не український')
                        ->inline(true)
                        ->live()                 // ← это ключ: заставит пересчитаться маска/placeholder у TextInput
                        ->dehydrated(false)
                        ->columnSpan(['lg' => 4])
                        ->extraAttributes(['class' => 'lg:mt-6']),
                ]),
                Select::make('gender')->label('Пол')->options(['male' => 'Мужчина','female' => 'Женщина'])->nullable(),
                TextInput::make('email')->label('Email')->email()->unique(\App\Models\Shop\Client::class, 'email'),
                Toggle::make('is_active')->label('Активный')->default(true),
            ]),
            Textarea::make('note')->label('Примечание')->columnSpanFull(),
        ];
    }*/

    public static function getInfoTabSchema(): array
    {
        return [
            Grid::make(12)->schema([

                // 1) Номер заказа — компактное поле
                TextInput::make('number')
                    ->label('Номер заказа')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder(fn (Order $r) => $r?->exists ? $r->number : 'Будет присвоен после сохранения')
                    ->columnSpan(3),

                // 2) Клиент — с create/edit в модалках из ClientResource
                Select::make('clients_id')
                    ->relationship('clients', 'name')
                    ->searchable()
                    ->label('Клиент')
                    ->required()
                    ->live()
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
                    ->label('Телефон')
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

            Section::make('Время и оплата')
                ->schema([
                    Grid::make(12)->schema([
                        DatePicker::make('dat')
                            ->label('Дата создания')
                            ->default(fn (?Order $record) => $record?->exists ? null : now())
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (! $get('date_order')) {
                                    $set('date_order', $state);
                                }
                            })
                            ->columnSpan(3),

                        TimePicker::make('time_start')
                            ->label('Время создания')
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
                            ->label('Время заказа')
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
                            ->label('Дата заказа')
                            ->default(now())
                            ->columnSpan(3),
                    ]),

                    Grid::make(12)->schema([
                        Toggle::make('as_soon_possible')
                            ->label('Как можно скорее')
                            ->inline(false)
                            ->live()
                            ->columnSpan(3),

                        Toggle::make('self_pickup')
                            ->label('Самовывоз')
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

                                $set(
                                    'time_order',
                                    Carbon::now()->addMinutes($add)->format('H:i')
                                );
                            })

                            ->columnSpan(3),

                        Select::make('payment')
                            ->label('Способ оплаты')
                            ->options([
                                1 => 'Наличкой',
                                2 => 'Безналичная',
                                3 => 'Клубная карта (кредит/депозит)',
                                4 => 'Кредитная карта',
                                5 => 'Без оплаты',
                            ])
                            ->default(1)
                            ->live()
                            ->reactive()
                            ->columnSpan(4),

                        TextInput::make('reason_non_payment')
                            ->label('Причина неоплаты')
                            ->placeholder('Коротко…')
                            ->visible(fn (Get $get) => (int) $get('payment') === 5)
                            ->maxLength(255)
                            ->columnSpan(12),
                    ]),
                ]),

            Select::make('selected_address_id')
                ->label('Адрес доставки')
                ->placeholder('Выберите адрес')
                ->default('')
                ->live()
                ->hidden(fn (Get $get) => (bool) $get('self_pickup'))
                ->afterStateHydrated(function (Select $component, ?Order $record, callable $set) {
                    if ($record && $record->client_address_id) {
                        $component->state((string) $record->client_address_id);
                        $address = ClientAddress::find($record->client_address_id);
                        if ($address) {
                            $set('address', $address->only([
                                'street','house','apartment','intercom','floor','entrance','zip','city','country','note','type','is_private_house',
                            ]));
                        }
                    }
                })
                ->afterStateUpdated(function ($state, callable $set) {
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
                    } else {
                        $address = ClientAddress::find($state);
                        if ($address) {
                            $set('address', $address->only([
                                'street','house','apartment','intercom','floor','entrance','zip','city','country','note','type','is_private_house',
                            ]));
                        }
                    }
                })
                ->options(function (callable $get) {
                    $clientId = $get('clients_id');
                    if (! $clientId) return [];

                    $addresses = ClientAddress::query()->where('client_id', $clientId)->get();
                    $final = collect(['-1' => 'Новый адрес'])->union(
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
                ->label('Валюта')
                ->options(Currency::pluck('name', 'code'))
                ->default('UAH')
                ->required(),

            MarkdownEditor::make('notes')->label('Примечание')->columnSpan('full'),
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
        $u = auth()->user();
        if (! $u) return false;
        return $u->can('set_order_status_' . $name);
    }

    public static function canDowngrade(): bool
    {
        return auth()->user()?->can('order_status_downgrade') ?? false;
    }

    protected static function allowedStatuses(): array
    {
        return collect(OrderStatus::sorted())
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
                Grid::make(12)->schema([
                    Select::make('product_id')
                        ->label('Продкут/товар')
                        ->options(function () use ($defaultLocale) {
                            return Product::query()
                                ->where('in_stock', 1)
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
                                ->toArray();
                        })
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                            $set('unit_price', Product::find($state)?->price ?? 0);
                            $mods = $get('modifiers') ?? [];
                            foreach ($mods as &$m) {
                                $m['_product_id']    = $state;
                                $m['value_id']       = $m['value_id'] ?? null;
                                $m['price_modifier'] = $m['price_modifier'] ?? 0;
                            }
                            $set('modifiers', $mods);
                            $set('order_total', now());
                        })
                        ->distinct()
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                        ->columnSpan(6)
                        ->searchable(),

                    TextInput::make('qty')
                        ->label('Количество')
                        ->numeric()
                        ->live(debounce: 250)
                        ->afterStateUpdated(fn ($state, callable $get, callable $set) => $set('order_total', now()))
                        ->default(1)
                        ->columnSpan(2)
                        ->required(),

                    TextInput::make('unit_price')
                        ->label('Цена')
                        ->dehydrated()
                        ->numeric()
                        ->live(debounce:500)
                        ->afterStateUpdated(fn ($state, callable $get, callable $set) => $set('order_total', now()))
                        ->required()
                        ->columnSpan(2),

                    Placeholder::make('item_total')
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
                                ->label('Характеристика / значение')
                                ->placeholder('Обрати варіант')
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
                                ->label('Цена +')
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
            ->columns([
                TextColumn::make('number')
                    ->label('Номер заказа')
                    ->searchable()
                    ->sortable(),
                  //  ->extraAttributes(['class' => 'cursor-pointer underline']),
                  //  ->action('statuses'), // клик по номеру откроет модалку статусов

                TextColumn::make('clients.name')->searchable()->label('Клиент')->sortable()->toggleable(),
                TextColumn::make('clients.phone')->searchable()->label('Телефон')->sortable()->toggleable()
                    ->copyable()
                    ->copyMessage('Телефон клиента скопирован')
                    ->copyMessageDuration(1500),

                TextColumn::make('status')->label('Статус')->badge(),

                TextColumn::make('total_price')
                    ->label('Сумма')
                    ->searchable()
                    ->sortable()
                    ->summarize([Sum::make()->money('UAH')]),

                TextColumn::make('discount_total')
                    ->label('Скидка')
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
                    ->label('Сумма со скидкой')
                    ->searchable()
                    ->sortable()
                    ->summarize([Sum::make()->money('UAH')]),

                TextColumn::make('date_order')->label('Дата доставки')->date()->toggleable(),
                TextColumn::make('time_order')->label('Время доставки')->time('H:i')->toggleable(),
                TextColumn::make('created_at')->label('Дата заказа')->date()->toggleable(),
            ])
            ->filters([
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
                    ->label('Статусы')
                    ->icon('heroicon-m-adjustments-horizontal')
                    ->color('gray')
                    ->modalHeading(fn (Order $r) => "Статусы: {$r->number}")
                    ->modalWidth('lg')
                    ->form(fn (Order $record) => static::statusModalForm())
                    ->action(function (array $data, Order $record) {
                        $user = auth()->user();

                        $from = $record->status;
                        $to   = OrderStatus::from($data['status_ui']);

                        if ($to->value === $from->value) {
                            Notification::make()->title('Статус не изменился')->info()->send();
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
                            ])->log($newRank < $oldRank ? 'Статус возвращён назад' : 'Статус изменён');

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
                Tables\Grouping\Group::make('created_at')->label('Дата заказа')->date()->collapsible(),
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
        return [ /* ... */ ];
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
