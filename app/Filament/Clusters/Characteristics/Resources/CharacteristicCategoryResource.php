<?php

namespace App\Filament\Clusters\Characteristics\Resources;

use App\Filament\Clusters\Characteristics;
use App\Filament\Clusters\Characteristics\Resources\CharacteristicCategoryResource\Pages;
use App\Filament\Clusters\Characteristics\Resources\CharacteristicCategoryResource\RelationManagers;
use App\Models\Shop\CharacteristicCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Pages\SubNavigationPosition;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use SolutionForest\FilamentTranslateField\Forms\Component\Translate;
use App\Models\Setting;
use App\Models\Language;
use Filament\Resources\Concerns\Translatable;


class CharacteristicCategoryResource extends Resource
{
    use Translatable;
    protected static ?string $model = CharacteristicCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $cluster = Characteristics::class;
  //  protected static ?string $navigationParentItem = 'Категории характеристик';
     protected static ?string $navigationLabel = 'Категории характеристик';
 //   protected static ?string $navigationLabel = 'Категории характеристик';
    protected static ?string $modelLabel = 'Категория характеристик';
    protected static ?string $pluralModelLabel = 'Категории характеристик';
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        // код основного языка
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        // список активных языков из таблицы languages
        $locales = static::getActiveLocales();
        return $form
            ->schema([
                Translate::make()
                    ->locales($locales)
                    ->prefixLocaleLabel()
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(fn(string $locale) => [
                TextInput::make('name')
                    ->label('Название')
                    ->required($locale === $defaultLocale)
                    ->maxLength(255),
                ]),
                TextInput::make('slug')
                    ->label('Код/slug')
               //     ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('sort_order')
                    ->label('Позиция сортировки')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Статус')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('Позиция')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean(),
            ])
            ->filters([
                //
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
            ->map(fn($c) => strtolower($c))
            ->toArray();
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
            'index' => Pages\ListCharacteristicCategories::route('/'),
            'create' => Pages\CreateCharacteristicCategory::route('/create'),
            'edit' => Pages\EditCharacteristicCategory::route('/{record}/edit'),
        ];
    }
}
