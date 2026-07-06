<?php

namespace App\Filament\Clusters\Products\Resources\ProductResource\Pages;

// use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;
// use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use App\Filament\Clusters\Products\Resources\ProductResource;
use App\Models\Language;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    //   use Translatable;
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        // Кэшируем список языков на 1 час для оптимизации
        $languages = cache()->remember('active_languages_map', 3600, function () {
            return Language::where('active', true)
                ->pluck('name', 'code')
                ->mapWithKeys(fn ($name, $code) => [strtolower($code) => $name])
                ->toArray();
        });

        $items = collect(static::getResource()::getActiveLocales()) // ['uk','en','ru', ...]
            ->map(function (string $code) use ($languages) {
                $label = $languages[$code] ?? strtoupper($code);

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
