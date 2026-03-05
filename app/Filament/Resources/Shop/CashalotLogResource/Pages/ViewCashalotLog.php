<?php

namespace App\Filament\Resources\Shop\CashalotLogResource\Pages;

use App\Filament\Resources\Shop\CashalotLogResource;
use App\Filament\Resources\Shop\OrderResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewCashalotLog extends ViewRecord
{
    protected static string $resource = CashalotLogResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Основне')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Дата')
                            ->dateTime('d.m.Y H:i'),
                        TextEntry::make('status')
                            ->label('Фіскалізація')
                            ->badge(),
                        TextEntry::make('num_fiscal')
                            ->label('Фіскальний №')
                            ->copyable(),
                        TextEntry::make('receipt_url')
                            ->label('Чек')
                            ->url(fn ($state) => $state)
                            ->openUrlInNewTab(),
                        TextEntry::make('order.number')
                            ->label('Замовлення')
                            ->url(fn ($record) => $record->order
                                ? OrderResource::getUrl('edit', ['record' => $record->order])
                                : null)
                            ->openUrlInNewTab(),
                        TextEntry::make('error_message')
                            ->label('Помилка фіскалізації')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Відправка клієнту')
                    ->schema([
                        TextEntry::make('consumer_status')
                            ->label('Статус')
                            ->badge(),
                        TextEntry::make('consumer_service_type')
                            ->label('Канал')
                            ->formatStateUsing(fn ($state): string => match ((int) $state) {
                                1 => 'Viber',
                                0 => 'SMS',
                                default => '—',
                            }),
                        TextEntry::make('consumer_phone')
                            ->label('Телефон')
                            ->placeholder('—'),
                        TextEntry::make('sent_to_consumer_at')
                            ->label('Дата відправки')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('consumer_error_code')
                            ->label('Код помилки')
                            ->placeholder('—'),
                        TextEntry::make('consumer_error_message')
                            ->label('Помилка відправки')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Payload')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('request_payload')
                            ->label('Request')
                            ->state(fn ($record): string => $record->request_payload
                                ? json_encode($record->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                                : '—')
                            ->columnSpanFull(),
                        TextEntry::make('response_payload')
                            ->label('Response')
                            ->state(fn ($record): string => $record->response_payload
                                ? json_encode($record->response_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                                : '—')
                            ->columnSpanFull(),
                        TextEntry::make('consumer_response_payload')
                            ->label('Consumer Response')
                            ->state(fn ($record): string => $record->consumer_response_payload
                                ? json_encode($record->consumer_response_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                                : '—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
