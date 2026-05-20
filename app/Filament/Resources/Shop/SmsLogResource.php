<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\SmsLogResource\Pages;
use App\Models\Shop\SmsLog;
use App\Services\Sms\EsputnikSms;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use LogicException;

class SmsLogResource extends Resource
{
    protected static ?string $model = SmsLog::class;

    protected static ?string $navigationGroup = 'Магазин';
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Логи SMS';
    protected static ?string $pluralModelLabel = 'Логи SMS';
    protected static ?string $slug = 'shop/sms-logs';
    protected static ?int $navigationSort = 24;

    protected static function canAccessModule(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        return (method_exists($user, 'hasRole') && $user->hasRole(config('shield.super_admin.name', 'super_admin')))
            || $user->can('view_any_sms_log');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccessModule();
    }

    public static function canViewAny(): bool
    {
        return static::canAccessModule();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                TextColumn::make('message_type')
                    ->label('Тип')
                    ->badge()
                    ->sortable(),
                TextColumn::make('normalized_phone')
                    ->label('Телефон')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('client.name')
                    ->label('Клієнт')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('sender')
                    ->label('Sender')
                    ->toggleable(),
                TextColumn::make('message_text')
                    ->label('Текст SMS')
                    ->limit(50)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('http_status')
                    ->label('HTTP')
                    ->badge()
                    ->color(fn (?string $state) => ($state !== null && (int) $state < 300) ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('provider_status')
                    ->label('Статус провайдера')
                    ->badge()
                    ->color(fn (?string $state) => match (strtoupper((string) $state)) {
                        'OK', 'DELIVERED' => 'success',
                        'PENDING', 'PROCESSING' => 'warning',
                        'FAILED', 'ERROR', 'UNDELIVERED', 'REJECTED' => 'danger',
                        default => 'secondary',
                    })
                    ->sortable(),
                TextColumn::make('delivery_status')
                    ->label('Delivery')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('provider_request_id')
                    ->label('Request ID')
                    ->copyable()
                    ->toggleable(),
                IconColumn::make('success')
                    ->label('OK')
                    ->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('message_type')
                    ->label('Тип')
                    ->options([
                        'login' => 'login',
                        'register' => 'register',
                        'password_reset' => 'password_reset',
                    ]),
                SelectFilter::make('success')
                    ->label('Успіх')
                    ->options([
                        '1' => 'Так',
                        '0' => 'Ні',
                    ]),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('З'),
                        DatePicker::make('until')->label('По'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'З ' . Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'По ' . Carbon::parse($data['until'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('checkStatus')
                    ->label('Отримати статус')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (SmsLog $record, EsputnikSms $sms): void {
                        try {
                            $sms->checkStatus($record);

                            Notification::make()
                                ->success()
                                ->title('Статус оновлено')
                                ->send();
                        } catch (LogicException $e) {
                            Notification::make()
                                ->warning()
                                ->title($e->getMessage())
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Не вдалося отримати статус')
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
            'index' => Pages\ListSmsLogs::route('/'),
            'view' => Pages\ViewSmsLog::route('/{record}'),
        ];
    }

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form;
    }
}
