<?php

namespace App\Filament\Resources\Shop\HolidayPeriodResource\Pages;

use App\Filament\Resources\Shop\HolidayPeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHolidayPeriods extends ListRecords
{
    protected static string $resource = HolidayPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
