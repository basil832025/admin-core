<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\LiqPayLogResource\Pages;
use App\Filament\Resources\Shop\LiqPayLogResource\RelationManagers;
use App\Models\Shop\LiqPayLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;


use App\Filament\Resources\Shop\OrderResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;

use Illuminate\Support\Carbon;


class LiqPayLogResource extends Resource
{
    protected static ?string $model = LiqPayLog::class;

    protected static ?string $navigationGroup = 'Магазин';
    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Логи LiqPay';
    protected static ?string $pluralModelLabel = 'Логи LiqPay';
    protected static ?string $slug = 'shop/liqpay-logs';
    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('log_date')
                    ->label('Дата логa')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(function (?string $state) {
                        return match ($state) {
                            'success', 'sandbox'        => 'success',
                            'wait_accept', 'processing' => 'warning',
                            'failure', 'error'          => 'danger',
                            'reversed', 'refunded'      => 'gray',
                            default                     => 'secondary',
                        };
                    })
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('action')
                    ->label('Дія')
                    ->toggleable(),

                TextColumn::make('amount')
                    ->label('Сума')
                    ->formatStateUsing(fn ($state, LiqPayLog $record) =>
                        number_format((float) $state, 2, ',', ' ') . ' ' . ($record->currency ?? 'UAH')
                    )
                    ->alignRight()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('paytype')
                    ->label('Тип оплати')
                    ->toggleable(),

                TextColumn::make('sender_phone')
                    ->label('Телефон')
                    ->copyable()
                    ->copyMessage('Скопійовано')
                    ->toggleable(),

                TextColumn::make('order.number')
                    ->label('Заказ')
                    ->url(fn (LiqPayLog $record) =>
                    $record->order
                        ? OrderResource::getUrl('edit', ['record' => $record->order])
                        : null
                    )
                    ->openUrlInNewTab()
                    ->toggleable(),

                TextColumn::make('payment_id')
                    ->label('Payment ID')
                    ->toggleable(),

                TextColumn::make('liqpay_order_id')
                    ->label('LiqPay order')
                    ->toggleable(),

                TextColumn::make('description')
                    ->label('Опис')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state)
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('error_short')
                    ->label('Коментар')
                    ->getStateUsing(function (LiqPayLog $record) {
                        $payload = is_array($record->payload)
                            ? $record->payload
                            : (json_decode($record->payload ?? '[]', true) ?: []);

                        return $payload['err_description']
                            ?? $payload['err_code']
                            ?? null;
                    })
                    ->limit(60)
                    ->tooltip(fn ($state) => $state)
                    ->wrap()
                    ->toggleable(),
            ])
            ->defaultSort('log_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'success'    => 'success',
                        'sandbox'    => 'sandbox',
                        'wait_accept'=> 'wait_accept',
                        'processing' => 'processing',
                        'failure'    => 'failure',
                        'error'      => 'error',
                        'reversed'   => 'reversed',
                        'refunded'   => 'refunded',
                    ])
                    ->multiple()
                    ->preload(),

                Filter::make('log_date')
                    ->form([
                        DatePicker::make('from')->label('З'),
                        DatePicker::make('until')->label('По'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('log_date', '>=', $date))
                            ->when($data['until'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('log_date', '<=', $date));
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
            ->bulkActions([]); // массовые действия не нужны
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLiqPayLogs::route('/'),
            'view'  => Pages\ViewLiqPayLog::route('/{record}'),
        ];
    }

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        // форма нам по сути не нужна (только просмотр),
        // но Filament требует метод form — можно оставить пустой.
        return $form;
    }
}
