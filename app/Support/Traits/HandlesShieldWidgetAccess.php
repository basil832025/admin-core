<?php

namespace App\Support\Traits;

use Illuminate\Support\Str;

trait HandlesShieldWidgetAccess
{
    public static function canView(): bool
    {
        $user = auth()->user();
        if (! $user) return false;
        $base = class_basename(static::class);
        $keys = [
            'widget_' . Str::of($base)->snake(), // widget_latest_orders
            'widget_' . $base,                    // widget_LatestOrders
        ];

        foreach ($keys as $key) {
            if ($user->can($key)) return true;
        }

        return false;
    }

    public static function canViewForRecord($record): bool
    {
        return static::canView();
    }
}
