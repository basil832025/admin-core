<?php

namespace App\Filament\Resources\Shop;
use AmidEsfahani\FilamentTinyEditor\TinyEditor;
use App\Models\Setting;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Filament\Resources\Shop\FixedDiscountResource\Pages;
use App\Models\Shop\FixedDiscount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use SolutionForest\FilamentTranslateField\Forms\Component\Translate;

class FixedDiscountResource extends Resource
{
    use Translatable;
    protected static ?string $model = FixedDiscount::class;

    protected static ?string $navigationGroup = null;
    protected static ?string $navigationIcon = 'heroicon-o-percent-badge';
    protected static ?string $navigationLabel = null;
    protected static ?string $pluralModelLabel = null;
    protected static ?string $modelLabel = null;

    public static function getNavigationGroup(): ?string
    {
        return __('fixed_discount.nav.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('fixed_discount.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('fixed_discount.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fixed_discount.nav.plural_model_label');
    }

    public static function form(Form $form): Form
    {
        // код основного языка
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        // список активных языков из таблицы languages
        $locales = \App\Models\Setting::getActiveLocales();            // ['uk','en','ru']
        return $form->schema([

            Translate::make()
                ->locales($locales)
                ->prefixLocaleLabel()
                ->columns(1)
                ->columnSpanFull()
                        ->schema(fn(string $locale) => [
                    TextInput::make("name")
                        ->label(__('fixed_discount.fields.name'))
                        ->maxLength(128)
                        ->required($locale === $defaultLocale),

                ]),



            Forms\Components\TextInput::make('percent')
                ->label(__('fixed_discount.fields.percent'))
                ->numeric()
                ->suffix('%')
                ->minValue(0.01)
                ->maxValue(100)
                ->step(0.01)
                ->required(),

            Forms\Components\Toggle::make('is_active')
                ->label(__('fixed_discount.fields.is_active'))
                ->default(true),

            Forms\Components\Select::make('applies_to')
                ->label(__('fixed_discount.fields.applies_to'))
                ->options([
                    'all'     => __('fixed_discount.options.applies_to_all'),
                    'client'  => __('fixed_discount.options.applies_to_client'),
                    'segment' => __('fixed_discount.options.applies_to_segment'),
                ])
                ->default('all')
                ->disabled(fn () => true), // пока только "all"

            Forms\Components\DateTimePicker::make('starts_at')
                ->label(__('fixed_discount.fields.starts_at'))
                ->seconds(false),

            Forms\Components\DateTimePicker::make('ends_at')
                ->label(__('fixed_discount.fields.ends_at'))
                ->seconds(false),

            Forms\Components\Textarea::make('note')
                ->label(__('fixed_discount.fields.note'))
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('fixed_discount.columns.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('percent')
                    ->label(__('fixed_discount.columns.percent'))
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('fixed_discount.columns.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label(__('fixed_discount.columns.starts_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label(__('fixed_discount.columns.ends_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fixed_discount.columns.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('fixed_discount.filters.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFixedDiscounts::route('/'),
            'create' => Pages\CreateFixedDiscount::route('/create'),
            'edit'   => Pages\EditFixedDiscount::route('/{record}/edit'),
        ];
    }
}
