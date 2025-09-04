<?php

namespace App\Filament\Clusters\Characteristics\Resources\CharacteristicResource\RelationManagers;

use App\Models\Language;
use App\Models\Setting;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Group;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\RelationManagers\RelationManager;
// ↓ вот эти два
use Filament\Forms\Form;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Table;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions;
use SolutionForest\FilamentTranslateField\Forms\Component\Translate;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables;
//use Filament\Resources\RelationManagers\Get; // ✅ нужный класс
class ValuesRelationManager extends RelationManager
{
    use Translatable;

    protected static string $relationship = 'values';
    protected static ?string $label        = 'Значения';

    // Сигнатура теперь корректна:
    public function form(Form $form): Form
    {
        $locales = static::getActiveLocales();
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        return $form
            ->schema([
                Translate::make()
                    ->locales($locales)
                    ->prefixLocaleLabel()
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(fn(string $locale) => [
                        // Color Picker (только если field_type === 'color')
                        ColorPicker::make("value")
                            ->label('Колір')
                            ->required($locale === $defaultLocale)
                            // ->visible(fn ($get) => $get('field_type') === 'color'),
                            ->visible(fn () => $this->getOwnerRecord()?->field_type === 'color'),

                        // TextInput по умолчанию
                        TextInput::make("value")
                            ->label('Значення')
                            ->required($locale === $defaultLocale)
                            ->visible(fn () => $this->getOwnerRecord()?->field_type !== 'color')


                    ]),
                TextInput::make('sort_order')->label('Позиция')->numeric()->default(0),
                Toggle::make('is_active')->label('Активно')->default(true),
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
    public function table(Table $table): Table
    {
        $owner = $this->getOwnerRecord(); // Родительская характеристика
        $locale = Setting::value('default_language_code') ?? config('app.locale');

        return $table
            ->columns([
                // Показываем цветной кружок, если тип поля "color"



                // Показываем текст, если не "color"
                TextColumn::make('value')
                    ->label('Значение')
                    ->formatStateUsing(function ($state, $record) use ($locale) {
                        return $record->getTranslation('value', $locale);
                    })
                //   ->visible(fn () => $owner?->field_type !== 'color')
                ,

                TextColumn::make('sort_order')->label('Позиция'),
                IconColumn::make('is_active')->label('Активно')->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('sort_order');
    }


}
