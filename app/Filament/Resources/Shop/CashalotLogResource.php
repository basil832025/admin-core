<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\CashalotLogResource\Pages;
use App\Models\Shop\CashalotLog;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CashalotLogResource extends Resource
{
    protected static ?string $model = CashalotLog::class;

    protected static ?string $navigationGroup = 'Магазин';
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationLabel = 'Логи Cashalot';
    protected static ?string $pluralModelLabel = 'Логи Cashalot';
    protected static ?string $slug = 'shop/cashalot-logs';
    protected static ?int $navigationSort = 21;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Фіскалізація')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'success' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'secondary',
                    })
                    ->sortable(),

                TextColumn::make('order.number')
                    ->label('Замовлення')
                    ->url(fn (CashalotLog $record) => $record->order
                        ? OrderResource::getUrl('edit', ['record' => $record->order])
                        : null)
                    ->openUrlInNewTab(),

                TextColumn::make('num_fiscal')
                    ->label('Фіскальний №')
                    ->copyable(),

                TextColumn::make('receipt_url')
                    ->label('Чек')
                    ->formatStateUsing(fn (?string $state): string => $state ? 'Відкрити' : '—')
                    ->url(fn (CashalotLog $record): ?string => $record->receipt_url)
                    ->openUrlInNewTab(),

                TextColumn::make('consumer_status')
                    ->label('Відправка клієнту')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        'skipped' => 'gray',
                        default => 'secondary',
                    }),

                TextColumn::make('consumer_service_type')
                    ->label('Канал')
                    ->formatStateUsing(fn ($state): string => match ((int) $state) {
                        1 => 'Viber',
                        0 => 'SMS',
                        default => '—',
                    }),

                TextColumn::make('consumer_phone')
                    ->label('Телефон')
                    ->copyable(),

                TextColumn::make('consumer_error_message')
                    ->label('Помилка відправки')
                    ->limit(70)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->wrap(),

                TextColumn::make('error_message')
                    ->label('Помилка фіскалізації')
                    ->limit(70)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->wrap(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Фіскалізація')
                    ->multiple()
                    ->options([
                        'success' => 'success',
                        'pending' => 'pending',
                        'failed' => 'failed',
                        'skipped' => 'skipped',
                    ]),

                SelectFilter::make('consumer_status')
                    ->label('Відправка клієнту')
                    ->multiple()
                    ->options([
                        'sent' => 'sent',
                        'failed' => 'failed',
                        'skipped' => 'skipped',
                    ]),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('З'),
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
                            $indicators['from'] = 'З ' . Carbon::parse($data['from'])->toFormattedDateString();
                        }

                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'По ' . Carbon::parse($data['until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashalotLogs::route('/'),
            'view' => Pages\ViewCashalotLog::route('/{record}'),
        ];
    }

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form;
    }
}
