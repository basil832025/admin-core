<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryZoneResource\Pages;
use App\Models\DeliveryZone;
use Filament\Forms;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeliveryZoneResource extends Resource
{
    protected static ?string $model = DeliveryZone::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationGroup = 'Настройки';
    protected static ?string $navigationLabel = 'Зоны доставки';
    protected static ?string $modelLabel = 'Зона доставки';
    protected static ?string $pluralModelLabel = 'Зоны доставки';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(12)->schema([
                Section::make('Основная информация')->schema([
                    TextInput::make('name')
                        ->label('Название зоны')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Например: Green_1, Зеленая зона 1'),

                    Textarea::make('description')
                        ->label('Описание')
                        ->rows(3)
                        ->columnSpanFull(),

                    ColorPicker::make('color')
                        ->label('Цвет зоны')
                        ->default('#00FF00')
                        ->required()
                        ->helperText('Цвет для отображения зоны на карте (HEX формат)'),
                ])->columns(2),

                Section::make('Параметры доставки')->schema([
                    TextInput::make('delivery_price')
                        ->label('Цена доставки (грн)')
                        ->numeric()
                        ->required()
                        ->default(0)
                        ->step(0.01)
                        ->minValue(0)
                        ->helperText('Стоимость доставки в данной зоне'),

                    Grid::make(2)->schema([
                        TextInput::make('delivery_time_min')
                            ->label('Минимальное время доставки (мин)')
                            ->numeric()
                            ->required()
                            ->default(30)
                            ->minValue(0)
                            ->maxValue(999)
                            ->live()
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                $set('delivery_time_max', max($get('delivery_time_max') ?? 60, $state))
                            ),

                        TextInput::make('delivery_time_max')
                            ->label('Максимальное время доставки (мин)')
                            ->numeric()
                            ->required()
                            ->default(60)
                            ->minValue(fn (Forms\Get $get) => $get('delivery_time_min') ?? 0)
                            ->maxValue(999)
                            ->helperText(fn (Forms\Get $get) => 
                                $get('delivery_time_min') 
                                    ? "Должно быть больше или равно {$get('delivery_time_min')}"
                                    : 'Должно быть больше минимального времени'
                            ),
                    ]),

                    TextInput::make('free_delivery_from')
                        ->label('Бесплатная доставка от (грн)')
                        ->numeric()
                        ->required()
                        ->default(0)
                        ->step(0.01)
                        ->minValue(0)
                        ->helperText('Сумма заказа, от которой доставка становится бесплатной'),
                ])->columns(1),

                Section::make('Дополнительно')->schema([
                    TextInput::make('sort_order')
                        ->label('Порядок сортировки')
                        ->numeric()
                        ->default(0)
                        ->helperText('Чем меньше число, тем выше в списке'),

                    Toggle::make('is_active')
                        ->label('Активна')
                        ->default(true)
                        ->helperText('Отображать ли зону на карте и использовать ли её при расчете доставки'),
                ])->columns(2),
            ]),
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

                ColorColumn::make('color')
                    ->label('Цвет'),

                TextColumn::make('delivery_price')
                    ->label('Цена доставки')
                    ->money('UAH')
                    ->sortable(),

                TextColumn::make('delivery_time_min')
                    ->label('Время (мин)')
                    ->formatStateUsing(fn ($record) => "{$record->delivery_time_min} - {$record->delivery_time_max}")
                    ->sortable(),

                TextColumn::make('free_delivery_from')
                    ->label('Бесплатно от')
                    ->money('UAH')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Активна')
                    ->placeholder('Все')
                    ->trueLabel('Только активные')
                    ->falseLabel('Только неактивные'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc');
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
            'index' => Pages\ListDeliveryZones::route('/'),
            'create' => Pages\CreateDeliveryZone::route('/create'),
            'edit' => Pages\EditDeliveryZone::route('/{record}/edit'),
        ];
    }
}
