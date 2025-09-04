<?php

namespace App\Filament\Clusters\Reference\Resources;

use App\Filament\Clusters\Reference;
use App\Filament\Clusters\Reference\Resources\CurrencyResource\Pages;
use App\Filament\Clusters\Reference\Resources\CurrencyResource\RelationManagers;
use App\Models\Currency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Pages\SubNavigationPosition;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;
    protected static ?string $cluster = Reference::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Валюты';
    protected static ?string $modelLabel = 'Валюты';
    protected static ?string $navigationLabel = 'Валюты';
    protected static ?string $pluralModelLabel = 'Валюты';
    protected static ?int    $navigationSort  = 3;
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;






    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-currency-dollar';
    }
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->label('Название валюты')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('code')->label('Код валюты')
                    ->required()
                    ->maxLength(5),
                Forms\Components\TextInput::make('position')->label('Позиция для сортировки')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('active')->label('Активность')->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Название валюты')
                    ->searchable(),
                Tables\Columns\TextColumn::make('code')->label('Код валюты')
                    ->searchable(),
                Tables\Columns\TextColumn::make('position')->label('Позиция для сортировки')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')->label('Активность')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('Создано')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->label('Модификация')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCurrencies::route('/'),
            'create' => Pages\CreateCurrency::route('/create'),
            'edit' => Pages\EditCurrency::route('/{record}/edit'),
        ];
    }
}
