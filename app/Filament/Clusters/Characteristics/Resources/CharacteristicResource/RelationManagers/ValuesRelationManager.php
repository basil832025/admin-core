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
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
//use Filament\Resources\RelationManagers\Get; // ✅ нужный класс
class ValuesRelationManager extends RelationManager
{
    use Translatable;

    protected static string $relationship = 'values';
    protected static ?string $label        = null;

    public static function getLabel(): string
    {
        return __('characteristic.values.label');
    }

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
                            ->label(__('characteristic.values.color'))
                            ->required($locale === $defaultLocale)
                            // ->visible(fn ($get) => $get('field_type') === 'color'),
                            ->visible(fn () => $this->getOwnerRecord()?->field_type === 'color'),

                        // TextInput по умолчанию
                        TextInput::make("value")
                            ->label(__('characteristic.values.value'))
                            ->required($locale === $defaultLocale)
                            ->visible(fn () => $this->getOwnerRecord()?->field_type !== 'color')


                    ]),
                TextInput::make('sort_order')->label(__('characteristic.values.sort_order'))->numeric()->default(0),
                Toggle::make('is_active')->label(__('characteristic.values.is_active'))->default(true),
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
            ->reorderable('sort_order')
            ->columns([
                // Показываем цветной кружок, если тип поля "color"



                // Показываем текст, если не "color"
                TextColumn::make('value')
                    ->label(__('characteristic.values.value'))

                    ->searchable(query: function (EloquentBuilder $query, string $search) use ($locale): EloquentBuilder {
                        return $query->whereRaw(
                            "JSON_UNQUOTE(JSON_EXTRACT(`value`, '$.\"$locale\"')) LIKE ?",
                            ["%{$search}%"]
                        );
                    })
                    ->sortable(query: function (EloquentBuilder $query, string $direction) use ($locale): EloquentBuilder {
                        return $query->orderByRaw(
                            "LOWER(JSON_UNQUOTE(JSON_EXTRACT(`value`, '$.\"$locale\"'))) {$direction}"
                        );
                    })
                    ->formatStateUsing(function ($state, $record) use ($locale) {
                        return $record->getTranslation('value', $locale);
                    })
                //   ->visible(fn () => $owner?->field_type !== 'color')
                ,

                TextColumn::make('sort_order')->label(__('characteristic.values.sort_order')),
                IconColumn::make('is_active')->label(__('characteristic.values.is_active'))->boolean(),
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
