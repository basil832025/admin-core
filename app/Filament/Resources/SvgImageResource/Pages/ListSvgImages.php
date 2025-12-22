<?php

namespace App\Filament\Resources\SvgImageResource\Pages;

use App\Filament\Resources\SvgImageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSvgImages extends ListRecords
{
    protected static string $resource = SvgImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
