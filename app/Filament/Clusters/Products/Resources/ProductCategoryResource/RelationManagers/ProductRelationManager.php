<?php

namespace App\Filament\Clusters\Products\Resources\ProductCategoryResource\RelationManagers;

use App\Models\Setting;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Filament\Clusters\Products\Resources\ProductResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;

class ProductRelationManager  extends RelationManager
{
protected static string $relationship = 'products'; // 👈 название метода-связи в модели Category
    public function form(Form $form): Form
    {
        return ProductResource::form($form);
    }
    public function table(Table $table): Table
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Название')
                    ->getStateUsing(fn ($record) => $record->getTranslation('title', $defaultLocale))
                    ->searchable(),
                TextColumn::make('price')->label('Цена')->sortable(),
                TextColumn::make('sku'),
                Tables\Columns\IconColumn::make('in_stock')->label('В наличии')->boolean(),
                TextColumn::make('Количество')
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make() ->after(function ($record, $data) {
                    $record->syncFromFormState($data);
                }),
            ])
            ->actions([
                Tables\Actions\EditAction::make() ->after(function ($record, $data) {
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
