<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Characteristics extends Cluster
{
    /**
     * Иконка в боковой навигации (Heroicons gear)
     */
    protected static ?string $navigationIcon = 'heroicon-o-cog';

    /**
     * Группа навигации, в которую попадёт кластер
     */
    protected static ?string $navigationGroup = 'Каталог';

    /**
     * Порядок сортировки кластера в меню
     */
    protected static ?int $navigationSort = 20;

    /**
     * Уникальный slug кластера, под которым ресурсы будут группироваться
     */
    protected static ?string $slug = 'catalog/characteristics';
}
