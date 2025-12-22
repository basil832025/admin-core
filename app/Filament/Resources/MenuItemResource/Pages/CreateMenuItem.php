<?php

namespace App\Filament\Resources\MenuItemResource\Pages;

use App\Filament\Resources\MenuItemResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMenuItem extends CreateRecord
{
    protected static string $resource = MenuItemResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        //  dd($data);
        $data['menu_id'] = $this->currentMenuId();
        $data['parent_id'] = $data['parent_id'] ?? -1;

        return $data;
    }
}
