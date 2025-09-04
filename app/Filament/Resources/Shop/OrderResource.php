<?php

// ====== app/Filament/Resources/Shop/OrderResource.php ======

namespace App\Filament\Resources\Shop;

use App\Enums\OrderStatus;
use App\Filament\Clusters\Products\Resources\ProductResource;
use App\Filament\Resources\Shop\OrderResource\Pages;
use App\Filament\Resources\Shop\OrderResource\RelationManagers;
use App\Filament\Resources\Shop\OrderResource\Widgets\OrderStats;
use App\Forms\Components\AddressForm;
use App\Models\Setting;
use App\Models\Shop\CharacteristicValue;
use App\Models\Shop\ClientAddress;
use App\Models\Shop\Order;
use App\Models\Shop\Product;
use App\Models\Shop\ProductCategory;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
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
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use App\Models\Shop\VariationValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Currency;
use Illuminate\Support\Carbon;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Filament\Forms\Components\View;
use App\Models\Shop\PromoCode;     // таблица промокодов
use App\Models\Shop\OrderAdjustment;
use App\Models\Shop\FixedDiscount;
use App\Models\Shop\TimeDiscount;
use Illuminate\Support\HtmlString;



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
                        Tabs::make('order_tabs')
                            ->tabs([
                                Tab::make('Инфо по заказу')
                                    ->schema(static::getInfoTabSchema())
                                    ->columns(2),
                                Tab::make('Товары')
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

    public static function getProductsTabSchema(): array
    {
        return [
            Section::make('Товары заказа')
                ->headerActions([
                    Action::make('Очистить')
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
            Section::make('Сумма и скидки')
                ->schema([
                    // Дублируем сумму заказа (как слева)
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
                    // 1) Фіксована знижка (ручні правила)
                    Select::make('ui_fixed_discount_id')
                        ->label('Фіксована знижка')
                        ->dehydrated(false)
                        ->searchable()
                        ->nullable()          // ← позволит ставить null
                      //  ->clearable()         // ← крестик для ручной очистки
                        ->options(fn () => FixedDiscount::active()->pluck('name', 'id'))
                        // показываем выбранную ранее скидку, если есть
                        // ставим значение при гидратации формы (работает и в Edit)
                        ->afterStateHydrated(function (Set $set, ?Order $record) {
                            if (! $record) return;

                            // берём применённую фикс-скидку (если есть) и подставляем её id
                            $adj = $record->adjustments()
                                ->where('type', 'fixed')
                                ->first();

                            $set('ui_fixed_discount_id', $adj?->meta['id'] ?? null);
                        })
                        ->reactive()
                        ->afterStateUpdated(function ($state, Set $set, ?Order $record) {
                            if (! $record) return;

                            app(\App\Services\OrderPricing::class)
                                ->applyFixedExclusive($record, $state ? (int)$state : null, policy: 'single'); // или 'max'
                            // в UI гасим конкурентов
                            if ($state) {
                                $set('ui_time_discount_id', null);
                                $set('ui_manual_percent', null); // визуально очистить %
                            }

                            app(\App\Services\OrderPricing::class)->recalc($record);
                            // форсим перерисовку плейсхолдеров
                            $set('ui_version', microtime(true));
                        }),

                    // 2) Знижки за часом (happy hours)
                    Select::make('ui_time_discount_id')
                        ->label('Знижка за часом')
                        ->searchable()
                        ->nullable()          // важно: чтобы $set(..., null) сработал

                        ->dehydrated(false)
                        ->reactive()

                        // ПОДСТАВИТЬ ранее выбранную при редактировании
                        ->afterStateHydrated(function (Select $component, ?Order $record) {
                            if (! $record) return;

                            if (! $record->relationLoaded('adjustments')) {
                                $record->load('adjustments');
                            }

                            $adj = $record->adjustments->firstWhere('type', 'time');
                            $id  = $adj ? (data_get($adj->meta, 'id') ?? data_get($adj->meta, 'time_discount_id')) : null;

                            if ($id) {
                                // если сейчас она не попадает в options — добавим вручную, чтобы отобразить
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

                        // СФОРМИРОВАТЬ options по НУЖНОМУ моменту
                        ->options(function (Get $get) {
                            // если скидка зависит от времени выполнения — берём delivery_at,
                            // иначе — ordered_at; если в форме поля разбиты, собираем строку
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

                        // ПРИ ВЫБОРЕ: применяем 'single' + чистим конкурентов
                        ->afterStateUpdated(function ($state, Set $set, Get $get, ?Order $record) {
                            if (! $record) return;

                            // вычисляем ТOТ ЖЕ момент, что и в options()
                            $discountType = TimeDiscount::find($state)?->time_type ?? ($get('time_type') ?? 'order');
                            $momentStr = $discountType === 'execution'
                                ? trim(($get('delivery_date') ?? '') . ' ' . ($get('delivery_time') ?? ''))
                                : trim(($get('ordered_date')  ?? '') . ' ' . ($get('ordered_time')  ?? ''));

                            $moment = $momentStr ? Carbon::parse($momentStr) : now();

                            // применяем time с политикой single (сносит fixed + manual_percent)
                            app(\App\Services\OrderPricing::class)->applyTimeExclusive(
                                $record,
                                $state ? (int) $state : null,
                                'single',
                                $moment
                            );

                            // визуально чистим конкурентов
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
                            Action::make('applyPromo')
                                ->icon('heroicon-m-check')
                                ->action(function (Get $get, Set $set, ?\App\Models\Shop\Order $record) {
                                    if (! $record) return;

                                    $code = trim((string) $get('ui_promo_code'));
                                    if ($code === '') return;

                                    $ok = app(\App\Services\OrderPricing::class)->applyPromo($record, $code);
                                  //  dd($ok);
                                    if ($ok) {
                                        $set('ui_promo_code', $code); // оставить введённый код
                                        \Filament\Notifications\Notification::make()
                                            ->title('Промокод застосовано')->success()->send();
                                    } else {
                                        \Filament\Notifications\Notification::make()
                                            ->title('Промокод не дійсний')->danger()->send();
                                    }
                                }),

                            // отдельный экшен для удаления промокода
                            Action::make('clearPromo')
                                ->icon('heroicon-m-x-mark')
                                ->tooltip('Скасувати промокод')
                                ->requiresConfirmation() // опционально
                                ->action(function (Set $set, ?Order $record) {
                                    if (! $record) return;

                                    DB::transaction(function () use ($record, $set) {
                                        // найдём текущий купон в заказе
                                        $adj = $record->adjustments()
                                            ->where('type', 'coupon')
                                            ->first();

                                        if ($adj) {
                                            // 1) снять usage (идемпотентно)
                                            $promoId = $adj->promo_code_id
                                                ?? ($adj->meta['promo_id'] ?? null);

                                            $promo = $promoId
                                                ? PromoCode::find($promoId)
                                                : (isset($adj->meta['code'])
                                                    ? PromoCode::where('code', $adj->meta['code'])->first()
                                                    : null);

                                            // удалим usage по этому заказу
                                            $promo?->unmarkUsed($record->id);

                                            // 2) удалить корректировку
                                            $adj->delete();
                                        }

                                        // 3) пересчитать заказ
                                        app(\App\Services\OrderPricing::class)->recalc($record);

                                        // 4) очистить поле в форме
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

                            // чтобы не дергать БД дважды
                            if (! $record->relationLoaded('adjustments')) {
                                $record->load('adjustments');
                            }

                            $adj = $record->adjustments->firstWhere('type', 'manual_percent');
                            if ($adj) {
                                $val = (float) data_get($adj->meta, 'percent', 0);
                                $component->state($val);   // покажем сохранённый %
                            }
                        })
                        ->afterStateUpdated(function ($state, Set $set, Get $get, ?Order $record) {
                            if (! $record) return;

                            $val = (float) $state;

                            if ($val > 0) {
                                // визуально и логически снимаем конкурентов
                                $set('ui_fixed_discount_id', null);
                                $set('ui_time_discount_id', null);
                            }

                            // применяем эксклюзивную ручную %
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

                            // сумма может храниться в amount или в meta.amount — берём что найдём
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
                    // список применённых корректировок
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
                        // ->content(fn (?Order $order) => view('admin.orders._adjustments_list', compact('order')))
                        ->dehydrated(false),
                    // Итог со скидкой (только отображение)
                    Placeholder::make('total_after_discount')
                        ->label('Разом зі знижкою')
                        ->dehydrated(false)
                        ->reactive()
                        ->content(function (?Order $record) {
                            if (! $record) return new HtmlString('—');
                            $record->refresh(); // подтянуть свежие агрегаты после recalc()
                            $val = number_format((float)$record->grand_total, 2, ',', ' ') . ' грн';
                            return new HtmlString('<div class="text-lg font-semibold">'.$val.'</div>');
                        })
                ]),
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
                            Action::make('confirmDowngradeInline')
                                ->label('Подтвердить откат')
                                ->color('danger')
                                ->icon('heroicon-m-arrow-uturn-left')
                                ->action(function (callable $get, callable $set, $livewire, Order $record) {
                                    $to     = $get('pending_status');
                                    $reason = (string) $get('downgrade_reason');
                                    if (! $to) return;

                                    if (! \App\Filament\Resources\Shop\OrderResource::canSetStatus($to)
                                        || ! \App\Filament\Resources\Shop\OrderResource::canDowngrade()) {
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
                            Action::make('cancelDowngradeInline')
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

    public static function clientCreateForm(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('name')->label('Имя')->required()->maxLength(255),
                TextInput::make('phone')->label('Телефон')->tel()->required(),
                Select::make('gender')->label('Пол')->options(['male' => 'Мужчина','female' => 'Женщина'])->nullable(),
                TextInput::make('email')->label('Email')->email()->unique(\App\Models\Shop\Client::class, 'email'),
                Toggle::make('is_active')->label('Активный')->default(true),
            ]),
            Textarea::make('note')->label('Примечание')->columnSpanFull(),
        ];
    }

    public static function getInfoTabSchema(): array
    {
        return [
            TextInput::make('number')
                ->label('Номер заказа')
                ->disabled()
                ->dehydrated(false)    // <-- важно
                ->placeholder(fn (?Order $r) => $r?->exists ? $r->number : 'Будет присвоен после сохранения'),


        Select::make('clients_id')
                ->relationship('clients', 'name')
                ->searchable()
                ->label('Клиент')
                ->required()
                ->createOptionForm(static::clientCreateForm())
                ->createOptionUsing(function (array $data) {
                    $client = \App\Models\Shop\Client::create($data);
                    return $client->getKey();
                })
                ->createOptionAction(function (Action $action) {
                    return $action->modalHeading('Создание клиента')->modalSubmitActionLabel('Создать клиента')->modalWidth('lg');
                }),

            Hidden::make('client_address_id')->dehydrated(true),

            // ——— Время / дата / оплата ———
            Section::make('Время и оплата')
                ->schema([
                    Grid::make(12)->schema([
                        DatePicker::make('dat')
                            ->label('Дата создания')
                            ->default(fn (?Order $record) => $record?->exists ? null : now()) // только на create
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (! $get('date_order')) {
                                    $set('date_order', $state);
                                }
                            })
                        /*    ->afterStateHydrated(function ($state, Set $set, Get $get) {
                                if (! $get('date_order') && $state) {
                                    $set('date_order', $state);
                                }
                            })*/
                            ->columnSpan(3),

                        TimePicker::make('time_start')
                            ->label('Время создания')
                            ->seconds(false)
                            ->default(fn (?Order $record) => $record?->exists ? null : Carbon::now()->format('H:i'))
                            ->live()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (! $state) return;
                                $add = $get('self_pickup') ? 15 : 60;
                                $set('time_order', Carbon::parse($state)->addMinutes($add)->format('H:i'));
                            })
                            ->columnSpan(3),

                        TimePicker::make('time_order')
                            ->label('Время заказа')
                            ->seconds(false)
                           // ->hint('Авто: +15 самовывоз, +60 доставка — можно изменить')
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
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $start = $get('time_start');
                                if (! $start) return;
                                $add = $state ? 15 : 60;
                                $set('time_order', Carbon::parse($start)->addMinutes($add)->format('H:i'));
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

        if (! $u) {
            return false;
        }

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
                Grid::make(12)
                    ->schema([
                        Select::make('product_id')
                            ->label('Продкут/товар')
                            ->options(function () use ($defaultLocale) {
                                // Берём только нужные поля, чтобы не тянуть всё подряд
                                return Product::query()
                                    ->where('in_stock', 1)
                                    ->get(['id', 'title', 'short_name'])
                                    ->mapWithKeys(function ($p) use ($defaultLocale) {
                                        // Универсальный геттер перевода для JSON/строки
                                        $getTrans = function ($raw, $fallback = null) use ($defaultLocale) {
                                            if (blank($raw)) return $fallback;
                                            // raw может быть строкой JSON или plain-строкой
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
                                                return $raw; // обычная строка
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
                          /*  ->options(function () use ($defaultLocale) {
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
                            })*/
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $set('unit_price', \App\Models\Shop\Product::find($state)?->price ?? 0);
                                $mods = $get('modifiers') ?? [];
                                foreach ($mods as &$m) {
                                    $m['_product_id']    = $state;
                                    $m['value_id']       = $m['value_id'] ?? null;
                                    $m['price_modifier'] = $m['price_modifier'] ?? 0;
                                }
                                $set('modifiers', $mods);
                                $set('order_total', now());
                            })
                          /*  ->afterStateUpdated(function ($state,Set $set, Get $get) {
                                $mods = $get('modifiers') ?? [];
                                foreach ($mods as &$m) {
                                    $m['_product_id']    = $state;
                                    $m['value_id']       = $m['value_id'] ?? null;
                                    $m['price_modifier'] = $m['price_modifier'] ?? 0;
                                }
                                $set('modifiers', $mods);
                            })*/
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
                                ->default(fn (\Filament\Forms\Get $get) => $get('../../product_id'))
                                ->afterStateHydrated(function ($state, \Filament\Forms\Set $set, \Filament\Forms\Get $get) {
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
                Action::make('addModifier')
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
                Action::make('openProduct')
                    ->tooltip('Open product')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(function (array $arguments, Repeater $component): ?string {
                        $itemData = $component->getRawItemState($arguments['item']);
                        $product = Product::find($itemData['product_id']);
                        if (! $product) return null;
                        return ProductResource::getUrl('edit', ['record' => $product]);
                    }, shouldOpenInNewTab: true)
                    ->hidden(fn (array $arguments, Repeater $component): bool => blank($component->getRawItemState($arguments['item'])['product_id'])),
            ])
            ->orderColumn('sort')
            ->defaultItems(1)
            ->hiddenLabel()
            ->columns(['md' => 10])
            ->required();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('Номер заказа')->searchable()->sortable(),
                TextColumn::make('clients.name')->searchable()->label('Клиент')->sortable()->toggleable(),
                TextColumn::make('status')->label('Статус')->badge(),
             /*   TextColumn::make('currency')
                    ->label('Валюта')
                    ->getStateUsing(function ($record){
                        $string = \App\Models\Currency::where('code', $record->currency)->value('name');
                        return $string;
                    })
                    ->searchable()->sortable()->toggleable(),*/
                TextColumn::make('total_price')->label('Сумма')->searchable()->sortable()->summarize([Sum::make()->money('UAH')]),
                TextColumn::make('discount_total')
                    ->label('Скидка')
                    ->formatStateUsing(fn ($state) => // <-- имя параметра должно быть $state
                    ($state ?? 0) != 0
                        ? number_format(((float) $state), 2, ',', ' ') . ' грн'
                        : '—'
                    )
                    ->badge()
                    ->color(fn ($state) => abs((float) ($state ?? 0)) > 0 ? 'success' : 'gray')
                    ->alignRight()
                    ->toggleable()->summarize([Sum::make()->money('UAH')]),
                TextColumn::make('grand_total')->label('Сумма со скидкой')->searchable()->sortable()
                    ->summarize([Sum::make()->money('UAH')]),
              //  TextColumn::make('shipping_price')->label('Сумма доставки')->searchable()->sortable()->toggleable()->summarize([Sum::make()->money()]),
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
            ->Actions([ EditAction::make() ])
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
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
        $modelClass = static::$model;
        return (string) $modelClass::where('status', 'new')->count();
    }

    public static function getRelations(): array
    { return [ /* ... */ ]; }

    public static function getWidgets(): array
    { return [ OrderStats::class ]; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}

