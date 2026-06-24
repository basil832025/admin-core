<?php

namespace App\Filament\Resources\Shop\LoyaltyAccountResource\RelationManagers;

use App\Filament\Resources\Callcenter\OrderResource as CallcenterOrderResource;
use App\Models\Shop\LoyaltyTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = null;

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('loyalty_account.transactions.title');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('type')
                ->label(__('loyalty_account.transactions.fields.type'))
                ->options([
                    LoyaltyTransaction::TYPE_ACCRUAL    => __('loyalty_account.transactions.types.accrual'),
                    LoyaltyTransaction::TYPE_SPEND      => __('loyalty_account.transactions.types.spend'),
                    LoyaltyTransaction::TYPE_EXPIRE     => __('loyalty_account.transactions.types.expire'),
                    LoyaltyTransaction::TYPE_ADJUSTMENT => __('loyalty_account.transactions.types.adjustment'),
                    LoyaltyTransaction::TYPE_REVERSE    => __('loyalty_account.transactions.types.reverse'),
                ])
                ->required(),

            Forms\Components\TextInput::make('source')
                ->label(__('loyalty_account.transactions.fields.source'))
                ->placeholder(__('loyalty_account.transactions.helpers.source')),

            Forms\Components\TextInput::make('amount')
                ->label(__('loyalty_account.transactions.fields.amount'))
                ->numeric()
                ->required()
                ->helperText(__('loyalty_account.transactions.helpers.amount')),

            Forms\Components\TextInput::make('remaining_amount')
                ->label(__('loyalty_account.transactions.fields.remaining_amount'))
                ->numeric()
                ->helperText(__('loyalty_account.transactions.helpers.remaining_amount')),

            Forms\Components\DateTimePicker::make('expires_at')
                ->label(__('loyalty_account.transactions.fields.expires_at'))
                ->seconds(false),

            Forms\Components\TextInput::make('order_id')
                ->label(__('loyalty_account.transactions.fields.order_id'))
                ->numeric()
                ->helperText(__('loyalty_account.transactions.helpers.order_id')),

            Forms\Components\KeyValue::make('meta')
                ->label(__('loyalty_account.transactions.fields.meta'))
                ->keyLabel(__('loyalty_account.transactions.fields.meta_key'))
                ->valueLabel(__('loyalty_account.transactions.fields.meta_value'))
                ->helperText(__('loyalty_account.transactions.helpers.meta')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('loyalty_account.transactions.columns.id'))
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label(__('loyalty_account.transactions.columns.type'))
                    ->colors([
                        'success' => LoyaltyTransaction::TYPE_ACCRUAL,
                        'danger'  => [LoyaltyTransaction::TYPE_SPEND, LoyaltyTransaction::TYPE_EXPIRE],
                        'warning' => [LoyaltyTransaction::TYPE_ADJUSTMENT, LoyaltyTransaction::TYPE_REVERSE],
                    ])
                    ->formatStateUsing(function (string $state): string {
                        return match ($state) {
                            LoyaltyTransaction::TYPE_ACCRUAL    => __('loyalty_account.transactions.types.accrual'),
                            LoyaltyTransaction::TYPE_SPEND      => __('loyalty_account.transactions.types.spend'),
                            LoyaltyTransaction::TYPE_EXPIRE     => __('loyalty_account.transactions.types.expire'),
                            LoyaltyTransaction::TYPE_ADJUSTMENT => __('loyalty_account.transactions.types.adjustment'),
                            LoyaltyTransaction::TYPE_REVERSE    => __('loyalty_account.transactions.types.reverse'),
                            default                             => $state,
                        };
                    }),

                Tables\Columns\TextColumn::make('source')
                    ->label(__('loyalty_account.transactions.columns.source'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label(__('loyalty_account.transactions.columns.amount'))
                    ->money('UAH', divideBy: false)
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label(__('loyalty_account.transactions.columns.remaining_amount'))
                    ->money('UAH', divideBy: false)
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('loyalty_account.transactions.columns.expires_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('order.number')
                    ->label(__('loyalty_account.transactions.columns.order_id'))
                    ->searchable()
                    ->formatStateUsing(fn (?string $state, LoyaltyTransaction $record): string => $state ?: (string) $record->order_id)
                    ->url(fn (LoyaltyTransaction $record): ?string => $record->order_id
                        ? CallcenterOrderResource::getUrl('edit', ['record' => $record->order_id])
                        : null)
                    ->openUrlInNewTab()
                    ->sortable(),

                Tables\Columns\TextColumn::make('order_id')
                    ->label(__('loyalty_account.transactions.columns.order_id'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('loyalty_account.transactions.columns.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                // Разрешаем ручное добавление (например, для корректировок админом)
                Tables\Actions\CreateAction::make()
                    ->label(__('loyalty_account.transactions.actions.create')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(__('loyalty_account.transactions.actions.edit')),
                Tables\Actions\DeleteAction::make()
                    ->label(__('loyalty_account.transactions.actions.delete')),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label(__('loyalty_account.transactions.actions.delete')),
            ]);
    }
}
