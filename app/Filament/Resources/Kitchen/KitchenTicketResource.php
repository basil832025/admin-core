<?php

namespace App\Filament\Resources\Kitchen;

use App\Enums\OrderStatus;
use App\Filament\Resources\Kitchen\Pages;
use App\Models\Kitchen\KitchenTicket;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Panel as PanelLayout;
use Filament\Tables\Columns\ViewColumn;
use App\Models\Shop\OrderItem as OrderItemModel;
use Filament\Notifications\Notification;
use Filament\Forms\Get;
use Illuminate\Support\HtmlString;

class KitchenTicketResource extends Resource
{
    protected static ?string $model = KitchenTicket::class;

    protected static ?string $navigationGroup = null;
    protected static ?string $navigationIcon   = 'heroicon-m-fire';
    protected static ?string $navigationLabel  = null;
    protected static ?int    $navigationSort   = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('kitchen_ticket.nav.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('kitchen_ticket.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('kitchen_ticket.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('kitchen_ticket.nav.plural_model_label');
    }
// ⬇️ БАЗОВЫЙ ЗАПРОС ДЛЯ ТАБЛИЦЫ (используется везде, в т.ч. табами)
    public static function getEloquentQuery(): Builder
    {
        $scope = request()->get('scope', 'current'); // current|archived

        return KitchenTicket::query()
            ->leftJoin('bs_shop_orders as so', 'so.id', '=', 'bs_kitchen_tickets.order_id')
            ->select('bs_kitchen_tickets.*')
            ->selectRaw('CONCAT(so.date_order, " ", TIME(so.time_order)) as order_dt')
            ->when(
                $scope === 'archived',
                fn (Builder $q) =>
                $q->where('stage', \App\Enums\OrderStatus::Prepared->value),
            fn (Builder $q) =>
            $q->whereIn('stage', [
                \App\Enums\OrderStatus::Processing->value,
                    \App\Enums\OrderStatus::Filling->value,
                    \App\Enums\OrderStatus::Molding->value,
                    \App\Enums\OrderStatus::Baking->value,
                ])
        )
        ->orderBy('so.date_order')
        ->orderBy('so.time_order');
}
    public static function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return \App\Models\Kitchen\KitchenTicket::query()
            ->with([
                'order.items.product', // чтобы в модалке были названия товаров
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)

            // по клику на строку запускать нашу модалку
            // если к тикету нет заказа — клика нет
            ->recordAction(fn ($record) => $record->order ? 'itemsStages' : null)
           ->query(static::getEloquentQuery())

            ->poll('5s') // лайв-обновление для кухни
            ->columns([
                TextColumn::make('order.number')
                    ->label(__('kitchen_ticket.columns.order_number'))
                    ->weight('semibold')
                    ->description(fn ($record) => $record->order?->created_at?->format('d.m H:i'))
                    ->wrap() // номер+дата в 2 строки ок
                    ->grow(false) // не позволяем этой колонке растягиваться/сжиматься
                    ->extraHeaderAttributes(['class' => 'min-w-[8rem]'])
                    ->extraCellAttributes(['class' => 'min-w-[8rem]']),

                TextColumn::make('order_dt')
                    ->label(__('kitchen_ticket.columns.order_time'))
                    ->dateTime('d.m H:i')
                    ->grow(false)
                    ->extraHeaderAttributes(['class' => 'min-w-[7rem]'])
                    ->extraCellAttributes(['class' => 'min-w-[7rem]'])
                    ->sortable(
                        query: fn (Builder $q, string $dir) =>
                    $q->orderBy('so.date_order', $dir)->orderBy('so.time_order', $dir)
                    ),

                BadgeColumn::make('urgent')
                    ->label(__('kitchen_ticket.columns.urgent'))
                    ->grow(false)
                    ->state(fn (KitchenTicket $record) =>
                    (bool) ($record->as_soon_possible ?? ($record->order->as_soon_possible ?? false))
                    )
                    ->formatStateUsing(fn ($state) => $state ? __('kitchen_ticket.values.yes') : __('kitchen_ticket.values.no'))
                    ->color(fn ($state) => $state ? 'danger' : 'gray')
                    ->toggleable(),

                BadgeColumn::make('delivery_type')
                    ->label(__('kitchen_ticket.columns.delivery_type'))
                    ->grow(false)
                    ->state(fn (KitchenTicket $record) =>
                        $record->delivery_type
                        ?? (((int) ($record->order?->self_pickup) === 1) ? 'pickup' : 'delivery')
                    )
                    ->formatStateUsing(fn ($state) =>
                    $state === 'pickup' ? __('kitchen_ticket.values.pickup') : ($state === 'delivery' ? __('kitchen_ticket.values.delivery') : __('kitchen_ticket.values.no'))
                    )
                    ->color(fn ($state) =>
                    $state === 'pickup' ? 'lime' : ($state === 'delivery' ? 'sky' : 'gray')
                    ),
                TextColumn::make('items_count')
                    ->label(__('kitchen_ticket.columns.items_count'))
                    ->counts('items') // если есть relation items()
                    ->sortable()
                    ->alignCenter(),


                // текущий этап (использует твой enum оформления)
                BadgeColumn::make('stage')
                    ->label(__('kitchen_ticket.columns.stage'))
                    ->grow(false)
                    ->formatStateUsing(fn ($state) => ($s = $state instanceof OrderStatus ? $state : OrderStatus::from($state))->getLabel())
                    ->icon(fn ($state) => ($s = $state instanceof OrderStatus ? $state : OrderStatus::from($state))->getIcon())
                    ->color(fn ($state) => ($s = $state instanceof OrderStatus ? $state : OrderStatus::from($state))->getColor()),
                // ⬇️ РАСКРЫВАЮЩАЯСЯ ПАНЕЛЬ ПОД СТРОКОЙ
                // Панель под строкой на всю ширину:

            ])

            ->filters([
                // Вкладки: Текущие / Архив
                Tables\Filters\SelectFilter::make('tab')
                    ->label(__('kitchen_ticket.filters.status'))
                    ->options([
                        'current'  => __('kitchen_ticket.filter_options.current'),
                        'archived' => __('kitchen_ticket.filter_options.archived'),
                    ])
                    ->default('current')
                    ->query(function (\Illuminate\Database\Eloquent\Builder $q, array $data) {
                        $isArchived = ($data['value'] ?? 'current') === 'archived';

                        return $isArchived
                            ? $q->where('stage', \App\Enums\OrderStatus::Prepared->value)
            : $q->whereIn('stage', [
                            \App\Enums\OrderStatus::Processing->value,
                \App\Enums\OrderStatus::Filling->value,
                \App\Enums\OrderStatus::Molding->value,
                \App\Enums\OrderStatus::Baking->value,
            ]);
    })
                    ->columnSpanFull(),

                TernaryFilter::make('urgent')
                    ->label(__('kitchen_ticket.filters.urgent'))
                    ->placeholder(__('kitchen_ticket.filter_options.any'))
                    ->trueLabel(__('kitchen_ticket.filter_options.urgent_only'))
                    ->falseLabel(__('kitchen_ticket.filter_options.normal_only'))
                    ->queries(
                        true:  fn (Builder $q) => $q->where('urgent', true),
                        false: fn (Builder $q) => $q->where('urgent', false),
                        blank: fn (Builder $q) => $q
                    ),

                Tables\Filters\SelectFilter::make('delivery_type')
                    ->label(__('kitchen_ticket.filters.delivery_type'))
                    ->options([
                        'delivery' => __('kitchen_ticket.filter_options.delivery'),
                        'pickup'   => __('kitchen_ticket.filter_options.pickup'),
                    ]),
            ])
            ->actions([
                Action::make('itemsStages')
                    ->label('')               // кнопку мы «прячем», вызываем по клику на строку
                    ->icon('')
                    ->extraAttributes(['class' => 'opacity-0 pointer-events-none w-0 h-0 p-0 m-0'])
                    ->visible(fn ($record) => (bool) $record->order)
                    ->modalHeading(fn ($record) => __('kitchen_ticket.modals.order_items_heading', ['number' => $record->order?->number]))
                    ->modalWidth('4xl')
                    ->form(function ($record) {
                        $order = $record->order;

                        $activeKey = static::stageKeyForTicket($record); // как мы делали раньше
                        $stageNames = [
                            'accepted' => __('kitchen_ticket.stages.accepted'),
                            'filling'  => __('kitchen_ticket.stages.filling'),
                            'molding'  => __('kitchen_ticket.stages.molding'),
                            'baking'   => __('kitchen_ticket.stages.baking'),
                            'ready'    => __('kitchen_ticket.stages.ready'),
                        ];
                        $stageLabel = $stageNames[$activeKey] ?? __('kitchen_ticket.stages.stage');

                        // подготовка строк
                        $rows = $order->items->map(function (\App\Models\Shop\OrderItem $it) {
                            $title = $it->product?->display_name
                                ?? data_get($it->meta, 'title')
                                ?? '—';

                            $f = (array) ($it->stage_flags ?? []);
                         //  if ($it->product?->id==52)   dd($it->product?->id,$it->product?->dop_info,$it->product);
                            return [
                                'id'       => $it->id,
                                'title'    => $title,
                                'qty'      => (int) $it->qty,
                                'dop_info' => (string) ($it->product?->dop_info ?? ''),   // 👈 сюда
                                'accepted' => (bool) ($f['accepted'] ?? false),
                                'filling'  => (bool) ($f['filling']  ?? false),
                                'molding'  => (bool) ($f['molding']  ?? false),
                                'baking'   => (bool) ($f['baking']   ?? false),
                                'ready'    => (bool) ($f['ready']    ?? false),
                            ];
                        })->values()->toArray();

                        return [
                            // ── Шапка «таблицы»
                            Grid::make(12)
                                ->schema([
                                    Placeholder::make('h_title')->label('')  ->content(__('kitchen_ticket.table_headers.product'))->columnSpan(8)
                                        ->extraAttributes(['class' => 'text-sm font-medium text-gray-500']),
                                    Placeholder::make('h_qty')->label('') ->content(__('kitchen_ticket.table_headers.quantity'))->columnSpan(2)
                                        ->extraAttributes(['class' => 'text-sm font-medium text-gray-500']),
                                    Placeholder::make('h_stage')->label('')  ->content($stageLabel)->columnSpan(2)
                                        ->extraAttributes(['class' => 'text-sm font-medium text-gray-500']),
                                ]),

                            // ── «Таблица» строк
                            Repeater::make('items_state')
                                ->default($rows)
                                ->label('')
                                ->addable(false)->deletable(false)->reorderable(false)
                                ->columns(12)
                                // компактнее и с разделителями, как таблица
                                ->extraAttributes(['class' => 'divide-y divide-gray-200'])
                                ->schema([
                                    Hidden::make('id'),

                                    // ячейки как «текст» (без инпутов)
                                    Placeholder::make('title_p')
                                        ->label('') // без подписи
                                       //->content(fn (Get $get) => $get('title'))
                                        ->content(function (Get $get) {
                                            $title   = e((string) $get('title'));
                                            $dopInfo = trim((string) $get('dop_info'));
                                            $dopHtml = $dopInfo !== ''
                                                ? ($dopInfo)
                                                : '<span class="text-gray-400">' . e(__('kitchen_ticket.helpers.calculation_missing')) . '</span>';

                                            return new \Illuminate\Support\HtmlString(<<<HTML
<div x-data="{ open: false }" class="py-2">
  <button type="button"
          class="text-left w-full font-medium hover:underline"
          @click="open = !open">
    {$title}
  </button>
  <div x-show="open" x-cloak class="mt-2 text-gray-600 text-sm leading-relaxed">
    {$dopHtml}
  </div>
</div>
HTML);
                                        })


                                        ->columnSpan(8),

                                    Placeholder::make('qty_p')
                                        ->label('')
                                        ->content(fn (Get $get) => (string) $get('qty'))
                                        ->columnSpan(2),

                                    // один тумблер — только по активному этапу
                                    Toggle::make('accepted')->label('')->inline(false)->columnSpan(2)
                                        ->visible(fn () => $activeKey === 'accepted'),
                                    Toggle::make('filling')->label('')->inline(false)->columnSpan(2)
                                        ->visible(fn () => $activeKey === 'filling'),
                                    Toggle::make('molding')->label('')->inline(false)->columnSpan(2)
                                        ->visible(fn () => $activeKey === 'molding'),
                                    Toggle::make('baking')->label('')->inline(false)->columnSpan(2)
                                        ->visible(fn () => $activeKey === 'baking'),
                                    Toggle::make('ready')->label('')->inline(false)->columnSpan(2)
                                        ->visible(fn () => $activeKey === 'ready'),
                                ]),
                        ];
                    })
                    ->modalSubmitActionLabel(__('kitchen_ticket.actions.save'))
                    ->action(function ($record, array $data) {
                        $order = $record->order;
                        $activeKey = static::stageKeyForTicket($record);

                        foreach (($data['items_state'] ?? []) as $row) {
                            $item = $order->items()->find($row['id'] ?? null);
                            if (! $item) continue;

                            $flags = (array) ($item->stage_flags ?? []);
                            $flags[$activeKey] = (bool) ($row[$activeKey] ?? false);

                            $item->stage_flags = $flags;
                            $item->saveQuietly();
                        }

                        \Filament\Notifications\Notification::make()
                            ->success()->title(__('kitchen_ticket.notifications.marks_saved'))->send();
                    }),

                // Крупные кнопки этапов кухни
                Tables\Actions\Action::make('to_filling')
                    ->label(__('kitchen_ticket.actions.to_filling'))
                    ->icon('heroicon-m-beaker')
                    ->color('teal')
                    ->visible(fn (KitchenTicket $r) => in_array($r->stage, [
                        OrderStatus::Processing, OrderStatus::Filling,
                    ], true))
                    ->action(fn (KitchenTicket $r) => $r->moveTo(OrderStatus::Filling, auth()->id())),

                Tables\Actions\Action::make('to_molding')
                    ->label(__('kitchen_ticket.actions.to_molding'))
                    ->icon('heroicon-m-puzzle-piece')
                    ->color('indigo')
                    ->visible(fn (KitchenTicket $r) => in_array($r->stage, [
                        OrderStatus::Processing, OrderStatus::Filling, OrderStatus::Molding,
                    ], true))
                    ->action(fn (KitchenTicket $r) => $r->moveTo(OrderStatus::Molding, auth()->id())),

                Tables\Actions\Action::make('to_baking')
                    ->label(__('kitchen_ticket.actions.to_baking'))
                    ->icon('heroicon-m-fire')
                    ->color('orange')
                    ->visible(fn (KitchenTicket $r) => in_array($r->stage, [
                        OrderStatus::Processing, OrderStatus::Filling, OrderStatus::Molding, OrderStatus::Baking,
                    ], true))
                    ->action(fn (KitchenTicket $r) => $r->moveTo(OrderStatus::Baking, auth()->id())),

                Tables\Actions\Action::make('to_prepared')
                    ->label(__('kitchen_ticket.actions.to_prepared'))
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('kitchen_ticket.modals.confirm_prepared_heading'))
                    ->modalDescription(__('kitchen_ticket.modals.confirm_prepared_description'))
                    ->visible(fn (KitchenTicket $r) => in_array($r->stage, [
                        OrderStatus::Processing, OrderStatus::Filling, OrderStatus::Molding, OrderStatus::Baking,
                    ], true))
                    ->action(fn (KitchenTicket $r) => $r->moveTo(OrderStatus::Prepared, auth()->id())),
            ])

            ->bulkActions([]) // на кухне массовых не нужно
            ->emptyStateIcon('heroicon-m-fire')
            ->emptyStateHeading(__('kitchen_ticket.empty_state.heading'))
            ->emptyStateDescription(__('kitchen_ticket.empty_state.description'));
    }
    protected static function stageKeyForTicket($ticket): string
    {
        // сопоставь со своими enum/значениями статуса тикета
        // accepted → "В обработке/Принял", filling → "Начинка", molding → "Лепка", baking → "Печь", ready → "Готово"
        return match ((string) $ticket->stage?->value ?? (string) $ticket->stage ?? '') {
            'in_progress', 'processing', 'processed'   => 'accepted',
            'filling'                                  => 'filling',
            'molding'                                  => 'molding',
            'baking'                                   => 'baking',
            'ready', 'prepared', 'done'                => 'ready',
            default                                    => 'accepted', // по умолчанию «Принял»
        };
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKitchenTickets::route('/'),
        ];
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
}
