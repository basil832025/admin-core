<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\CashalotCommandLogResource\Pages;
use App\Models\Shop\CashalotCommandLog;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Forms\Form;

class CashalotCommandLogResource extends Resource
{
    protected static ?string $model = CashalotCommandLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Логи Cashalot (команди)';
    protected static ?string $pluralModelLabel = 'Логи Cashalot (команди)';
    protected static ?string $navigationGroup = null;
    protected static ?int $navigationSort = 98;

    protected static function canAccessModule(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        return (method_exists($user, 'hasRole') && $user->hasRole(config('shield.super_admin.name', 'super_admin')))
            || $user->can('view_any_shop::cashalot::command::log');
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
        return $form
            ->schema([
                Forms\Components\Section::make('Деталі')
                    ->schema([
                        Forms\Components\TextInput::make('command')->label('Команда')->disabled(),
                        Forms\Components\TextInput::make('status')->label('Статус')->disabled(),
                        Forms\Components\TextInput::make('prro_num_fiscal')->label('ПРРО')->disabled(),
                        Forms\Components\TextInput::make('result_num_fiscal')->label('NumFiscal')->disabled(),
                        Forms\Components\TextInput::make('shift_id')->label('ShiftId')->disabled(),
                        Forms\Components\TextInput::make('error_code')->label('ErrorCode')->disabled(),
                        Forms\Components\Textarea::make('error_message')->label('ErrorMessage')->rows(3)->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Payloads')
                    ->schema([
                        Forms\Components\Textarea::make('request_payload')
                            ->label('Request')
                            ->rows(12)
                            ->disabled()
                            ->formatStateUsing(fn ($state): string => is_array($state)
                                ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                                : (string) $state),
                        Forms\Components\Textarea::make('response_payload')
                            ->label('Response')
                            ->rows(12)
                            ->disabled()
                            ->formatStateUsing(fn ($state): string => is_array($state)
                                ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                                : (string) $state),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Час')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('command')
                    ->label('Команда')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('prro_num_fiscal')
                    ->label('ПРРО')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->sortable(),

                Tables\Columns\TextColumn::make('error_code')
                    ->label('ErrorCode')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('result_num_fiscal')
                    ->label('NumFiscal')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('shift_id')
                    ->label('ShiftId')
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashalotCommandLogs::route('/'),
            'view' => Pages\ViewCashalotCommandLog::route('/{record}'),
        ];
    }
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settings');
    }

}
