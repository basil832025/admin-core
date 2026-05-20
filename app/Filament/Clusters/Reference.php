<?php

namespace App\Filament\Clusters;

use Filament\Facades\Filament;
use Filament\Clusters\Cluster;

class Reference extends Cluster
{
    protected static function canAccessModule(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        return (method_exists($user, 'hasRole') && $user->hasRole(config('shield.super_admin.name', 'super_admin')))
            || $user->can('page_Reference');
    }

    public static function canAccess(): bool
    {
        return static::canAccessModule();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccessModule();
    }

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-book-open'; // или любая иконка из Heroicons или Lucide
    }
    protected static ?string $slug = 'reference/spr';
    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('reference.nav.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('reference.nav.navigation_label');
    }

}
