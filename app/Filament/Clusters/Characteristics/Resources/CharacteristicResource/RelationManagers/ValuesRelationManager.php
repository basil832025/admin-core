<?php

namespace App\Filament\Clusters\Characteristics\Resources\CharacteristicResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
// ↓ вот эти два
use Filament\Forms\Form;
use Filament\Tables\Table;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions;

class ValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'values';
    protected static ?string $label        = 'Значения';

    // Сигнатура теперь корректна:
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('value')->label('Значение')->required(),
                TextInput::make('sort_order')->label('Позиция')->numeric()->default(0),
                Toggle::make('is_active')->label('Активно')->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('value')->label('Значение'),
                TextColumn::make('sort_order')->label('Позиция'),
                IconColumn::make('is_active')->label('Активно')->boolean(),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }
}
