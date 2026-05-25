<?php

namespace App\Filament\Resources\Callcenter\OrderResource\Widgets;

use App\Filament\Resources\Callcenter\OrderResource\Pages\ListOrders;
use App\Models\Callcenter\Order;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class OrderStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected static ?string $pollingInterval = null;
    public array $tableColumnSearches = [];

    protected function getTablePage(): string
    {
        return ListOrders::class;
    }

    protected function getStats(): array
    {
        $orderData = Trend::model(Order::class)
            ->between(
                start: now()->subYear(),
                end: now(),
            )
            ->perMonth()
            ->count();

        return [
            Stat::make(__('callcenter.list.widgets.orders_total'), $this->getPageTableQuery()->count())
                ->chart(
                    $orderData
                        ->map(fn (TrendValue $value) => $value->aggregate)
                        ->toArray()
                ),
            Stat::make(__('callcenter.list.widgets.orders_open'), $this->getPageTableQuery()->whereIn('status', ['open', 'processing'])->count()),
            Stat::make(__('callcenter.list.widgets.avg_price'), number_format($this->getPageTableQuery()->avg('total_price'), 2)),
        ];
    }
}
