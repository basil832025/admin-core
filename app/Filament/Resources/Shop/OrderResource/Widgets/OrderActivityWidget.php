<?php

namespace App\Filament\Resources\Shop\OrderResource\Widgets;

use App\Models\Shop\Order;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Arr;
use App\Support\Activity\OrderActivityFormatter;
use Illuminate\Support\Str;

class OrderActivityWidget extends BaseWidget
{
    // Filament автоматически пробросит текущую запись ресурса в это свойство
    public ?Order $record = null;

    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Журнал операций';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->query())
            ->columns([
                TextColumn::make('created_at')
                    ->label('Дата/время')->dateTime('Y-m-d H:i:s')->sortable(),

                TextColumn::make('causer.name')
                    ->label('Пользователь')->default('Система')->toggleable(),

                TextColumn::make('log_name')
                    ->label('Источник')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'order'       => 'Заказ',
                        'order.items' => 'Товары заказа',
                        default       => (string) $state,
                    })
                    ->badge(),

                TextColumn::make('description')
                    ->label('Операция')
                    ->state(fn (Activity $r) => OrderActivityFormatter::operation($r)),

             /*   Tables\Columns\TextColumn::make('event')
                    ->label('Событие')->badge()->toggleable(),
*/
            /*    Tables\Columns\TextColumn::make('properties.old')
                    ->label('Старое')
                    ->formatStateUsing(fn ($s) => json_encode($s, JSON_UNESCAPED_UNICODE))
                    ->limit(60)->tooltip(fn ($s) => json_encode($s, JSON_UNESCAPED_UNICODE))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('properties.attributes')
                    ->label('Новое')
                    ->formatStateUsing(fn ($s) => json_encode($s, JSON_UNESCAPED_UNICODE))
                    ->limit(60)->tooltip(fn ($s) => json_encode($s, JSON_UNESCAPED_UNICODE))
                    ->toggleable(),*/

                TextColumn::make('properties')
                    ->label('Доп. инфо')
                    // Формируем видимый текст в ячейке
                    ->state(fn (Activity $r) => OrderActivityFormatter::text($r))
                    ->tooltip(fn (Activity $r) => OrderActivityFormatter::tooltip($r))
                    ->wrap()     // перенос строк
                    ->grow()     // даём колонке расти
                    ->limit(120) // укоротим видимый текст

                    ->toggleable()

            ])
            ->paginated([25, 50, 100]);
    }

    protected function query(): Builder
    {
        return Activity::query()
            ->when($this->record, fn ($q) =>
            $q->where(fn ($q2) => $q2
                ->where('subject_type', Order::class)
                ->where('subject_id', $this->record->id)
            )
                ->orWhere('properties->order_id', $this->record->id)
            )
            ->latest();
    }
}
