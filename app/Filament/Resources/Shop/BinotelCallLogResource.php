<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\BinotelCallLogResource\Pages;
use App\Models\Callcenter\Source;
use App\Models\Shop\BinotelCallLog;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class BinotelCallLogResource extends Resource
{
    protected static ?string $model = BinotelCallLog::class;

    protected static ?string $navigationGroup = null;
    protected static ?string $navigationIcon = 'heroicon-o-phone-arrow-down-left';
    protected static ?string $navigationLabel = 'Логи Binotel';
    protected static ?string $pluralModelLabel = 'Логи Binotel';
    protected static ?string $slug = 'shop/binotel-call-logs';
    protected static ?int $navigationSort = 22;

    protected static function canAccessModule(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        return (method_exists($user, 'hasRole') && $user->hasRole(config('shield.super_admin.name', 'super_admin')))
            || $user->can('view_any_binotel_call_log');
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
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('event_type')
                    ->label('Подія')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'call_settings' => 'call_settings',
                        'call_completed' => 'call_completed',
                        default => (string) $state,
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'call_settings' => 'info',
                        'call_completed' => 'success',
                        default => 'secondary',
                    }),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'accepted', 'success' => 'success',
                        'invalid_request_type' => 'warning',
                        'forbidden' => 'danger',
                        default => 'secondary',
                    }),

                TextColumn::make('caller_phone')
                    ->label('Телефон')
                    ->copyable(),

                TextColumn::make('client_name')
                    ->label('Імʼя')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('source_name')
                    ->label('Сайт')
                    ->badge()
                    ->color('primary')
                    ->placeholder('Основний сайт')
                    ->searchable(),

                TextColumn::make('pbx_number')
                    ->label('Лінія (номер)')
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('pbx_name')
                    ->label('Лінія (назва)')
                    ->limit(40)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(),

                TextColumn::make('point_name')
                    ->label('Точка')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('request_type')
                    ->label('Request type')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('general_call_id')
                    ->label('Call ID')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('event_type')
                    ->label('Подія')
                    ->options([
                        'call_settings' => 'call_settings',
                        'call_completed' => 'call_completed',
                    ]),

                SelectFilter::make('status')
                    ->label('Статус')
                    ->multiple()
                    ->options([
                        'accepted' => 'accepted',
                        'success' => 'success',
                        'forbidden' => 'forbidden',
                        'invalid_request_type' => 'invalid_request_type',
                    ]),

                SelectFilter::make('source_id')
                    ->label('Сайт')
                    ->options(fn (): array => Source::query()->orderBy('name')->pluck('name', 'id')->toArray()),

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
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBinotelCallLogs::route('/'),
            'view' => Pages\ViewBinotelCallLog::route('/{record}'),
        ];
    }

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form;
    }
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.shop');
    }

}
