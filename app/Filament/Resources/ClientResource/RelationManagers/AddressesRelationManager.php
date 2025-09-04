<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use Filament\Forms;

use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';
    protected static ?string $title = 'Addresses';


    public  function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('street')->required()->label('Улица'),
                    TextInput::make('house')->required()->label('Дом'),

                    TextInput::make('apartment')->label('Квартира'),
                    TextInput::make('intercom')->label('Домофон'),

                    TextInput::make('floor')->label('Этаж'),
                    TextInput::make('entrance')->label('Подъезд'),

                    TextInput::make('zip')->label('Почтовый индекс'),
                    TextInput::make('city')->label('Город'),

                    TextInput::make('country')->label('Страна'),
                    Select::make('type')
                        ->options([
                            'home' => 'Дом',
                            'work' => 'Работа',
                            'friends' => 'Друзья',
                        ])
                        ->label('Тип адреса'),

                    Toggle::make('is_private')->label('Частный дом'),
                    Textarea::make('note')
                        ->label('Примечание')
                        ->columnSpanFull(),
                ]),
            ]);
    }
    public  function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('street')->label('Улица'),
                TextColumn::make('house')->label('Дом'),
                TextColumn::make('apartment')->label('Квартира'),
                TextColumn::make('entrance')->label('Подезд'),
                TextColumn::make('floor')->label('Этаж'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
