<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Spatie\Permission\Models\Role;
use App\Support\AdminStartPage;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    // protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Настройки';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = null;
    protected static ?string $modelLabel = null;
    protected static ?string $pluralModelLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('user.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('user.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('user.nav.plural_model_label');
    }
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label(__('user.fields.name'))
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('email')
                ->label(__('user.fields.email'))
                ->email()
                ->required()
                ->unique(ignoreRecord: true),

            Forms\Components\TextInput::make('password')
                ->label(__('user.fields.password'))
                ->password()
                ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                ->dehydrated(fn ($state) => filled($state)) // 👈 добавляем это
                ->required(fn (string $context) => $context === 'create')
                ->maxLength(255),

            // выбор ролей
            Forms\Components\Select::make('roles')
                ->label(__('user.fields.roles'))
                ->multiple()
                ->relationship('roles', 'name')
                ->preload()
                ->searchable(),

            Forms\Components\Select::make('admin_start_page')
                ->label(__('admin_start_page.label_user'))
                ->options(AdminStartPage::options())
                ->searchable()
                ->nullable()
                ->helperText(__('admin_start_page.help_user')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('user.columns.name'))->sortable()->searchable(),
                Tables\Columns\TextColumn::make('email')->label(__('user.columns.email'))->sortable()->searchable(),
                Tables\Columns\TagsColumn::make('roles.name')->label(__('user.columns.roles')),
                Tables\Columns\TextColumn::make('created_at')->label(__('user.columns.created_at'))->dateTime(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
