<?php

namespace App\Filament\Resources\Shop\SmsLogResource\Pages;

use App\Filament\Resources\Shop\SmsLogResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewSmsLog extends ViewRecord
{
    protected static string $resource = SmsLogResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Основне')
                ->schema([
                    TextEntry::make('created_at')->label('Дата')->dateTime('d.m.Y H:i:s'),
                    TextEntry::make('message_type')->label('Тип'),
                    TextEntry::make('normalized_phone')->label('Телефон'),
                    TextEntry::make('client.name')->label('Клієнт')->placeholder('—'),
                    TextEntry::make('sender')->label('Sender')->placeholder('—'),
                    TextEntry::make('http_status')->label('HTTP')->placeholder('—'),
                    TextEntry::make('provider_status')->label('Статус провайдера')->placeholder('—'),
                    TextEntry::make('delivery_status')->label('Delivery')->placeholder('—'),
                    TextEntry::make('provider_request_id')->label('Request ID')->placeholder('—'),
                    TextEntry::make('delivery_checked_at')->label('Перевірено')->dateTime('d.m.Y H:i:s')->placeholder('—'),
                    TextEntry::make('message_preview')->label('Превʼю SMS')->placeholder('—'),
                    TextEntry::make('message_text')->label('Текст SMS')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('error_message')->label('Помилка')->placeholder('—')->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Context')
                ->collapsed()
                ->schema([
                    TextEntry::make('context')
                        ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                        ->columnSpanFull(),
                ]),
            Section::make('Payload')
                ->collapsed()
                ->schema([
                    TextEntry::make('provider_payload')
                        ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                        ->columnSpanFull(),
                ]),
            Section::make('Response')
                ->collapsed()
                ->schema([
                    TextEntry::make('provider_response')
                        ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
