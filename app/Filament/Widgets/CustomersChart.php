<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Str;
use App\Support\Traits\HandlesShieldWidgetAccess;
class CustomersChart extends ChartWidget
{
    use HandlesShieldWidgetAccess;
    protected static ?string $heading = 'Всего клиентов';

    protected static ?int $sort = 2;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) return false;

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
        return [
            'datasets' => [
                [
                    'label' => 'Клиенты',
                    'data' => [4344, 5676, 6798, 7890, 8987, 9388, 10343, 10524, 13664, 14345, 15753, 17332],
                    'fill' => 'start',
                ],
            ],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
    }
}
