<?php

namespace App\Filament\Resources\Shop\LoyaltyAccountResource\Widgets;

use App\Filament\Resources\Shop\LoyaltyAccountResource;
use App\Models\Shop\LoyaltyTransaction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class LoyaltyExpirationHistoryWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'История сгорания бонусов';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('account.id')
                    ->label('Счет')
                    ->url(fn (LoyaltyTransaction $record): string => LoyaltyAccountResource::getUrl('view', ['record' => $record->account_id]))
                    ->sortable(),
                Tables\Columns\TextColumn::make('account.client_id')
                    ->label('Клиент ID')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('account.phone')
                    ->label('Телефон')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Сумма')
                    ->state(fn (LoyaltyTransaction $record): string => number_format(abs((float) $record->amount), 2, '.', ' '))
                    ->sortable(),
                Tables\Columns\TextColumn::make('meta.accrual_id')
                    ->label('Начисление ID')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Баланс после')
                    ->money('UAH', divideBy: false)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('quick_period')
                    ->label('Быстрый период')
                    ->options([
                        'today' => 'Сегодня',
                        '7_days' => '7 дней',
                        'month' => 'Этот месяц',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'today' => $query->whereDate('created_at', today()),
                            '7_days' => $query->where('created_at', '>=', now()->subDays(7)->startOfDay()),
                            'month' => $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
                            default => $query,
                        };
                    }),
                Filter::make('created_at')
                    ->label('Период')
                    ->form([
                        DatePicker::make('from')->label('С'),
                        DatePicker::make('until')->label('По'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'С ' . Carbon::parse($data['from'])->toFormattedDateString();
                        }

                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'По ' . Carbon::parse($data['until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    protected function getTableQuery(): Builder
    {
        return LoyaltyTransaction::query()
            ->with(['account'])
            ->where('type', LoyaltyTransaction::TYPE_EXPIRE)
            ->where('source', 'system_expire');
    }
}
