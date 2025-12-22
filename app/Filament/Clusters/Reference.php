<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Reference extends Cluster
{
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

