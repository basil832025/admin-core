<?php

namespace App\Filament\Clusters\Reference\Resources\LocationResource\Pages;

use App\Filament\Clusters\Reference\Resources\LocationResource;
use App\Models\Language;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLocations extends ListRecords
{
    protected static string $resource = LocationResource::class;
    protected static ?string $title = 'Точки (заведения)';

    protected function getHeaderActions(): array
    {

        $items = collect(static::getResource()::getActiveLocales()) // ['uk','en','ru', ...]
        ->map(function (string $code) {
            $label = Language::where('code', $code)->value('name') ?? strtoupper($code);

            return Actions\Action::make("locale-$code")
                ->label($label)
                ->icon('heroicon-m-language')
                ->action(function () use ($code) {
                    session(['locale' => $code]);
                    app()->setLocale($code);
                    Carbon::setLocale($code);
                    $this->dispatch('$refresh'); // Livewire v3
                });
        })
            ->all();

        return [
            Actions\ActionGroup::make($items)
                ->label(strtoupper(app()->getLocale()))
                ->icon('heroicon-m-language'),
            CreateAction::make(),
        ];
    }
}
