<?php

namespace App\Filament\Clusters\Products\Resources\ProductCategoryResource\RelationManagers;

use App\Models\Setting;
use Filament\Forms\Form;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use App\Filament\Clusters\Products\Resources\ProductResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class ProductRelationManager extends RelationManager
{
    protected static string $relationship = 'products'; // метод связи в модели Category

    public function form(Form $form): Form
    {
        return ProductResource::form($form);
    }

    public function table(Table $table): Table
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');

        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query
                    ->orderByRaw('COALESCE(parent_id, id) asc')
                    ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END asc')
                    ->orderBy('sort')
                    ->orderBy('id');
            })
            ->columns([
                TextColumn::make('title')
                    ->label(__('product.columns.title'))->sortable()->searchable()
                    ->getStateUsing(function (\App\Models\Shop\Product $record, TextColumn $column, $livewire) use ($defaultLocale) {
                        if (!empty($record->short_name)) {
                            return $record->short_name;
                        }
                        //  dd($lang);
                        return $record->getTranslation('title', $defaultLocale);

                    })
                    //->getStateUsing(fn ($record) => $record->getTranslation('title', $defaultLocale))
                    ->searchable(),

                TextInputColumn::make('price')
                    ->type('number')   // HTML5 number
                    ->step('1')
                    ->rules(['numeric','min:0']) // валидация на сохранение
                    ->alignRight()
                    ->label(__('product.columns.price'))
                    ->sortable(),

                TextColumn::make('sku')
                    ->label(__('product.columns.sku'))
                    ->searchable(),

                Tables\Columns\IconColumn::make('in_stock')
                    ->label(__('product.columns.in_stock'))
                    ->boolean(),

                TextColumn::make('quantity')
                    ->label(__('product.columns.quantity')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(function ($record, $data) {
                        $record->syncFromFormState($data);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function ($record, $data) {
                        $record->syncFromFormState($data);
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->groupedBulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    protected function afterSave(): void
    {
        $this->record->syncFromFormState($this->form->getState());
    }
}
