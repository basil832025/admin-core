<?php

namespace App\Filament\Resources\Shop\LiqPayLogResource\Pages;

use App\Filament\Resources\Shop\LiqPayLogResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;

class ViewLiqPayLog extends ViewRecord
{
    protected static string $resource = LiqPayLogResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Основне')
                    ->schema([
                        TextEntry::make('log_date')->label('Дата')->dateTime('d.m.Y H:i'),
                        TextEntry::make('status'),
                        TextEntry::make('action'),
                        TextEntry::make('amount')->formatStateUsing(
                            fn ($state, $record) =>
                                number_format((float)$state, 2, ',', ' ')
                                . ' ' . ($record->currency ?? 'UAH')
                        ),
                        TextEntry::make('order.number')
                            ->label('Заказ')
                            ->url(fn ($record) =>
                            $record->order
                                ? \App\Filament\Resources\Shop\OrderResource::getUrl('edit', [
                                'record' => $record->order,
                            ])
                                : null
                            )
                            ->openUrlInNewTab(),
                    ]),
                Section::make('Відправник')
                    ->schema([
                        TextEntry::make('sender_phone'),
                        TextEntry::make('sender_first_name'),
                        TextEntry::make('sender_last_name'),
                        TextEntry::make('sender_card_mask2'),
                        TextEntry::make('sender_card_bank'),
                        TextEntry::make('sender_card_type'),
                        TextEntry::make('sender_card_country'),
                    ]),
                Section::make('Сирий payload')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('payload')
                            ->formatStateUsing(fn ($state) =>
                            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                            )
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
