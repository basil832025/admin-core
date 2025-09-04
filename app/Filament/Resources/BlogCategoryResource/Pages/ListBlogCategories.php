<?php

namespace App\Filament\Resources\BlogCategoryResource\Pages;

//use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;
//use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use App\Models\Language;
use Carbon\Carbon;
use Filament\Actions\CreateAction;
use App\Filament\Resources\BlogCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBlogCategories extends ListRecords
{
    //use Translatable;
    protected static string $resource = BlogCategoryResource::class;

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
