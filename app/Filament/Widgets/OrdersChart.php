<?php

namespace App\Filament\Widgets;

use App\Models\Shop\Order;
use App\Support\Traits\HandlesShieldWidgetAccess;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class OrdersChart extends ChartWidget
{
    use HandlesShieldWidgetAccess;
    protected static ?string $heading = 'Заказов в месяц';

    protected static ?int $sort = 1;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $orderData = Trend::model(Order::class)
            ->between(
                start: now()->startOfYear(),
                end: now()->endOfYear(),
            )
            ->perMonth()
            ->count();

        $data = $orderData->map(fn (TrendValue $value) => (int) $value->aggregate)->toArray();
        $labels = $orderData->map(fn (TrendValue $value) => 
            \Carbon\Carbon::parse($value->date)->translatedFormat('M')
        )->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Заказы',
                    'data' => $data,
                    'fill' => 'start',
                ],
            ],
            'labels' => $labels,
        ];
    }
}
