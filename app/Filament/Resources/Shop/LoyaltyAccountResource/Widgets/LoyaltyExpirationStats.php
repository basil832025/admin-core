<?php

namespace App\Filament\Resources\Shop\LoyaltyAccountResource\Widgets;

use App\Models\Shop\LoyaltyTransaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LoyaltyExpirationStats extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Сгорание бонусов';

    protected function getStats(): array
    {
        $baseQuery = LoyaltyTransaction::query()
            ->where('type', LoyaltyTransaction::TYPE_EXPIRE)
            ->where('source', 'system_expire');

        $todayQuery = (clone $baseQuery)->whereDate('created_at', today());
        $monthQuery = (clone $baseQuery)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);

        return [
            Stat::make('Сегодня сгорело', number_format(abs((float) $todayQuery->sum('amount')), 2, '.', ' '))
                ->description('Транзакций: ' . $todayQuery->count()),
            Stat::make('За месяц сгорело', number_format(abs((float) $monthQuery->sum('amount')), 2, '.', ' '))
                ->description('Транзакций: ' . $monthQuery->count()),
            Stat::make('Всего сгорело', number_format(abs((float) $baseQuery->sum('amount')), 2, '.', ' '))
                ->description('Всего транзакций: ' . $baseQuery->count()),
        ];
    }
}
