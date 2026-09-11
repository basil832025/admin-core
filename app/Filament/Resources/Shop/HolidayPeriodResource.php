<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\HolidayPeriodResource\Pages;
use App\Models\Setting;
use App\Models\Shop\HolidayPeriod;
use Filament\Forms;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use SolutionForest\FilamentTranslateField\Forms\Component\Translate;

class HolidayPeriodResource extends Resource
{
    use Translatable;

    protected static ?string $model = HolidayPeriod::class;
    protected static ?string $navigationGroup = null;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = null;
    protected static ?string $pluralModelLabel = null;
    protected static ?string $modelLabel = null;
    protected static ?int $navigationSort = 45;

    public static function getNavigationGroup(): ?string
    {
        return __('holiday_period.nav.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('holiday_period.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('holiday_period.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('holiday_period.nav.plural_model_label');
    }

    public static function form(Form $form): Form
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        $locales = Setting::getActiveLocales();

        return $form->schema([
            Forms\Components\Section::make(__('holiday_period.sections.main'))
                ->columns(3)
                ->schema([
                    Forms\Components\DatePicker::make('date_from')
                        ->label(__('holiday_period.fields.date_from'))
                        ->native(false)
                        ->required()
                        ->live(),
                    Forms\Components\DatePicker::make('date_to')
                        ->label(__('holiday_period.fields.date_to'))
                        ->native(false)
                        ->required()
                        ->minDate(fn (Forms\Get $get) => $get('date_from')),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('holiday_period.fields.is_active'))
                        ->default(true)
                        ->inline(false),
                    Translate::make()
                        ->locales($locales)
                        ->prefixLocaleLabel()
                        ->columns(1)
                        ->columnSpanFull()
                        ->schema(fn (string $locale) => [
                            RichEditor::make('comment')
                                ->label(__('holiday_period.fields.comment'))
                                ->required($locale === $defaultLocale)
                                ->columnSpanFull(),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date_from')
                    ->label(__('holiday_period.columns.date_from'))
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('date_to')
                    ->label(__('holiday_period.columns.date_to'))
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('comment')
                    ->label(__('holiday_period.columns.comment'))
                    ->formatStateUsing(fn (HolidayPeriod $record): string => trim(strip_tags($record->localizedComment())) ?: '—')
                    ->limit(80)
                    ->wrap(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('holiday_period.columns.is_active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('holiday_period.filters.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('date_from', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHolidayPeriods::route('/'),
            'create' => Pages\CreateHolidayPeriod::route('/create'),
            'edit' => Pages\EditHolidayPeriod::route('/{record}/edit'),
        ];
    }
}
