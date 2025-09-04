<?php

namespace App\Filament\Resources\Shop;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Filament\Resources\Shop\FixedDiscountResource\Pages;
use App\Models\Shop\FixedDiscount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FixedDiscountResource extends Resource
{
    protected static ?string $model = FixedDiscount::class;

    protected static ?string $navigationGroup = 'Дисконтные программы';
    protected static ?string $navigationIcon = 'heroicon-o-percent-badge';
    protected static ?string $navigationLabel = 'Фиксированные скидки';
    protected static ?string $pluralModelLabel = 'Фиксированные скидки';
    protected static ?string $modelLabel = 'Фиксированная скидка';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Название')
                ->required()
                ->maxLength(128),

            Forms\Components\TextInput::make('percent')
                ->label('Скидка %')
                ->numeric()
                ->suffix('%')
                ->minValue(0.01)
                ->maxValue(100)
                ->step(0.01)
                ->required(),

            Forms\Components\Toggle::make('is_active')
                ->label('Активна')
                ->default(true),

            Forms\Components\Select::make('applies_to')
                ->label('Применяется к')
                ->options([
                    'all'     => 'Все клиенты',
                    'client'  => 'Конкретный клиент',
                    'segment' => 'Сегмент клиентов',
                ])
                ->default('all')
                ->disabled(fn () => true), // пока только "all"

            Forms\Components\DateTimePicker::make('starts_at')
                ->label('Начало действия')
                ->seconds(false),

            Forms\Components\DateTimePicker::make('ends_at')
                ->label('Окончание действия')
                ->seconds(false),

            Forms\Components\Textarea::make('note')
                ->label('Примечание')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('percent')
                    ->label('Скидка')
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Начало')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Окончание')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Активна'),
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
