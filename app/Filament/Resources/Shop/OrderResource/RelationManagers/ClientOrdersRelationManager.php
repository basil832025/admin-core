<?php

namespace App\Filament\Resources\Shop\OrderResource\RelationManagers;

use App\Enums\PaymentMethodEnum;              // если используешь enum для оплаты
use App\Models\Shop\Order;
use App\Models\Shop\OrderItem;
use Awcodes\TableRepeater\Components\TableRepeater;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Awcodes\TableRepeater\Header;                  // ✅ а не Components\Header
use Filament\Support\Enums\Alignment;
use App\Filament\Resources\Shop\OrderResource;
class ClientOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'clientOrders';
    protected static ?string $title = 'История заказов'; // заголовок вкладки

    public function isReadOnly(): bool
    {
        return true; // историю не редактируем
    }

    protected function getTableQuery(): Builder
    {
        /** @var Order $current */
        $current = $this->getOwnerRecord();

        return Order::query()
            ->where('clients_id', $current->clients_id)
            ->whereKeyNot($current->getKey())      // исключаем текущий заказ
            ->latest('created_at');                // новые сверху
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')       // поле номера заказа
            ->columns([
                TextColumn::make('number')->label('Номер заказа')->searchable(),

                TextColumn::make('status')->label('Статус')->badge(),

                // ↓ Поменяй имена на свои поля
                TextColumn::make('total_price')->label('Сумма')->numeric(2)->alignRight(),
                TextColumn::make('discount_total')->label('Скидка')->numeric(2)->alignRight(),
                TextColumn::make('grand_total')->label('Сумма со скидкой')->numeric(2)->alignRight(),

                TextColumn::make('delivery_date')->label('Дата доставки')->date(),
                TextColumn::make('delivery_time')->label('Время доставки')->time(),
                TextColumn::make('delivery_type')->label('Доставка')->badge(),

                // Оплата с enum (или убери formatStateUsing, если без enum)
                TextColumn::make('payment')->label('Оплата')
                    ->formatStateUsing(fn ($state) =>
                    $state instanceof PaymentMethodEnum
                        ? $state->label()
                        : (optional($state, fn ($v) => PaymentMethodEnum::from((int) $v)->label()) ?? '—')
                    ),

                TextColumn::make('created_at')->label('Дата заказа')->date(),
            ])
            ->actions([
                // Открыть состав заказа и добавить выбранные позиции в текущий
                Action::make('open')
                    ->label('Открыть')
                    ->slideOver()
                    ->modalWidth('7xl')
                    ->form(function (Order $record) {
                        // 1) Готовим строки (обязательно ->values())
                        $rows = $record->items()
                            ->with('product:id,title') // имя связи/поля подставь свои
                            ->get()
                            ->map(fn (OrderItem $it) => [
                                'item_id'  => $it->getKey(),
                                'selected' => false,
                                'title'    => $it->product->title ?? ('#'.$it->product_id),
                                'qty'      => (int) $it->qty,
                                'price'    => (float) $it->unit_price,
                            ])
                            ->values()
                            ->all();

                        return [
                            Section::make('Позиции заказа')
                                ->columnSpanFull()
                                ->schema([
                                    Repeater::make('rows')
                                        ->label(' ')
                                        ->addable(false)->deletable(false)->reorderable(false)
                                        ->columns(12)->columnSpanFull()
                                        ->default($rows) // 2) прямая инициализация
                                        ->afterStateHydrated(function (Repeater $component, ?array $state) use ($rows) {
                                            // 3) страховка, если вдруг state пустой
                                            if (empty($state)) {
                                                $component->state($rows);
                                            }
                                        })
                                        ->schema([
                                            Checkbox::make('selected')->label(' ')->columnSpan(1),
                                            TextInput::make('title')->label('Товар')->disabled()->dehydrated(false)->columnSpan(7),
                                            TextInput::make('qty')->label('К-во')->numeric()->minValue(1)->step(1)->columnSpan(2),
                                            TextInput::make('price')->label('Цена')->numeric()->dehydrated(false)->columnSpan(2),
                                            Hidden::make('item_id'),
                                        ]),
                                ]),
                        ];
                    })
                    ->modalSubmitActionLabel('Добавить в текущий заказ')
                    ->action(function (array $data) {
                        /** @var \App\Models\Shop\Order $current */
                        $current = $this->getOwnerRecord();

                        $toAdd = collect($data['rows'] ?? [])
                            ->values()
                            ->where('selected', true)
                            ->map(function (array $row) {
                                $src = \App\Models\Shop\OrderItem::find($row['item_id']);
                                if (! $src) return null;

                                return [
                                    'product_id' => $src->product_id,
                                    'qty'        => max(1, (int) ($row['qty'] ?? 1)),
                                    'unit_price' => (float) $src->unit_price,
                                ];
                            })
                            ->filter()
                            ->values()
                            ->all();

                        if ($toAdd) {
                            $current->items()->createMany($toAdd);
                            \Filament\Notifications\Notification::make()->title('Позиции добавлены')->success()->send();
                            $this->dispatch('refresh');
                            // 👇 мягкая «перезагрузка» страницы редактирования
                            return $this->redirect(
                                OrderResource::getUrl('edit', ['record' => $current]),
                                navigate: true
                            );
                        } else {
                            \Filament\Notifications\Notification::make()->title('Не выбрано ни одной позиции')->warning()->send();
                        }

                    })
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(true);
    }
}
