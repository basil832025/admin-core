<?php

namespace App\Filament\Resources\Shop\PaypartsRefundResource\Pages;

use App\Filament\Resources\Callcenter\OrderResource;
use App\Filament\Resources\Shop\PaypartsRefundResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPaypartsRefund extends ViewRecord
{
    protected static string $resource = PaypartsRefundResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Возврат')->schema([
                TextEntry::make('status')->label('Статус')->badge(),
                TextEntry::make('amount')->label('Сумма')->money('UAH'),
                TextEntry::make('order.number')
                    ->label('Заказ')
                    ->url(fn ($record) => $record->order
                        ? OrderResource::getUrl('edit', ['record' => $record->order])
                        : null)
                    ->openUrlInNewTab(),
                TextEntry::make('order_id')->label('Privat order ID')->copyable(),
                TextEntry::make('initiatedBy.name')->label('Инициатор')->placeholder('Система'),
                TextEntry::make('created_at')->label('Создан')->dateTime('d.m.Y H:i:s'),
                TextEntry::make('checked_at')->label('Проверен')->dateTime('d.m.Y H:i:s')->placeholder('—'),
                TextEntry::make('completed_at')->label('Завершён')->dateTime('d.m.Y H:i:s')->placeholder('—'),
                TextEntry::make('response_message')->label('Ошибка')->placeholder('—')->columnSpanFull(),
            ])->columns(2),
            Section::make('API')->schema([
                TextEntry::make('decline_request_payload')
                    ->label('Decline request')
                    ->state(fn ($record): string => $this->formatPayload($record->decline_request_payload))
                    ->copyable()->columnSpanFull(),
                TextEntry::make('decline_response_payload')
                    ->label('Decline response')
                    ->state(fn ($record): string => $this->formatPayload($record->decline_response_payload))
                    ->copyable()->columnSpanFull(),
                TextEntry::make('state_request_payload')
                    ->label('State request')
                    ->state(fn ($record): string => $this->formatPayload($record->state_request_payload))
                    ->copyable()->columnSpanFull(),
                TextEntry::make('state_response_payload')
                    ->label('State response')
                    ->state(fn ($record): string => $this->formatPayload($record->state_response_payload))
                    ->copyable()->columnSpanFull(),
            ]),
        ]);
    }

    private function formatPayload(?array $payload): string
    {
        return $payload
            ? (json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—')
            : '—';
    }
}
