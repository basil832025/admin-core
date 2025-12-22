<?php

namespace App\Filament\Clusters\Characteristics\Resources;

use App\Filament\Clusters\Characteristics;
use App\Filament\Clusters\Characteristics\Resources\CharacteristicResource\Pages;
use App\Filament\Clusters\Characteristics\Resources\CharacteristicResource\RelationManagers;
use App\Models\Shop\CategoryCharacteristic;
use App\Models\Shop\Characteristic;

use App\Models\Shop\CharacteristicCategory;

use App\Models\SvgImage;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
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
use Filament\Forms\Get;
use Illuminate\Support\HtmlString;
class CharacteristicResource extends Resource
{
    use Translatable;
    protected static ?string $model = Characteristic::class;
    //   protected static ?string $slug = 'catalog/characteristics';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $cluster = Characteristics::class;
    protected static ?string $navigationLabel = null;
    //   protected static ?string $navigationLabel = 'Категории характеристик';
    protected static ?string $modelLabel = null;
    protected static ?string $pluralModelLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('characteristic.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('characteristic.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('characteristic.nav.plural_model_label');
    }

    public static function getBreadcrumb(): string
    {
        return __('characteristic.nav.navigation_label');
    }

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
                Section::make(__('characteristic.sections.main'))
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
                                            ->label(__('characteristic.fields.name'))
                                            ->required($locale === $defaultLocale)
                                            ->maxLength(255),
                                    ]),

                                TextInput::make('slug')
                                    ->label(__('characteristic.fields.slug'))
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
                                    ->label(__('characteristic.fields.category'))
                                    ->required(),
                                Section::make(__('characteristic.sections.icon'))
                                    ->columns([
                                        'default' => 1,   // на мобилках — одна колонка
                                        'md'      => 12,  // с md и выше — 12 колонок
                                    ])
                                    ->schema([
                                Select::make('svg_image_id')
                                    ->label(__('characteristic.fields.icon'))
                                    ->placeholder('— ' . __('characteristic.helpers.icon_not_selected') . ' —')
                                    ->searchable()
                                    ->preload()
                                    ->options(function () {
                                        // Возьмем только иконки, помеченные как "для характеристик"
                                        return SvgImage::query()
                                            ->where('is_attr', true)
                                            ->orderBy('title')
                                            ->get()
                                            ->mapWithKeys(function (SvgImage $svg) {
                                                $label = trim(($svg->title ?: $svg->slug) . ' [' . $svg->slug . ']');
                                                return [$svg->id => $label];
                                            })
                                            ->toArray();
                                    })
                                    // если хотите связью (альтернатива options()):
                                    // ->relationship('svgImage', 'slug', fn($q) => $q->where('is_attr', true))
                                    ->hint(__('characteristic.helpers.icon_hint'))
                                    ->native(false)       // красивый Select2
                                    ->columnSpan(8)  // левая часть
                                    ->live(),             // для живого превью ниже

                                Placeholder::make('svg_icon_preview')
                                    ->label(__('characteristic.fields.icon_preview'))
                                    ->content(function (Get $get) {
                                        $id = $get('svg_image_id');
                                        if (! $id) {
                                            return new HtmlString('<div class="text-xs text-gray-500">' . __('characteristic.helpers.icon_not_selected') . '</div>');
                                        }

                                        /** @var SvgImage|null $svg */
                                        $svg = SvgImage::query()->find($id);
                                        if (! $svg) {
                                            return new HtmlString('<div class="text-xs text-red-500">' . __('characteristic.helpers.icon_load_error') . '</div>');
                                        }

                                        // предполагаем, что у SvgImage есть поле svg_normalized (или svg_code)
                                        $code = $svg->svg_normalized ?: $svg->svg_code;

                                        if (! is_string($code) || ! str_starts_with(ltrim($code), '<svg')) {
                                            return new HtmlString('<div class="text-xs text-red-500">' . __('characteristic.helpers.icon_invalid') . '</div>');
                                        }

                                        // показываем маленьким размером; если нормализовали под currentColor — можно задать цвет
                                        $code = preg_replace('/<svg\b(?![^>]*width=)/', '<svg width="28"', $code, 1);
                                        $code = preg_replace('/<svg\b(?![^>]*height=)/', '<svg height="28"', $code, 1);

                                        return new HtmlString('
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded border bg-white"
                      style="color:#111827">'
                                            . $code .
                                            '</span>
                <span class="text-xs text-gray-600">'
                                            . e($svg->title ?: $svg->slug) .
                                            '</span>
            </div>
        ');
                                    })->columnSpan(4)
                                    ,
                                ]),
                                Select::make('pricing_type')
                                    ->label(__('characteristic.fields.pricing_type'))
                                    ->options([
                                        0 => __('characteristic.pricing_types.no_impact'),
                                        1 => __('characteristic.pricing_types.surcharge'),
                                        2 => __('characteristic.pricing_types.fixed'),
                                    ])
                                    ->required(),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('sort_order')
                                    ->label(__('characteristic.fields.sort_position'))
                                    ->numeric()
                                    ->default(0),

                                Toggle::make('expand_values')
                                    ->label(__('characteristic.fields.expand_all_values')),

                                Toggle::make('is_required')
                                    ->label(__('characteristic.fields.is_required')),
                                Toggle::make('is_main_tab')
                                    ->label(__('characteristic.fields.show_on_main_tab')),
                            ]),

                        Select::make('field_type')
                            ->label(__('characteristic.fields.field_type'))
                            ->options([
                                'text'        => __('characteristic.field_types.text'),
                                'datetime'    => __('characteristic.field_types.datetime'),
                                'number'      => __('characteristic.field_types.number'),
                                'decimal'     => __('characteristic.field_types.decimal'),
                                'textarea'    => __('characteristic.field_types.textarea'),
                                'toggle'      => __('characteristic.field_types.toggle'),
                                'color'       => __('characteristic.field_types.color'),
                                'file'        => __('characteristic.field_types.file'),
                                'select'      => __('characteristic.field_types.select'),
                                'radio'       => __('characteristic.field_types.radio'),
                                'multiselect' => __('characteristic.field_types.multiselect'),
                                'checkbox'    => __('characteristic.field_types.checkbox'),
                            ])
                            ->required(),

                        Toggle::make('is_active')
                            ->label(__('characteristic.fields.is_active'))
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
                    ->label(__('characteristic.columns.name'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('slug')
                    ->label(__('characteristic.columns.slug'))
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label(__('characteristic.columns.category'))

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
                    ->label(__('characteristic.columns.field_type')),

                TextColumn::make('pricing_type')
                    ->label(__('characteristic.columns.pricing_type'))
                    ->formatStateUsing(fn (int $state) => match ($state) {
                        0 => __('characteristic.pricing_types.no_impact'),
                        1 => __('characteristic.pricing_types.surcharge'),
                        2 => __('characteristic.pricing_types.fixed'),
                    }),
                ViewColumn::make('svg_icon')
                    ->label(__('characteristic.columns.icon'))
                    ->view('filament.tables.columns.characteristic-svg') // см. ниже
                    ->toggleable(),
                IconColumn::make('is_required')
                    ->label(__('characteristic.columns.is_required'))
                    ->boolean(),
                IconColumn::make('is_main_tab')
                    ->label(__('characteristic.columns.is_main_tab'))
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label(__('characteristic.columns.is_active'))
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label(__('characteristic.columns.sort_order'))
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
