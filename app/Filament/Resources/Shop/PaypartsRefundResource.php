<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\PaypartsRefundResource\Pages;
use App\Models\Shop\PaypartsRefund;
use App\Services\PrivatBankPaypartsRefundService;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaypartsRefundResource extends Resource
{
    protected static ?string $model = PaypartsRefund::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';
    protected static ?string $navigationLabel = 'Возвраты Оплаты частями';
    protected static ?string $pluralModelLabel = 'Возвраты Оплаты частями';
    protected static ?string $slug = 'shop/payparts-refunds';
    protected static ?int $navigationSort = 21;

    protected static function canAccessModule(): bool
    {
        $user = Filament::auth()->user();

        return $user && (
            (method_exists($user, 'hasRole') && $user->hasRole(config('shield.super_admin.name', 'super_admin')))
            || $user->can('refund_payparts_payment')
            || $user->can('view_any_shop::payparts::transaction')
        );
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
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Создан')->dateTime('d.m.Y H:i:s')->sortable(),
                TextColumn::make('order.number')->label('Заказ')->searchable(),
                TextColumn::make('order_id')->label('Order ID')->copyable()->searchable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'refunded' => 'success',
                        'refund_failed' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('amount')->label('Сумма')->money('UAH'),
                TextColumn::make('initiatedBy.name')->label('Инициатор')->placeholder('Система'),
                TextColumn::make('checked_at')->label('Проверен')->dateTime('d.m.Y H:i:s')->placeholder('—'),
                TextColumn::make('response_message')->label('Сообщение')->limit(80)->wrap()->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('sync_refund')
                    ->label('Проверить')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (PaypartsRefund $record): bool => $record->status === 'refund_pending')
                    ->action(function (PaypartsRefund $record): void {
                        try {
                            $refund = PrivatBankPaypartsRefundService::make()->sync($record);
                            Notification::make()
                                ->title($refund->status === 'refunded'
                                    ? 'Возврат подтверждён банком'
                                    : 'Возврат ещё обрабатывается')
                                ->{$refund->status === 'refunded' ? 'success' : 'warning'}()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Не удалось проверить возврат')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaypartsRefunds::route('/'),
            'view' => Pages\ViewPaypartsRefund::route('/{record}'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.shop');
    }
}
