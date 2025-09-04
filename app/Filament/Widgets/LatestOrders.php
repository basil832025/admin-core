<?php

namespace App\Filament\Widgets;

use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;

use App\Filament\Resources\Shop\OrderResource;
use App\Models\Shop\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use \App\Models\Currency;
use App\Support\Traits\HandlesShieldWidgetAccess;
class LatestOrders extends BaseWidget
{
    use HandlesShieldWidgetAccess;
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;


    public function table(Table $table): Table
    {
        return $table
            ->query(OrderResource::getEloquentQuery())
            ->defaultPaginationPageOption(4)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Order Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('clients.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('currency')
                    ->getStateUsing(fn ($record): ?string => Currency::find($record->currency)?->name ?? null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_price')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('shipping_price')
                    ->label('Shipping cost')
                    ->searchable()
                    ->sortable(),
            ])
            ->Actions([
                Action::make('open')
                    ->url(fn (Order $record): string => OrderResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
