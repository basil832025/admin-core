<?php

namespace App\Filament\Widgets;

use App\Models\Shop\Client;
use App\Models\Shop\Order;
use App\Support\Traits\HandlesShieldWidgetAccess;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Flowframe\Trend\Trend;
use Illuminate\Support\Number;

class StatsOverviewWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    use HandlesShieldWidgetAccess;
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $startDate = ! is_null($this->pageFilters['startDate'] ?? null) ?
            Carbon::parse($this->pageFilters['startDate']) :
            now()->subMonth(); // По умолчанию последний месяц

        $endDate = ! is_null($this->pageFilters['endDate'] ?? null) ?
            Carbon::parse($this->pageFilters['endDate']) :
            now();

        $isBusinessCustomersOnly = $this->pageFilters['businessCustomersOnly'] ?? null;

        // Запросы к БД
        $ordersQuery = Order::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', \App\Enums\OrderStatus::Cart->value);

        $clientsQuery = Client::query()
            ->whereBetween('created_at', [$startDate, $endDate]);

        // Если выбраны только корпоративные клиенты
        if (boolval($isBusinessCustomersOnly)) {
            // Предполагаем, что корпоративные клиенты имеют какое-то поле или связь
            // Если такого поля нет, можно убрать этот фильтр
            // $clientsQuery->where('is_business', true);
        }

        // Доход (сумма всех заказов)
        $revenue = (int) round($ordersQuery->sum('total_price') ?? 0);

        // Новые клиенты
        $newCustomers = $clientsQuery->count();

        // Новые заказы
        $newOrders = $ordersQuery->count();

        // Данные для сравнения с предыдущим периодом
        $previousStartDate = $startDate->copy()->sub($startDate->diffInDays($endDate) + 1, 'days');
        $previousEndDate = $startDate->copy()->subDay();

        $previousRevenue = (int) round(Order::query()
            ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->where('status', '!=', \App\Enums\OrderStatus::Cart->value)
            ->sum('total_price') ?? 0);

        $previousCustomers = Client::query()
            ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->count();

        $previousOrders = Order::query()
            ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->where('status', '!=', \App\Enums\OrderStatus::Cart->value)
            ->count();

        // Расчет изменений
        $revenueChange = $previousRevenue > 0 
            ? round((($revenue - $previousRevenue) / $previousRevenue) * 100, 1)
            : ($revenue > 0 ? 100 : 0);
        
        $customersChange = $previousCustomers > 0
            ? round((($newCustomers - $previousCustomers) / $previousCustomers) * 100, 1)
            : ($newCustomers > 0 ? 100 : 0);
        
        $ordersChange = $previousOrders > 0
            ? round((($newOrders - $previousOrders) / $previousOrders) * 100, 1)
            : ($newOrders > 0 ? 100 : 0);

        // Данные для графиков (последние 7 дней)
        $chartData = Trend::model(Order::class)
            ->between(
                start: now()->subDays(7),
                end: now(),
            )
            ->perDay()
            ->sum('total_price');

        $revenueChart = $chartData->map(fn ($value) => (int) ($value->aggregate ?? 0))->toArray();

        $customersChart = Trend::model(Client::class)
            ->between(
                start: now()->subDays(7),
                end: now(),
            )
            ->perDay()
            ->count();

        $customersChartData = $customersChart->map(fn ($value) => (int) ($value->aggregate ?? 0))->toArray();

        $ordersChart = Trend::model(Order::class)
            ->between(
                start: now()->subDays(7),
                end: now(),
            )
            ->perDay()
            ->count();

        $ordersChartData = $ordersChart->map(fn ($value) => (int) ($value->aggregate ?? 0))->toArray();

        $formatNumber = function (int $number): string {
            if ($number < 1000) {
                return (string) Number::format($number, 0);
            }

            if ($number < 1000000) {
                return Number::format($number / 1000, 2) . 'k';
            }

            return Number::format($number / 1000000, 2) . 'm';
        };

        $formatRevenue = function (int|float $number): string {
            // Форматируем как валюту (total_price уже в основной валюте)
            return Number::currency($number, 'UAH', 'ru');
        };

        return [
            Stat::make('Revenue', $formatRevenue($revenue))
                ->description(
                    $revenueChange >= 0 
                        ? 'увеличение на ' . $formatNumber(abs($revenue - $previousRevenue))
                        : 'падение на ' . $formatNumber(abs($revenue - $previousRevenue))
                )
                ->label('Доход')
                ->descriptionIcon($revenueChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart($revenueChart)
                ->color($revenueChange >= 0 ? 'success' : 'danger'),
            Stat::make('New customers', $formatNumber($newCustomers))
                ->description(
                    $customersChange >= 0
                        ? 'увеличение на ' . abs($customersChange) . '%'
                        : 'падение на ' . abs($customersChange) . '%'
                )
                ->label('Новые клиенты')
                ->descriptionIcon($customersChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart($customersChartData)
                ->color($customersChange >= 0 ? 'success' : 'danger'),
            Stat::make('New orders', $formatNumber($newOrders))
                ->description(
                    $ordersChange >= 0
                        ? 'увеличение на ' . abs($ordersChange) . '%'
                        : 'падение на ' . abs($ordersChange) . '%'
                )
                ->label('Новые заказы')
                ->descriptionIcon($ordersChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart($ordersChartData)
                ->color($ordersChange >= 0 ? 'success' : 'danger'),
        ];
    }
}
