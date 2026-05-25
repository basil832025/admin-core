<?php

namespace App\Filament\Resources\Logistics;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethodEnum;
use App\Filament\Resources\Callcenter\OrderResource as CallcenterOrderResource;
use App\Filament\Resources\Logistics\OrderResource\Pages;
use App\Models\Callcenter\Order;
use App\Models\Location;
use App\Models\Shop\Order as ShopOrder;
use App\Services\DeliveryCalculationService;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class OrderResource extends CallcenterOrderResource
{
    protected static ?string $model = Order::class;
    protected static ?string $slug = 'logistics/orders';
    protected static ?string $navigationGroup = null;
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationIcon = 'heroicon-o-truck';

    public static function getNavigationLabel(): string
    {
        return __('logistics.nav.navigation_label');
    }

    protected static function canAccessModule(): bool
    {
        $user = auth('admin')->user();

        if (! $user instanceof \App\Models\User) {
            return false;
        }

        $permissions = [
            'access_logistics_orders',
            'view_any_logistics::order',
            'view_logistics::order',
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
        return false;
    }

    public static function canViewAny(): bool
    {
        return static::canAccessModule();
    }

    public static function getNavigationBadge(): ?string
    {
        $modelClass = static::$model;

        return (string) $modelClass::where('status', OrderStatus::Prepared->value)->count();
    }

    public static function getModelLabel(): string
    {
        return __('logistics.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('logistics.nav.plural_model_label');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['clients.group', 'clientAddress', 'lastLiqpayLog', 'items.product'])
            )
            ->recordUrl(null)
            ->columns([
                TextColumn::make('number')
                    ->label('')
                    ->extraHeaderAttributes([
                        'style' => 'line-height:1.1;min-width:3rem;width:3rem;',
                        'x-data' => '{}',
                        'x-html' => json_encode(
                            '<span class="fi-ta-header-cell-label text-sm font-medium">'
                            . __('order.columns.order_number') . '<br>Дата заказа'
                            . '</span>'
                        ),
                    ])
                    ->grow(false)
                    ->extraCellAttributes(['style' => 'min-width:3rem;width:3rem;'])
                    ->searchable(isIndividual: true)
                    ->sortable()
                    ->description(function (ShopOrder $record) {
                        $createdAt = $record->created_at?->format('d.m H:i') ?? '—';
                        $recordKey = (string) $record->getKey();
                        $hasCourierComment = trim((string) ($record->courier_comment ?? '')) !== '';
                        $isCash = (($record->payment instanceof PaymentMethodEnum ? $record->payment->value : (int) $record->payment) === PaymentMethodEnum::CASH->value);
                        $cashFrom = (float) ($record->cash_from ?? 0);
                        $buttonStyle = $hasCourierComment
                            ? 'display:inline-flex;align-items:center;border-radius:6px;border:1px solid #fca5a5;background:#fee2e2;color:#b91c1c;padding:2px 8px;font-size:11px;font-weight:600;'
                            : 'display:inline-flex;align-items:center;border-radius:6px;border:1px solid #86efac;background:#dcfce7;color:#15803d;padding:2px 8px;font-size:11px;font-weight:600;';

                        $cashInfo = '';
                        if ($isCash && $cashFrom > 0) {
                            $cashInfo = '<div style="margin-top:4px;display:inline-block;background:#ffedd5;color:#9a3412;padding:2px 6px;border-radius:6px;font-size:11px;font-weight:600;">Сдача с ' . e(number_format($cashFrom, 2, ',', ' ')) . ' грн</div>';
                        }

                        return new HtmlString(
                            '<div class="leading-snug">'
                            . '<div>' . e($createdAt) . '</div>'
                            . $cashInfo
                            . '<div style="margin-top:4px;"><button type="button" style="' . e($buttonStyle) . '" wire:click.stop.prevent="mountTableAction(&quot;courier_comment&quot;, &quot;' . e($recordKey) . '&quot;)">Комментарий курьера</button></div>'
                            . '</div>'
                        );
                    })
                    ->html(),

                TextColumn::make('clients.name')
                    ->label(__('order.columns.client'))
                    ->formatStateUsing(fn (?string $state): string => e($state ?? '—'))
                    ->sortable()
                    ->searchable(isIndividual: true, query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('clients', function (Builder $q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                    })
                    ->extraHeaderAttributes(['style' => 'min-width:10rem;width:10rem;'])
                    ->extraCellAttributes(['style' => 'min-width:10rem;width:10rem;'])
                    ->description(function (ShopOrder $record) {
                        $phone = trim((string) ($record->clients?->phone ?? ''));
                        $group = $record->clients?->group;
                        $groupName = trim((string) ($group?->display_name ?? ''));
                        $isBlacklist = (bool) ($group?->is_blacklist ?? false);
                        $courierComment = trim((string) ($record->courier_comment ?? ''));

                        $phoneBadge = $phone !== ''
                            ? '<span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs" style="background:#dcfce7;color:#166534;border:1px solid #86efac;">' . e($phone) . '</span>'
                            : '<span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs" style="background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;">—</span>';

                        $groupBadge = $groupName !== ''
                            ? '<span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs" style="background:' . ($isBlacklist ? '#fee2e2' : '#eff6ff') . ';color:' . ($isBlacklist ? '#b91c1c' : '#1d4ed8') . ';border:1px solid ' . ($isBlacklist ? '#fecaca' : '#bfdbfe') . ';font-weight:600;">' . e(($isBlacklist ? '👎 ' : '') . $groupName) . '</span>'
                            : '';

                        $courierBadge = $courierComment !== ''
                            ? '<div style="margin-top:4px;display:block;background:#fee2e2;color:#b91c1c;padding:2px 6px;border-radius:6px;font-size:11px;font-weight:600;">' . e($courierComment) . '</div>'
                            : '';

                        return new HtmlString(
                            '<div class="leading-snug">'
                            . '<div>' . $phoneBadge . '</div>'
                            . ($groupBadge !== '' ? '<div class="mt-1">' . $groupBadge . '</div>' : '')
                            . $courierBadge
                            . '</div>'
                        );
                    })
                    ->html(),

                ViewColumn::make('status_compact')
                    ->label(__('order.columns.status'))
                    ->grow(false)
                    ->extraHeaderAttributes(['class' => 'min-w-[9rem] w-[9rem] pr-4'])
                    ->extraCellAttributes(['class' => 'min-w-[9rem] w-[9rem] pr-4'])
                    ->view('filament.tables.columns.logistics-status')
                    ->viewData(function (ShopOrder $record): array {
                        $status = $record->status instanceof OrderStatus
                            ? $record->status
                            : OrderStatus::tryFrom((string) $record->status);

                        return [
                            'statusLabel' => $status?->getLabel() ?? (string) $record->status,
                            'statusColors' => $status?->getFrontendColors() ?? ['bg' => '#E5E7EB', 'text' => '#374151'],
                        ];
                    }),

                ViewColumn::make('delivery_address')
                    ->label(__('logistics.columns.delivery_address'))
                    ->grow(true)
                    ->extraHeaderAttributes(['class' => 'min-w-[24rem] w-[24rem] pl-4'])
                    ->extraCellAttributes(['class' => 'min-w-[24rem] w-[24rem] pl-4'])
                    ->view('filament.tables.columns.logistics-delivery-address')
                    ->viewData(function (ShopOrder $record): array {
                        $deliveryAddress = static::buildDeliveryAddress($record);
                        $deliveryNote = trim(strip_tags((string) ($record->clientAddress?->note ?? '')));

                        return [
                            'address' => $deliveryAddress,
                            'deliveryNote' => $deliveryNote,
                            'isPickup' => (bool) $record->self_pickup,
                            'canOpenRoute' => ! $record->self_pickup && trim($deliveryAddress) !== '' && trim($deliveryAddress) !== '—',
                            'palette' => static::resolveAddressPalette($record),
                            'recordKey' => (string) $record->getKey(),
                        ];
                    }),

                // TextColumn::make('operator_time')
                // TextColumn::make('kitchen_time')
                // TextColumn::make('delivery_time')
                // TextColumn::make('total_time')

                TextColumn::make('total_price')
                    ->label('')
                    ->extraHeaderAttributes([
                        'style' => 'line-height:1.1;',
                        'x-data' => '{}',
                        'x-html' => json_encode(
                            '<span class="fi-ta-header-cell-label text-sm font-medium">'
                            . __('order.fields.sum') . '<br>' . __('order.filters.discount') . '<br>' . __('order.fields.total_with_discount')
                            . '</span>'
                        ),
                    ])
                    ->formatStateUsing(function ($state, ShopOrder $record) {
                        $total = number_format((float) ($record->total_price ?? 0), 2, ',', ' ') . ' грн';
                        $discountValue = (float) ($record->discount_total ?? 0);
                        $discount = $discountValue != 0
                            ? number_format($discountValue, 2, ',', ' ') . ' грн'
                            : '—';
                        $resolved = static::resolveFinalAmount(
                            $record,
                            (float) ($record->subtotal ?? $record->total_price ?? 0),
                            $record->resolveDeliveryAmount()
                        );
                        $grand = number_format((float) ($resolved['final'] ?? 0), 2, ',', ' ') . ' грн';

                        $isCash = (($record->payment instanceof PaymentMethodEnum ? $record->payment->value : (int) $record->payment) === PaymentMethodEnum::CASH->value);
                        $cashFromValue = (float) ($record->cash_from ?? 0);

                        $cashHint = '';
                        if ($isCash && $cashFromValue > 0) {
                            $changeValue = max(0, $cashFromValue - (float) ($resolved['final'] ?? 0));

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
                            . '<div class="text-green-700">' . e($discount) . '</div>'
                            . '<div class="font-semibold">' . e($grand) . '</div>'
                            . $cashHint
                            . '</div>'
                        );
                    })
                    ->html()
                    ->alignRight(),

                TextColumn::make('date_order')
                    ->label('')
                    ->extraHeaderAttributes([
                        'class' => 'th-wrap min-w-[10rem]',
                        'x-data' => '{}',
                        'x-html' => json_encode(
                            '<span class="fi-ta-header-cell-label text-sm font-medium">'
                            . 'Дата<br>доставки'
                            . '</span>'
                        ),
                        'style' => 'line-height: 1.1;',
                    ])
                    ->formatStateUsing(function ($state, ShopOrder $record) {
                        if (! $state) {
                            return '—';
                        }

                        $date = Carbon::parse($state)->format('d.m');
                        $time = $record->time_order ? Carbon::parse($record->time_order)->format('H:i') : '—';

                        return new HtmlString($date . '<br>' . $time);
                    })
                    ->html(),

                ViewColumn::make('items_inline')
                    ->label(__('order.columns.items'))
                    ->grow(false)
                    ->extraHeaderAttributes(['class' => 'min-w-[16rem]'])
                    ->extraCellAttributes(['class' => 'min-w-[16rem]'])
                    ->view('filament.tables.columns.order-items-inline'),

                TextColumn::make('payment')
                    ->label(__('Оплата'))
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(function (null|PaymentMethodEnum $state): string {
                        if (! $state) {
                            return '—';
                        }

                        if ($state === PaymentMethodEnum::INVOICE) {
                            return static::invoiceLabel();
                        }

                        return $state->label();
                    }),

                BadgeColumn::make('liqpay_status')
                    ->label('LiqPay')
                    ->getStateUsing(fn (ShopOrder $record) => $record->lastLiqpayLog?->status)
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'success', 'sandbox' => 'Успішно',
                            'wait_accept', 'processing' => 'В обробці',
                            'failure', 'error' => 'Помилка',
                            'reversed', 'refunded' => 'Повернення',
                            default => 'Немає',
                        };
                    })
                    ->color(function ($state) {
                        return match ($state) {
                            'success', 'sandbox' => 'success',
                            'wait_accept', 'processing' => 'warning',
                            'failure', 'error' => 'danger',
                            'reversed', 'refunded' => 'gray',
                            default => 'secondary',
                        };
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(12)
            ->filters([
                SelectFilter::make('payment')
                    ->label(__('Оплата'))
                    ->options(static::paymentOptionsAdmin())
                    ->multiple()
                    ->preload()
                    ->columnSpan(2),

                TrashedFilter::make()
                    ->columnSpan(2),

                Filter::make('created_at')
                    ->columnSpan(8)
                    ->form([
                        DatePicker::make('created_from')
                            ->label(__('order.filters.date_from'))
                            ->columnSpan(6),
                        DatePicker::make('created_until')
                            ->label(__('order.filters.date_to'))
                            ->columnSpan(6),
                    ])
                    ->columns(12)
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['created_from'] ?? null;
                        $until = $data['created_until'] ?? null;

                        return $query
                            ->when($from, fn (Builder $q, $date): Builder => $q->whereDate('created_at', '>=', $date))
                            ->when($until, fn (Builder $q, $date): Builder => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Action::make('statuses')
                    ->label('')
                    ->icon('')
                    ->extraAttributes(['class' => 'hidden'])
                    ->modalHeading(fn (ShopOrder $r) => __('order.actions.statuses_modal_heading', ['number' => $r->number]))
                    ->modalWidth('lg')
                    ->fillForm(fn (ShopOrder $record): array => [
                        'current' => $record->status?->value,
                        'status_ui' => $record->status?->value ?? OrderStatus::New->value,
                    ])
                    ->form(fn (ShopOrder $record) => static::statusModalForm())
                    ->action(function (array $data, ShopOrder $record) {
                        $user = auth('admin')->user();
                        if (! $user || ! $user instanceof \App\Models\User) {
                            Notification::make()->danger()->title('Ошибка авторизации')->send();
                            return;
                        }

                        $from = $record->status;
                        $to = OrderStatus::from($data['status_ui']);

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
                            $reason = trim((string) ($data['downgrade_reason'] ?? ''));
                            if ($reason === '') {
                                Notification::make()->danger()->title('Укажите причину отката')->send();
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
                                'from' => $from->value,
                                'to' => $to->value,
                                'reason' => $reason,
                            ])->log($newRank < $oldRank ? __('order.journal.status_rollback') : __('order.journal.status_changed'));

                        Notification::make()
                            ->success()
                            ->title($newRank < $oldRank ? 'Статус откатан' : 'Статус обновлён')
                            ->send();
                    }),

                Action::make('route_map')
                    ->label('')
                    ->icon('')
                    ->extraAttributes(['class' => 'hidden'])
                    ->modalHeading(fn (ShopOrder $record) => __('logistics.actions.route_modal_heading', ['number' => $record->number]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('logistics.actions.close'))
                    ->modalWidth('5xl')
                    ->modalContent(function (ShopOrder $record) {
                        $address = static::buildDeliveryAddress($record);
                        $coords = static::resolveCoordinates($record);
                        $kitchen = static::resolveKitchenPoint();

                        return view('filament.logistics.route-map-modal', [
                            'recordId' => (int) $record->getKey(),
                            'address' => $address,
                            'destinationLat' => $coords['lat'],
                            'destinationLng' => $coords['lng'],
                            'googleMapsKey' => (string) config('services.google_maps.key'),
                            'kitchenLat' => $kitchen['lat'],
                            'kitchenLng' => $kitchen['lng'],
                            'kitchenAddress' => $kitchen['address'],
                        ]);
                    })
                    ->action(fn () => null),

                Action::make('courier_comment')
                    ->label('Комментарий курьера')
                    ->icon('heroicon-m-chat-bubble-left-ellipsis')
                    ->color('gray')
                    ->extraAttributes(['class' => 'hidden'])
                    ->fillForm(fn (ShopOrder $record): array => [
                        'courier_comment' => (string) ($record->courier_comment ?? ''),
                    ])
                    ->form([
                        Textarea::make('courier_comment')
                            ->label('Комментарий курьера')
                            ->rows(5)
                            ->placeholder('Комментарий во время/после доставки')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data, ShopOrder $record): void {
                        $record->update([
                            'courier_comment' => trim((string) ($data['courier_comment'] ?? '')) ?: null,
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Комментарий курьера сохранён')
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    protected static function buildDeliveryAddress(ShopOrder $record): string
    {
        if ($record->self_pickup) {
            return __('logistics.columns.self_pickup');
        }

        $address = (array) ($record->address ?? []);
        $model = $record->clientAddress;

        $formatted = trim((string) ($address['formatted_address'] ?? ''));
        if ($formatted !== '') {
            return $formatted;
        }

        $parts = array_filter([
            $address['city'] ?? $model?->city,
            trim((string) (($address['street'] ?? $model?->street ?? '') . ' ' . ($address['house'] ?? $model?->house ?? ''))),
            ! empty($address['apartment'] ?? $model?->apartment)
                ? __('order.address_prefixes.apartment') . ' ' . ($address['apartment'] ?? $model?->apartment)
                : null,
            ! empty($address['entrance'] ?? $model?->entrance)
                ? __('order.address_prefixes.entrance') . ' ' . ($address['entrance'] ?? $model?->entrance)
                : null,
            ! empty($address['floor'] ?? $model?->floor)
                ? __('order.address_prefixes.floor') . ' ' . ($address['floor'] ?? $model?->floor)
                : null,
        ]);

        $line = trim(implode(', ', $parts));

        return $line !== '' ? $line : '—';
    }

    protected static function resolveCoordinates(ShopOrder $record): array
    {
        $address = (array) ($record->address ?? []);
        $model = $record->clientAddress;

        $lat = $address['latitude'] ?? $model?->latitude;
        $lng = $address['longitude'] ?? $model?->longitude;

        return [
            'lat' => $lat ? (float) $lat : null,
            'lng' => $lng ? (float) $lng : null,
        ];
    }

    protected static function resolveKitchenPoint(): array
    {
        $location = Location::query()->find(1);

        if (! $location) {
            return [
                'lat' => config('services.google_maps.kitchen_lat'),
                'lng' => config('services.google_maps.kitchen_lng'),
                'address' => (string) config('services.google_maps.kitchen_address', ''),
            ];
        }

        $address = '';

        if (method_exists($location, 'getTranslation')) {
            $locale = app()->getLocale();
            $address = (string) ($location->getTranslation('address', $locale, false)
                ?: $location->getTranslation('address', 'uk', false)
                ?: '');
        }

        if ($address === '') {
            $raw = $location->address;
            $address = is_array($raw) ? (string) ($raw[app()->getLocale()] ?? $raw['uk'] ?? reset($raw) ?: '') : (string) $raw;
        }

        return [
            'lat' => $location->lat !== null ? (float) $location->lat : null,
            'lng' => $location->lng !== null ? (float) $location->lng : null,
            'address' => trim($address),
        ];
    }

    protected static function resolveAddressPalette(ShopOrder $record): array
    {
        if ($record->self_pickup) {
            return [
                'bg' => 'rgba(251, 191, 36, 0.18)',
                'border' => 'rgba(245, 158, 11, 0.5)',
                'text' => '#78350f',
            ];
        }

        $zoneColor = static::resolveZoneColor($record);
        $rgb = static::hexToRgb($zoneColor ?: '#3b82f6');

        return [
            'bg' => sprintf('rgba(%d, %d, %d, 0.16)', $rgb['r'], $rgb['g'], $rgb['b']),
            'border' => sprintf('rgba(%d, %d, %d, 0.48)', $rgb['r'], $rgb['g'], $rgb['b']),
            'text' => '#0f172a',
        ];
    }

    protected static function resolveZoneColor(ShopOrder $record): ?string
    {
        static $cache = [];

        if ($record->self_pickup) {
            return null;
        }

        $coords = static::resolveCoordinates($record);
        if (! $coords['lat'] || ! $coords['lng']) {
            return null;
        }

        $key = $coords['lat'] . '|' . $coords['lng'];
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $tempOrder = new Order();
        $tempOrder->self_pickup = false;
        $tempOrder->address = [
            'latitude' => $coords['lat'],
            'longitude' => $coords['lng'],
        ];

        $delivery = app(DeliveryCalculationService::class)->calculateDelivery(
            $tempOrder,
            (float) ($record->total_price ?? $record->grand_total ?? 0)
        );

        $zone = $delivery['zone'] ?? null;

        return $cache[$key] = is_object($zone) ? ($zone->color ?? null) : null;
    }

    protected static function hexToRgb(string $color): array
    {
        $value = ltrim(trim($color), '#');

        if (strlen($value) === 3) {
            $value = $value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $value)) {
            $value = '3b82f6';
        }

        return [
            'r' => hexdec(substr($value, 0, 2)),
            'g' => hexdec(substr($value, 2, 2)),
            'b' => hexdec(substr($value, 4, 2)),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
        ];
    }
}
