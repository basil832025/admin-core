<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\LoyaltyAccountResource\Pages;
use App\Filament\Resources\Shop\LoyaltyAccountResource\RelationManagers\TransactionsRelationManager;
use App\Models\Shop\LoyaltyAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LoyaltyAccountResource extends Resource
{
    protected static ?string $model = LoyaltyAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';
    protected static ?string $navigationLabel = null;
    protected static ?string $pluralModelLabel = null;
    protected static ?string $modelLabel = null;
    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('loyalty_account.nav.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('loyalty_account.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('loyalty_account.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loyalty_account.nav.plural_model_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('loyalty_account.sections.main'))
                ->schema([
                    Forms\Components\TextInput::make('id')
                        ->label(__('loyalty_account.fields.id'))
                        ->disabled(),

                    Forms\Components\TextInput::make('client_id')
                        ->label(__('loyalty_account.fields.client_id'))
                        ->disabled(),

                    Forms\Components\TextInput::make('phone')
                        ->label(__('loyalty_account.fields.phone'))
                        ->tel(),

                    Forms\Components\TextInput::make('balance')
                        ->label(__('loyalty_account.fields.balance'))
                        ->numeric()
                        ->disabled()
                        ->helperText(__('loyalty_account.helpers.balance')),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('loyalty_account.columns.id'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('client_id')
                    ->label(__('loyalty_account.columns.client_id'))
                    ->sortable()
                    ->toggleable(),

                // Если в модели Client есть поле name — можно будет раскомментировать:
                // Tables\Columns\TextColumn::make('client.name')
                //     ->label(__('loyalty_account.columns.client'))
                //     ->sortable()
                //     ->toggleable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label(__('loyalty_account.columns.phone'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('balance')
                    ->label(__('loyalty_account.columns.balance'))
                    ->sortable()
                    ->money('UAH', divideBy: false),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('loyalty_account.columns.updated_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label(__('loyalty_account.actions.view')),
                Tables\Actions\EditAction::make()
                    ->label(__('loyalty_account.actions.edit')),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label(__('loyalty_account.actions.delete')),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
        ];
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoyaltyAccounts::route('/'),
            'view'  => Pages\ViewLoyaltyAccount::route('/{record}'),
            'edit'  => Pages\EditLoyaltyAccount::route('/{record}/edit'),
        ];
    }
}
