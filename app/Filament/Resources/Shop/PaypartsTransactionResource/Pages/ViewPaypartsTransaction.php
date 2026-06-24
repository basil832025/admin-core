<?php

namespace App\Filament\Resources\Shop\PaypartsTransactionResource\Pages;

use App\Filament\Resources\Callcenter\OrderResource;
use App\Filament\Resources\Shop\PaypartsTransactionResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPaypartsTransaction extends ViewRecord
{
    protected static string $resource = PaypartsTransactionResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Main')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Date')
                            ->dateTime('d.m.Y H:i:s'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                        TextEntry::make('order.number')
                            ->label('Order')
                            ->url(fn ($record) => $record->order
                                ? OrderResource::getUrl('edit', ['record' => $record->order])
                                : null)
                            ->openUrlInNewTab()
                            ->placeholder('-'),
                        TextEntry::make('bank.bank_type')
                            ->label('Bank')
                            ->placeholder('-'),
                        TextEntry::make('merchant_type')
                            ->label('Merchant type')
                            ->placeholder('-'),
                        TextEntry::make('parts_count')
                            ->label('Parts')
                            ->placeholder('-'),
                        TextEntry::make('amount')
                            ->label('Amount')
                            ->money('UAH'),
                        TextEntry::make('order_id')
                            ->label('Privat order ID')
                            ->copyable()
                            ->placeholder('-'),
                        TextEntry::make('token')
                            ->label('Token')
                            ->copyable()
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('response_message')
                            ->label('Message')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('response_code')
                            ->label('Code')
                            ->placeholder('-'),
                        TextEntry::make('customer_phone')
                            ->label('Customer phone')
                            ->copyable()
                            ->placeholder('-'),
                        TextEntry::make('customer_email')
                            ->label('Customer email')
                            ->placeholder('-'),
                        TextEntry::make('customer_locale')
                            ->label('Locale')
                            ->placeholder('-'),
                        TextEntry::make('redirect_url')
                            ->label('Redirect URL')
                            ->copyable()
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('response_url')
                            ->label('Response URL')
                            ->copyable()
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Payload')
                    ->schema([
                        TextEntry::make('request_payload')
                            ->label('Request')
                            ->state(fn ($record): string => $this->formatPayload($record->request_payload))
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('response_payload')
                            ->label('Response')
                            ->state(fn ($record): string => $this->formatPayload($record->response_payload))
                            ->copyable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private function formatPayload(?array $payload): string
    {
        if (! $payload) {
            return '-';
        }

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '-';
    }
}
