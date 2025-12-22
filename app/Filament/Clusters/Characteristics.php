<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Characteristics extends Cluster
{


    /**
     * Иконка в боковой навигации (Heroicons gear)
     */
    protected static ?string $navigationIcon = 'heroicon-o-cog';
    public static function getNavigationLabel(): string
    {
        return __('characteristic.nav.navigation_label');
    }

    public static function getClusterBreadcrumb(): string
    {
        return __('characteristic.nav.navigation_label');
    }

    /**
     * Группа навигации, в которую попадёт кластер
     */
    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('characteristic.nav.navigation_group');
    }

    /**
     * Порядок сортировки кластера в меню
     */
    //   protected static ?int $navigationSort = 20;
    // protected static ?string $cluster = Characteristics::class;
    /**
     * Уникальный slug кластера, под которым ресурсы будут группироваться
     */
    protected static ?string $slug = 'catalog/characteristics';
    // Здесь перечисляем все ресурсы, которые должны быть внутри этого кластера:
    /*protected static array $resources = [
        CharacteristicCategoryResource::class,
        CharacteristicResource::class,
        VariationResource::class,
    ];*/
}
