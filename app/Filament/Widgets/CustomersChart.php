<?php

namespace App\Filament\Widgets;

use App\Models\Shop\Client;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Str;
use App\Support\Traits\HandlesShieldWidgetAccess;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class CustomersChart extends ChartWidget
{
    use HandlesShieldWidgetAccess;
    protected static ?string $heading = 'Всего клиентов';

    protected static ?int $sort = 2;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        if (! $user || !$user instanceof \App\Models\User) return false;

        // Две возможные схемы именования у Shield:
        $keys = [
            // из slug страницы: "general-settings" -> "page_general_settings"
            'page_' . Str::of(static::getSlug())->snake(),
            // из имени класса: "GeneralSettings" -> "page_GeneralSettings"
            'page_' . class_basename(static::class),
        ];

        foreach ($keys as $key) {
            if ($user->can($key)) {
                return true;
            }
        }
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
    
    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        // Получаем данные по месяцам за текущий год
        $customerData = Trend::model(Client::class)
            ->between(
                start: now()->startOfYear(),
                end: now()->endOfYear(),
            )
            ->perMonth()
            ->count();

        // Вычисляем накопительную сумму (всего клиентов на конец каждого месяца)
        $cumulativeData = [];
        $total = 0;
        
        // Сначала получаем общее количество клиентов до начала года
        $totalBeforeYear = Client::where('created_at', '<', now()->startOfYear())->count();
        $total = $totalBeforeYear;
        
        foreach ($customerData as $value) {
            $total += (int) $value->aggregate;
            $cumulativeData[] = $total;
        }

        $labels = $customerData->map(fn (TrendValue $value) => 
            \Carbon\Carbon::parse($value->date)->translatedFormat('M')
        )->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Клиенты',
                    'data' => $cumulativeData,
                    'fill' => 'start',
                ],
            ],
            'labels' => $labels,
        ];
    }
}
