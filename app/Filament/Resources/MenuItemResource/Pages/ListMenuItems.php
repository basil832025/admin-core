<?php

namespace App\Filament\Resources\MenuItemResource\Pages;

use App\Filament\Resources\MenuItemResource;
use App\Models\Language;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\MenuResource;
class ListMenuItems extends ListRecords
{
    protected static string $resource = MenuItemResource::class;

    protected function getHeaderActions(): array
    {
        $items = collect(\App\Models\Setting::getActiveLocales()) // ['uk','en','ru', ...]
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
            Actions\CreateAction::make(),
        ];
    }
    public function mount(): void
    {
        // сразу уводим пользователя к списку Меню
        $this->redirect(MenuResource::getUrl('index'));
    }
}
