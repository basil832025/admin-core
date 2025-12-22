<?php

namespace App\Filament\Clusters\Characteristics\Resources;

use App\Filament\Clusters\Characteristics;
use App\Filament\Clusters\Characteristics\Resources\VariationResource\Pages;
use App\Filament\Clusters\Characteristics\Resources\VariationResource\RelationManagers;
use App\Models\Shop\Variation;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Pages\SubNavigationPosition;
use App\Models\Shop\Characteristic;
use App\Models\Shop\CharacteristicValue;
use App\Models\Setting;
use App\Models\Language;
class   VariationResource extends Resource
{
    protected static ?string $model = Variation::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = null;
    protected static ?string $modelLabel = null;
    protected static ?string $pluralModelLabel = null;
    protected static ?string $cluster = Characteristics::class;

    public static function getNavigationLabel(): string
    {
        return __('variation.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('variation.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('variation.nav.plural_model_label');
    }

    public static function getBreadcrumb(): string
    {
        return __('variation.nav.navigation_label');
    }

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
    protected static ?int $navigationSort = 1;
    public static function form(Form $form): Form
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');

        return $form->schema([
            Grid::make(2)
                ->schema([
                    // Левая колонка
                    TextInput::make('name')
                        ->label(__('variation.fields.name'))
                        ->helperText(__('variation.helpers.name_example')),
                    TextInput::make('slug')
                        ->label(__('variation.fields.slug'))
                        ->helperText(__('variation.helpers.slug_auto'))
                        ->unique(ignoreRecord: true)
                        //  ->required()
                        ->maxLength(255),

                    // Левая колонка
                    Repeater::make('variationCharacteristicValues')
                        ->label(__('variation.fields.characteristic_values'))
                        ->relationship('variationCharacteristicValues')
                        ->schema([
                            Select::make('characteristic_id')
                                ->label(__('variation.fields.characteristic'))
                                ->options(function () use ($defaultLocale) {
                                    return \App\Models\Shop\Characteristic::all()
                                        ->mapWithKeys(function ($item) use ($defaultLocale) {

                                            $label = json_decode($item->getRawOriginal('name'), true)[$defaultLocale]
                                                ?? json_decode($item->getRawOriginal('name'), true)[config('app.locale')];

                                            // принудительно привести к строке, даже если null
                                            return [$item->id => (string) $label];
                                        })
                                        ->filter(fn ($label) => trim($label) !== '');
                                })
                                ->reactive(),

                            Select::make('characteristic_value_id')
                                ->label(__('variation.fields.value'))
                                ->options(fn ($get) => CharacteristicValue::where('characteristic_id', $get('characteristic_id'))->pluck('value', 'id'))
                                ->required(),
                        ])
                        ->columns(2)
                        ->minItems(1)
                        ->maxItems(10)
                        ->columnSpanFull(), // Растянуть на всю ширину

                ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('variation.columns.name'))
                    ->searchable(),
                TextColumn::make('slug')
                    ->label(__('variation.columns.slug'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('variation.columns.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('variation.columns.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVariations::route('/'),
            'create' => Pages\CreateVariation::route('/create'),
            'edit' => Pages\EditVariation::route('/{record}/edit'),
        ];
    }
}
