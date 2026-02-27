<?php

namespace App\Filament\Clusters\Reference\Resources;

use App\Filament\Clusters\Reference;
use App\Filament\Clusters\Reference\Resources\ClientGroupResource\Pages;
use App\Models\Language;
use App\Models\Setting;
use App\Models\Shop\ClientGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use SolutionForest\FilamentTranslateField\Forms\Component\Translate;

class ClientGroupResource extends Resource
{
    use Translatable;

    protected static ?string $model = ClientGroup::class;
    protected static ?string $cluster = Reference::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Группы клиентов';
    protected static ?string $modelLabel = 'Группа клиента';
    protected static ?string $pluralModelLabel = 'Группы клиентов';
    protected static ?int $navigationSort = 11;
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function form(Form $form): Form
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        $locales = static::getActiveLocales();

        return $form->schema([
            Forms\Components\Section::make('Основное')
                ->schema([
                    Translate::make()
                        ->locales($locales)
                        ->prefixLocaleLabel()
                        ->columns(1)
                        ->columnSpanFull()
                        ->schema(fn (string $locale) => [
                            Forms\Components\TextInput::make('name')
                                ->label('Название')
                                ->required($locale === $defaultLocale)
                                ->maxLength(255),
                        ]),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Активность статуса')
                        ->default(true),

                    Forms\Components\Toggle::make('is_blacklist')
                        ->label('Черный список')
                        ->default(false),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->formatStateUsing(fn (ClientGroup $record): string => $record->display_name)
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активность')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_blacklist')
                    ->label('Черный список')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientGroups::route('/'),
            'create' => Pages\CreateClientGroup::route('/create'),
            'edit' => Pages\EditClientGroup::route('/{record}/edit'),
        ];
    }

    public static function getActiveLocales(): array
    {
        return Language::where('active', true)
            ->orderBy('position')
            ->pluck('code')
            ->map(fn ($code) => strtolower((string) $code))
            ->values()
            ->all();
    }
}
