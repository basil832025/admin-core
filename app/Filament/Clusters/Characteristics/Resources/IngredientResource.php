<?php

namespace App\Filament\Clusters\Characteristics\Resources;

use App\Filament\Clusters\Characteristics;
use App\Filament\Clusters\Characteristics\Resources\IngredientResource\Pages;
use App\Models\Language;
use App\Models\Setting;
use App\Models\Shop\Ingredient;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use SolutionForest\FilamentTranslateField\Forms\Component\Translate;

class IngredientResource extends Resource
{
    use Translatable;

    protected static ?string $model = Ingredient::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $cluster = Characteristics::class;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('ingredient.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('ingredient.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ingredient.nav.plural_model_label');
    }

    public static function getBreadcrumb(): string
    {
        return __('ingredient.nav.navigation_label');
    }

    public static function form(Form $form): Form
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        $locales = static::getActiveLocales();

        return $form->schema([
            Translate::make()
                ->locales($locales)
                ->prefixLocaleLabel()
                ->columns(1)
                ->columnSpanFull()
                ->schema(fn (string $locale) => [
                    TextInput::make('name')
                        ->label(__('ingredient.fields.name'))
                        ->required($locale === $defaultLocale)
                        ->maxLength(255),
                ]),

            TextInput::make('slug')
                ->label(__('ingredient.fields.slug'))
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            Toggle::make('is_active')
                ->label(__('ingredient.fields.is_active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('ingredient.columns.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('ingredient.columns.slug'))
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('ingredient.columns.is_active'))
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label(__('ingredient.columns.updated_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function getActiveLocales(): array
    {
        return Language::where('active', true)
            ->orderBy('position')
            ->pluck('code')
            ->map(fn ($code) => strtolower($code))
            ->toArray();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIngredients::route('/'),
            'create' => Pages\CreateIngredient::route('/create'),
            'edit' => Pages\EditIngredient::route('/{record}/edit'),
        ];
    }
}
