<?php

namespace App\Livewire\Admin;

use App\Models\Shop\Order;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

class OrderActivityTable extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->query())
            ->columns([
                TextColumn::make('created_at')
                    ->label('Дата/время')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),

                TextColumn::make('causer.name')
                    ->label('Пользователь')
                    ->default('Система')
                    ->toggleable(),

                TextColumn::make('log_name')
                    ->label('Источник')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('description')
                    ->label('Операция'),

                TextColumn::make('event')
                    ->label('Событие')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('properties.old')
                    ->label('Старое')
                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_UNESCAPED_UNICODE))
                    ->limit(60)
                    ->tooltip(fn ($state) => json_encode($state, JSON_UNESCAPED_UNICODE))
                    ->toggleable(),

                TextColumn::make('properties.attributes')
                    ->label('Новое')
                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_UNESCAPED_UNICODE))
                    ->limit(60)
                    ->tooltip(fn ($state) => json_encode($state, JSON_UNESCAPED_UNICODE))
                    ->toggleable(),

                TextColumn::make('properties')
                    ->label('Доп. инфо')
                    ->formatStateUsing(function ($state): string {
                        if (! is_array($state)) return '';
                        $from = $state['status_from'] ?? null;
                        $to   = $state['status_to'] ?? null;
                        if ($from || $to) return "Статус: {$from} → {$to}";
                        $key  = $state['action'] ?? null;
                        return $key ? "action: {$key}" : '';
                    })
                    ->limit(60)
                    ->tooltip(fn ($state) => json_encode($state, JSON_UNESCAPED_UNICODE))
                    ->toggleable(),
            ])
            ->paginated([25, 50, 100]);
    }

    protected function query(): Builder
    {
        return Activity::query()
            ->where(fn (Builder $q) => $q
                ->where('subject_type', Order::class)
                ->where('subject_id', $this->order->id)
            )
            ->orWhere('properties->order_id', $this->order->id)
            ->latest();
    }

    public function render()
    {
        return view('livewire.admin.order-activity-table');
    }
}
