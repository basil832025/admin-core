<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\PaypartsTransactionResource\Pages;
use App\Models\Shop\PaypartsTransaction;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaypartsTransactionResource extends Resource
{
    protected static ?string $model = PaypartsTransaction::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';
    protected static ?string $navigationLabel = 'Логи Оплати частинами';
    protected static ?string $pluralModelLabel = 'Логи Оплати частинами';
    protected static ?string $slug = 'shop/payparts-transactions';
    protected static ?int $navigationSort = 20;

    protected static function canAccessModule(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        return (method_exists($user, 'hasRole') && $user->hasRole(config('shield.super_admin.name', 'super_admin')))
            || $user->can('view_any_shop::payparts::transaction');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccessModule();
    }

    public static function canViewAny(): bool
    {
        return static::canAccessModule();
    }

    public static function form(Form $form): Form
    {
        return $form;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Час')->dateTime('d.m.Y H:i:s')->sortable(),
                TextColumn::make('order.number')->label('Замовлення'),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('bank.bank_type')->label('Банк')->formatStateUsing(fn (?string $state): string => $state ?: '—'),
                TextColumn::make('merchant_type')->label('Type'),
                TextColumn::make('parts_count')->label('Платежів'),
                TextColumn::make('amount')->label('Сума')->money('UAH'),
                TextColumn::make('order_id')->label('Order ID')->copyable(),
                TextColumn::make('token')->label('Token')->copyable()->toggleable(),
                TextColumn::make('response_message')->label('Повідомлення')->limit(80)->wrap(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaypartsTransactions::route('/'),
            'view' => Pages\ViewPaypartsTransaction::route('/{record}'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.shop');
    }
}
