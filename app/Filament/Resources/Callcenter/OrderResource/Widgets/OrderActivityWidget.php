<?php

namespace App\Filament\Resources\Callcenter\OrderResource\Widgets;

use App\Models\Callcenter\Order;
use App\Support\Activity\OrderActivityFormatter;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class OrderActivityWidget extends BaseWidget
{
    public ?Order $record = null;

    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = null;

    public static function getHeading(): string
    {
        return __('order.journal.heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->query())
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('order.journal.columns.datetime'))->dateTime('Y-m-d H:i:s')->sortable(),

                TextColumn::make('causer.name')
                    ->label(__('order.journal.columns.user'))
                    ->default(__('order.journal.system'))
                    ->toggleable(),

                TextColumn::make('log_name')
                    ->label(__('order.journal.columns.source'))
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'order'       => __('order.journal.sources.order'),
                        'order.items' => __('order.journal.sources.order.items'),
                        default       => (string) $state,
                    })
                    ->badge(),

                TextColumn::make('description')
                    ->label(__('order.journal.columns.operation'))
                    ->state(fn (Activity $r) => OrderActivityFormatter::operation($r)),

                TextColumn::make('properties')
                    ->label(__('order.journal.columns.additional_info'))
                    ->state(fn (Activity $r) => OrderActivityFormatter::text($r))
                    ->tooltip(fn (Activity $r) => OrderActivityFormatter::tooltip($r))
                    ->wrap()
                    ->grow()
                    ->limit(120)

                    ->toggleable(),

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
