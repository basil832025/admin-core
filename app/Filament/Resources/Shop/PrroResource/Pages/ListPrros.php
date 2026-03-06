<?php

namespace App\Filament\Resources\Shop\PrroResource\Pages;

use App\Filament\Resources\Shop\PrroResource;
use App\Models\Shop\Prro;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListPrros extends ListRecords
{
    protected static string $resource = PrroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Активные')
                ->badge(Prro::query()->where('is_active', true)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('is_active', true)),

            'archived' => Tab::make('Архивные')
                ->badge(Prro::query()->where('is_active', false)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('is_active', false)),
        ];
    }
}
