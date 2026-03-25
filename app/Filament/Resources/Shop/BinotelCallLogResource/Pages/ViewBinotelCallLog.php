<?php

namespace App\Filament\Resources\Shop\BinotelCallLogResource\Pages;

use App\Filament\Resources\Shop\BinotelCallLogResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewBinotelCallLog extends ViewRecord
{
    protected static string $resource = BinotelCallLogResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Основне')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Дата')
                            ->dateTime('d.m.Y H:i'),
                        TextEntry::make('event_type')
                            ->label('Подія')
                            ->badge(),
                        TextEntry::make('status')
                            ->label('Статус')
                            ->badge(),
                        TextEntry::make('caller_phone')
                            ->label('Телефон')
                            ->placeholder('—'),
                        TextEntry::make('client_name')
                            ->label('Імʼя')
                            ->placeholder('—'),
                        TextEntry::make('source_name')
                            ->label('Сайт')
                            ->placeholder('Основний сайт'),
                        TextEntry::make('point_name')
                            ->label('Точка')
                            ->placeholder('—'),
                        TextEntry::make('pbx_number')
                            ->label('Лінія (номер)')
                            ->placeholder('—'),
                        TextEntry::make('pbx_name')
                            ->label('Лінія (назва)')
                            ->placeholder('—'),
                        TextEntry::make('call_type')
                            ->label('Тип дзвінка')
                            ->placeholder('—'),
                        TextEntry::make('request_type')
                            ->label('Request type')
                            ->placeholder('—'),
                        TextEntry::make('general_call_id')
                            ->label('Call ID')
                            ->placeholder('—'),
                        TextEntry::make('internal_number')
                            ->label('Internal number')
                            ->placeholder('—'),
                        TextEntry::make('company_id')
                            ->label('Company ID')
                            ->placeholder('—'),
                        TextEntry::make('ip')
                            ->label('IP')
                            ->placeholder('—'),
                        TextEntry::make('crm_url')
                            ->label('CRM URL')
                            ->url(fn (?string $state): ?string => $state)
                            ->openUrlInNewTab()
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
                    ]),
            ]);
    }
}
