<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\LoyaltyRuleResource\Pages;
use App\Filament\Resources\Shop\LoyaltyRuleResource\RelationManagers;
use App\Models\Shop\LoyaltyRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LoyaltyRuleResource extends Resource
{
    protected static ?string $model = LoyaltyRule::class;

    protected static ?string $navigationIcon   = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationLabel  = null;
    protected static ?string $pluralModelLabel = null;
    protected static ?string $modelLabel       = null;
    protected static ?string $navigationGroup  = null;

    public static function getNavigationGroup(): ?string
    {
        return __('loyalty_rule.nav.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('loyalty_rule.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('loyalty_rule.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loyalty_rule.nav.plural_model_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('loyalty_rule.sections.valid_period'))
                ->schema([
                    Forms\Components\DatePicker::make('valid_from')
                        ->label(__('loyalty_rule.fields.valid_from'))
                        ->required()
                        ->native(false)
                        ->helperText(__('loyalty_rule.helpers.valid_from')),
                ]),

            Forms\Components\Section::make(__('loyalty_rule.sections.regular_bonuses'))
                ->schema([
                    Forms\Components\TextInput::make('earn_percent')
                        ->label(__('loyalty_rule.fields.earn_percent'))
                        ->numeric()
                        ->required()
                        ->suffix('%')
                        ->helperText(__('loyalty_rule.helpers.earn_percent')),

                    Forms\Components\TextInput::make('earn_expire_days')
                        ->label(__('loyalty_rule.fields.earn_expire_days'))
                        ->numeric()
                        ->required()
                        ->helperText(__('loyalty_rule.helpers.earn_expire_days')),

                    Forms\Components\TextInput::make('min_order_sum_for_earn')
                        ->label(__('loyalty_rule.fields.min_order_sum_for_earn'))
                        ->numeric()
                        ->default(null)
                        ->helperText(__('loyalty_rule.helpers.min_order_sum_for_earn')),
                ])
                ->columns(3),

            Forms\Components\Section::make(__('loyalty_rule.sections.welcome_bonus'))
                ->schema([
                    Forms\Components\TextInput::make('welcome_bonus_amount')
                        ->label(__('loyalty_rule.fields.welcome_bonus_amount'))
                        ->numeric()
                        ->required()
                        ->helperText(__('loyalty_rule.helpers.welcome_bonus_amount')),

                    Forms\Components\TextInput::make('welcome_bonus_expire_days')
                        ->label(__('loyalty_rule.fields.welcome_bonus_expire_days'))
                        ->numeric()
                        ->required(),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('loyalty_rule.sections.other'))
                ->schema([
                    Forms\Components\Toggle::make('is_enabled')
                        ->label(__('loyalty_rule.fields.is_enabled'))
                        ->default(true)
                        ->helperText(__('loyalty_rule.helpers.is_enabled')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('valid_from')
                    ->label(__('loyalty_rule.columns.valid_from'))
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_enabled')
                    ->label(__('loyalty_rule.columns.is_enabled'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('earn_percent')
                    ->label(__('loyalty_rule.columns.earn_percent'))
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('earn_expire_days')
                    ->label(__('loyalty_rule.columns.earn_expire_days'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('welcome_bonus_amount')
                    ->label(__('loyalty_rule.columns.welcome_bonus_amount'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('welcome_bonus_expire_days')
                    ->label(__('loyalty_rule.columns.welcome_bonus_expire_days'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('min_order_sum_for_earn')
                    ->label(__('loyalty_rule.columns.min_order_sum_for_earn'))
                    ->money('UAH', divideBy: false)
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('valid_from', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(__('loyalty_rule.actions.edit')),
                Tables\Actions\DeleteAction::make()->label(__('loyalty_rule.actions.delete')),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label(__('loyalty_rule.actions.delete_bulk')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLoyaltyRules::route('/'),
            'create' => Pages\CreateLoyaltyRule::route('/create'),
            'edit'   => Pages\EditLoyaltyRule::route('/{record}/edit'),
        ];
    }
}
