<?php

namespace App\Filament\Resources\BlogResource\Pages;

//use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;
//use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use App\Models\Language;
use Carbon\Carbon;
use Filament\Actions\CreateAction;
use App\Filament\Resources\BlogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBlogs extends ListRecords
{
   // use Translatable;
    protected static string $resource = BlogResource::class;

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

}
