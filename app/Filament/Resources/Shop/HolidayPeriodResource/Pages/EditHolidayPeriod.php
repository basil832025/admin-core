<?php

namespace App\Filament\Resources\Shop\HolidayPeriodResource\Pages;

use App\Filament\Resources\Shop\HolidayPeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHolidayPeriod extends EditRecord
{
    protected static string $resource = HolidayPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
