<?php

namespace App\Filament\Clusters\Characteristics\Resources;

use App\Filament\Clusters\Characteristics;
use App\Filament\Clusters\Characteristics\Resources\CharacteristicResource\Pages;
use App\Filament\Clusters\Characteristics\Resources\CharacteristicResource\RelationManagers;
use App\Models\Shop\CategoryCharacteristic;
use App\Models\Shop\Characteristic;

use App\Models\Shop\CharacteristicCategory;

use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Pages\SubNavigationPosition;
use Illuminate\Support\Arr;
use SolutionForest\FilamentTranslateField\Forms\Component\Translate;
use App\Models\Setting;
use App\Models\Language;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables\Enums\FiltersLayout;
class CharacteristicResource extends Resource
{
    use Translatable;
    protected static ?string $model = Characteristic::class;
    //   protected static ?string $slug = 'catalog/characteristics';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $cluster = Characteristics::class;
    protected static ?string $navigationLabel = 'Характеристики';
    //   protected static ?string $navigationLabel = 'Категории характеристик';
    protected static ?string $modelLabel = 'характеристика';
    protected static ?string $pluralModelLabel = 'характеристика';
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
                Section::make('Основные')
                    ->schema([
                        Grid::make(2)
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
                                    ->label('Slug')
                                    ->unique(ignoreRecord: true)
                                    //  ->required()
                                    ->maxLength(255),

                                Select::make('category_id')
                                    ->relationship('category', 'name')
                                    // вернем на языке по умоллчанию название категорий
                                    ->options(function () use ($defaultLocale) {
                                        return CharacteristicCategory::query()
                                            ->where('is_active', 1)
                                            ->get()
                                            ->mapWithKeys(fn($cat) => [
                                                $cat->id => (
                                                    json_decode($cat->getRawOriginal('name'), true)[$defaultLocale]
                                                    ?? json_decode($cat->getRawOriginal('name'), true)[config('app.locale')]
                                                ),
                                            ])
                                            ->toArray();
                                    })
                                    ->label('Категория')
                                    ->required(),

                                Select::make('pricing_type')
                                    ->label('Тип ценообразования')
                                    ->options([
                                        0 => 'Не влияет',
                                        1 => 'Надбавка',
                                        2 => 'Фиксированная',
                                    ])
                                    ->required(),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('sort_order')
                                    ->label('Позиция сортировки')
                                    ->numeric()
                                    ->default(0),

                                Toggle::make('expand_values')
                                    ->label('Раскрывать все значения'),

                                Toggle::make('is_required')
                                    ->label('Обязательная'),
                                Toggle::make('is_main_tab')
                                    ->label('На главной вкладке показать товара'),
                            ]),

                        Select::make('field_type')
                            ->label('Тип поля')
                            ->options([
                                'text'        => 'TextInput',
                                'datetime'    => 'Date/Time',
                                'number'      => 'Number',
                                'decimal'     => 'Decimal',
                                'textarea'    => 'Textarea',
                                'toggle'      => 'Switch',
                                'color'       => 'Color Picker',
                                'file'        => 'File/Image',
                                'select'      => 'Select (одно)',
                                'radio'       => 'RadioList',
                                'multiselect' => 'MultiSelect',
                                'checkbox'    => 'CheckboxList',
                            ])
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Активность')
                            ->default(true),
                    ]),
            ]);
    }
    public static function getRelations(): array
    {
        return [
            RelationManagers\ValuesRelationManager::class,
        ];
    }
    public static function table(Table $table): Table
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Категория')

                    ->getStateUsing(function (Characteristic $record, TextColumn $column, $livewire) use($defaultLocale) {
                        $locale = $defaultLocale;

                        // Возвращаем нужный перевод или дефолт
                        return $record->category
                            // используя Spatie, вытянем нужный перевод:
                            ? $record->category->getTranslation('name', $locale)
                            : '—';

                    })
                    ->sortable(),

                TextColumn::make('field_type')
                    ->label('Тип поля'),

                TextColumn::make('pricing_type')
                    ->label('Ценообразование')
                    ->formatStateUsing(fn (int $state) => match ($state) {
                        0 => 'Не влияет',
                        1 => 'Надбавка',
                        2 => 'Фиксированная',
                    }),

                IconColumn::make('is_required')
                    ->label('Обязательная')
                    ->boolean(),
                IconColumn::make('is_main_tab')
                    ->label('На главной')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Позиция')
                    ->sortable(),
            ])
            ->filtersFormColumns(6) // сколько колонок занимают фильтры в строке
            // 👇 сохранять выбор фильтров между перезагрузками
            ->persistFiltersInSession()
            ->filters([
                SelectFilter::make('category')
                    ->label(__('product.filters.category'))
                    ->columnSpan(2)

                    ->placeholder(__('product.filters.category_all'))                         // вместо «Всі»
                    // ->searchPrompt('Введите текст для поиска…')
                    ->relationship('characteristicCategories', 'id') // фильтруем по belongsTo 'category'
                    ->getOptionLabelFromRecordUsing(function (CharacteristicCategory $record): string {
                        $locale = Setting::value('default_language_code') ?: app()->getLocale();

                        // без авто-фолбека, может вернуть null/array
                        $label = $record->getTranslation('name', $locale, false);

                        if (is_array($label)) {
                            $label = $label['value'] ?? Arr::first($label, fn($v) => is_string($v) && $v !== '') ?? '';
                        }

                        if (! is_string($label) || $label === '') {
                            $all = $record->getTranslations('name'); // ['uk'=>..., 'ru'=>..., ...] либо вложенные
                            foreach ($all as $v) {
                                if (is_string($v) && $v !== '') { $label = $v; break; }
                                if (is_array($v) && is_string($v['value'] ?? null) && $v['value'] !== '') { $label = $v['value']; break; }
                            }
                        }

                        return (string) $label;
                    })

                    ->preload()
                    ->searchable(),

            ], layout: FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\EditAction::make(),
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
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCharacteristics::route('/'),
            'create' => Pages\CreateCharacteristic::route('/create'),
            'edit' => Pages\EditCharacteristic::route('/{record}/edit'),
        ];
    }
}
