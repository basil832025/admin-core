<?php

namespace App\Filament\Resources;
use App\Filament\Resources\ClientResource\RelationManagers\AddressesRelationManager;
use App\Models\Shop\Client;

use App\Filament\Resources\ClientResource\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Resources\Resource;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Illuminate\Support\Str;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Магазин';
    protected static ?string $navigationLabel = 'Клиенты';
    protected static ?string $modelLabel = 'Клиент';
    protected static ?string $pluralModelLabel = 'Клиенты';
    public static function form(Form $form): Form
    {
        return $form ->schema([
            Forms\Components\Section::make()
                ->schema([
            TextInput::make('name')->required()->label('ФИО'),
            TextInput::make('phone')->required()->tel(),
            TextInput::make('email')->email(),
            DatePicker::make('birthday')->label('Дата рождения'),
            Select::make('gender')
                ->options([
                    'male' => 'Мужчина',
                    'female' => 'Женщина',
                ])
                ->nullable(),
            TextInput::make('password')
                ->password()
                ->label('Пароль')
                ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : null)
                ->dehydrated(fn ($state) => filled($state)),
              //  ->required(fn (string $context): bool => $context === 'create'),
            FileUpload::make('photo')->image()->directory('clients')->label('Фото'),
            Textarea::make('note')->label('Примечание'),
            Toggle::make('is_active')->label('Активен')->default(true),
                ])
                ->columns(2)
                ->columnSpan(['lg' => fn (?Client $record) => $record === null ? 3 : 2]),

            Forms\Components\Section::make()
                ->schema([
                    Forms\Components\Placeholder::make('created_at')
                        ->label('Created at')
                        ->content(fn (Client $record): ?string => $record->created_at?->diffForHumans()),

                    Forms\Components\Placeholder::make('updated_at')
                        ->label('Last modified at')
                        ->content(fn (Client $record): ?string => $record->updated_at?->diffForHumans()),
                ])
                ->columnSpan(['lg' => 1])
                ->hidden(fn (?Client $record) => $record === null),
        ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->label('ФИО'),
                TextColumn::make('phone')->label('Телефон'),
                TextColumn::make('email'),
                TextColumn::make('gender')->label('Пол'),
                BooleanColumn::make('is_active')->label('Активен'),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
    public static function getRelations(): array
    {
        return [
            AddressesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
