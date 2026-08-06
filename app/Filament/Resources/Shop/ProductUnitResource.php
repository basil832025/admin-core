<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\ProductUnitResource\Pages;
use App\Models\Shop\ProductUnit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductUnitResource extends Resource
{
    protected static ?string $model = ProductUnit::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = null;

    protected static ?string $slug = 'shop/product-units';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('product_unit.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('product_unit.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('product_unit.nav.plural_model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.shop');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->label(__('product_unit.fields.code'))
                ->required()
                ->maxLength(32)
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('name.uk')
                ->label(__('product_unit.fields.name_uk'))
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('short_name.uk')
                ->label(__('product_unit.fields.short_name_uk'))
                ->required()
                ->maxLength(32),
            Forms\Components\TextInput::make('name.ru')
                ->label(__('product_unit.fields.name_ru'))
                ->maxLength(255),
            Forms\Components\TextInput::make('short_name.ru')
                ->label(__('product_unit.fields.short_name_ru'))
                ->maxLength(32),
            Forms\Components\TextInput::make('name.en')
                ->label(__('product_unit.fields.name_en'))
                ->maxLength(255),
            Forms\Components\TextInput::make('short_name.en')
                ->label(__('product_unit.fields.short_name_en'))
                ->maxLength(32),
            Forms\Components\TextInput::make('sort_order')
                ->label(__('product_unit.fields.sort_order'))
                ->numeric()
                ->default(0),
            Forms\Components\Toggle::make('is_default')
                ->label(__('product_unit.fields.is_default')),
            Forms\Components\Toggle::make('is_active')
                ->label(__('product_unit.fields.is_active'))
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('product_unit.fields.code'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('short_name')
                    ->label(__('product_unit.columns.short_name'))
                    ->formatStateUsing(fn ($state, ProductUnit $record): string => $record->getTranslation('short_name', app()->getLocale(), false) ?: $record->code)
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('product_unit.columns.name'))
                    ->formatStateUsing(fn ($state, ProductUnit $record): string => $record->getTranslation('name', app()->getLocale(), false) ?: $record->code)
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label(__('product_unit.fields.is_default'))
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('product_unit.fields.is_active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('product_unit.fields.sort_order'))
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductUnits::route('/'),
            'create' => Pages\CreateProductUnit::route('/create'),
            'edit' => Pages\EditProductUnit::route('/{record}/edit'),
        ];
    }
}
