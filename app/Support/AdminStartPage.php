<?php

namespace App\Support;

use App\Filament\Resources\Callcenter\OrderResource as CallcenterOrderResource;
use App\Filament\Resources\Logistics\OrderResource as LogisticsOrderResource;
use App\Filament\Resources\Shop\OrderResource as ShopOrderResource;
use App\Models\User;

class AdminStartPage
{
    public const DASHBOARD = 'dashboard';
    public const LOGISTICS_ORDERS = 'logistics_orders';
    public const CALLCENTER_ORDERS = 'callcenter_orders';
    public const SHOP_ORDERS = 'shop_orders';

    public static function options(): array
    {
        return [
            self::DASHBOARD => __('admin_start_page.options.dashboard'),
            self::LOGISTICS_ORDERS => __('admin_start_page.options.logistics_orders'),
            self::CALLCENTER_ORDERS => __('admin_start_page.options.callcenter_orders'),
            self::SHOP_ORDERS => __('admin_start_page.options.shop_orders'),
        ];
    }

    public static function resolveForCurrentUser(): string
    {
        $user = auth('admin')->user();

        if (! $user instanceof User) {
            return self::urlFor(self::DASHBOARD);
        }

        $preferred = self::normalize($user->admin_start_page ?? null);
        $rolePreferred = self::normalize(
            $user->roles()->whereNotNull('admin_start_page')->orderBy('id')->value('admin_start_page')
        );

        $keys = array_values(array_filter([
            $preferred,
            $rolePreferred,
            self::LOGISTICS_ORDERS,
            self::CALLCENTER_ORDERS,
            self::SHOP_ORDERS,
            self::DASHBOARD,
        ]));

        foreach ($keys as $key) {
            if (self::canAccess($user, $key)) {
                return self::urlFor($key);
            }
        }

        return self::urlFor(self::DASHBOARD);
    }

    public static function normalize(null|string $key): ?string
    {
        if (! $key) {
            return null;
        }

        return array_key_exists($key, self::options()) ? $key : null;
    }

    protected static function canAccess(User $user, string $key): bool
    {
        return match ($key) {
            self::DASHBOARD => true,
            self::LOGISTICS_ORDERS => self::hasAnyPermission($user, [
                'access_logistics_orders',
                'view_any_logistics::order',
                'view_logistics::order',
                'view_any_shop::order',
                'view_shop::order',
                'view_any_order',
                'view_order',
            ]),
            self::CALLCENTER_ORDERS => self::hasAnyPermission($user, [
                'access_callcenter_orders',
                'view_any_callcenter::order',
                'view_callcenter::order',
                'view_any_shop::order',
                'view_shop::order',
                'view_any_order',
                'view_order',
            ]),
            self::SHOP_ORDERS => self::hasAnyPermission($user, [
                'view_any_shop::order',
                'view_shop::order',
                'view_any_order',
                'view_order',
            ]),
            default => false,
        };
    }

    protected static function hasAnyPermission(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    protected static function urlFor(string $key): string
    {
        try {
            return match ($key) {
                self::LOGISTICS_ORDERS => LogisticsOrderResource::getUrl('index'),
                self::CALLCENTER_ORDERS => CallcenterOrderResource::getUrl('index'),
                self::SHOP_ORDERS => ShopOrderResource::getUrl('index'),
                default => route('filament.admin.pages.dashboard'),
            };
        } catch (\Throwable $e) {
            return route('filament.admin.pages.dashboard');
        }
    }
}
