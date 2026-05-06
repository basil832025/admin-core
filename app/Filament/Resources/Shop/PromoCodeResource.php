<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\PromoCodeResource\Pages;
use App\Models\Setting;
use App\Models\Shop\Characteristic;
use App\Models\Shop\Product;
use App\Models\Shop\ProductCategory;
use App\Models\Shop\PromoCode;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\Shop\CharacteristicValue;
use Illuminate\Database\Eloquent\Builder;

class PromoCodeResource extends Resource
{
    protected static ?string $model = PromoCode::class;

    protected static ?string $navigationGroup = null;
    protected static ?string $navigationIcon  = 'heroicon-o-ticket';
    protected static ?string $navigationLabel = null;
    protected static ?string $pluralModelLabel = null;
    protected static ?string $modelLabel = null;

    public static function getNavigationGroup(): ?string
    {
        return __('promo_code.nav.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('promo_code.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('promo_code.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('promo_code.nav.plural_model_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('promo_code.sections.parameters'))
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label(__('promo_code.fields.code'))
                        ->required()
                        ->maxLength(64)
                        ->unique(PromoCode::class, 'code', ignoreRecord: true)
                        ->helperText(__('promo_code.helpers.code_uppercase'))
                        ->afterStateUpdated(fn ($state, $set) => $set('code', mb_strtoupper(trim((string) $state))))
                        ->afterStateHydrated(function ($component, $state) {
                            $component->state(mb_strtoupper(trim((string) $state)));
                        }),

                    Forms\Components\TextInput::make('percent')
                        ->label(__('promo_code.fields.percent'))
                        ->numeric()
                        ->suffix('%')
                        ->minValue(0.01)
                        ->maxValue(100)
                        ->step(0.01)
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('promo_code.fields.is_active'))
                        ->default(true),

                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label(__('promo_code.fields.starts_at'))
                        ->seconds(false),

                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label(__('promo_code.fields.ends_at'))
                        ->seconds(false)
                        ->rule('after_or_equal:starts_at'),

                    Forms\Components\TextInput::make('max_uses')
                        ->label(__('promo_code.fields.max_uses'))
                        ->numeric()
                        ->minValue(1)
                        ->helperText(__('promo_code.helpers.max_uses_empty')),

                    Forms\Components\TextInput::make('per_client_limit')
                        ->label(__('promo_code.fields.per_client_limit'))
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required(),

                    Forms\Components\Textarea::make('note')
                        ->label(__('promo_code.fields.note'))
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make(__('promo_code.sections.scope'))
                ->description(__('promo_code.helpers.scope_description'))
                ->columns(2)
                ->schema([
                    // Категории
                    Forms\Components\MultiSelect::make('categories')
                        ->label(__('promo_code.fields.categories'))
                        ->relationship(
                            name: 'categories',
                            titleAttribute: 'id', // метка ниже
                            modifyQueryUsing: fn (Builder $query): Builder => $query
                                ->where('is_visible', true)
                                ->whereNotNull('slug')
                                ->where('slug', 'not like', 'src-%')
                        )
                        ->getSearchResultsUsing(function (string $search): array {
                            $locale = Setting::value('default_language_code') ?: config('app.locale');

                            return ProductCategory::query()
                                ->select('id', 'title')
                                ->where('is_visible', true)
                                ->whereNotNull('slug')
                                ->where('slug', 'not like', 'src-%')
                                ->where(function ($q) use ($search, $locale) {
                                    $path = '$."' . $locale . '"';
                                    $q->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, ?))) LIKE ?", [$path, "%{$search}%"]);
                                })
                                ->orderBy('id')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(function (ProductCategory $c) use ($locale) {
                                    $label = $c->getTranslation('title', $locale) ?? ('#'.$c->id);
                                    return [$c->id => $label];
                                })
                                ->toArray();
                        })
                        ->getOptionLabelsUsing(function (array $values): array {
                            $locale = Setting::value('default_language_code') ?: config('app.locale');

                            return ProductCategory::query()
                                ->where('is_visible', true)
                                ->whereNotNull('slug')
                                ->where('slug', 'not like', 'src-%')
                                ->whereIn('id', $values)
                                ->get(['id', 'title'])
                                ->mapWithKeys(function (ProductCategory $c) use ($locale) {
                                    $label = $c->getTranslation('title', $locale) ?? ('#'.$c->id);
                                    return [$c->id => $label];
                                })
                                ->toArray();
                        })
                        ->getOptionLabelFromRecordUsing(function (ProductCategory $record) {
                            $defaultLocale = Setting::value('default_language_code') ?: app()->getLocale();
                            return $record->getTranslation('title', $defaultLocale);
                        })
                        ->preload()
                        ->searchable(),

                    // Товары
                    Forms\Components\MultiSelect::make('products')
                        ->label(__('promo_code.fields.products'))
                        ->relationship(
                            name: 'products',
                            titleAttribute: 'id' // метка ниже
                        )
                        // как искать (возвращаем [id => label]):
                        ->getSearchResultsUsing(function (string $search): array {
                            $locale = Setting::value('default_language_code') ?: config('app.locale');

                            return Product::query()
                                ->select('id', 'title', 'in_stock')
                                ->where('in_stock', 1)
                                ->where(function ($q) use ($search, $locale) {
                                    // поиск по локализованному названию в JSON
                                    $path = '$."' . $locale . '"';
                                    $q->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, ?))) LIKE ?", [$path, "%{$search}%"]);
                                })
                                ->orderBy('id')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(function (Product $p) use ($locale) {
                                    $raw = json_decode($p->getRawOriginal('title'), true) ?: [];
                                    $label = $raw[$locale] ?? $raw[config('app.locale')] ?? ('#'.$p->id);
                                    return [$p->id => $label];
                                })
                                ->toArray();
                        })
                        // как подписывать уже выбранные значения:
                        ->getOptionLabelsUsing(function (array $values): array {
                            $locale = Setting::value('default_language_code') ?: config('app.locale');

                            return Product::query()
                                ->whereIn('id', $values)
                                ->get(['id', 'title'])
                                ->mapWithKeys(function (Product $p) use ($locale) {
                                    $raw = json_decode($p->getRawOriginal('title'), true) ?: [];
                                    $label = $raw[$locale] ?? $raw[config('app.locale')] ?? ('#'.$p->id);
                                    return [$p->id => $label];
                                })
                                ->toArray();
                        })
                        ->getOptionLabelFromRecordUsing(function (Product $p) {
                            $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
                            $raw = json_decode($p->getRawOriginal('title'), true) ?: [];
                            return $raw[$defaultLocale]
                                ?? $raw[config('app.locale')]
                                ?? ('#'.$p->id);
                        })
                        ->preload()
                        ->searchable(),

                    // Характеристики (любой value)
                    // Характеристики (любой value)
                    Forms\Components\MultiSelect::make('characteristics')
                        ->label(__('promo_code.fields.characteristics'))
                        ->relationship(name: 'characteristics', titleAttribute: 'name')
                        ->options(fn () => Characteristic::query()->orderBy('id')->pluck('name','id')->toArray())
                        ->searchable()
                        ->preload()
                        ->reactive()
                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                            $selectedChars = (array) $state;
                            if (empty($selectedChars)) {
                                // если очистили характеристики — обнулим и значения
                                $set('characteristicValues', []);
                                return;
                            }

                            // оставим среди уже выбранных values только те, что принадлежат выбранным характеристикам
                            $currentValueIds = (array) $get('characteristicValues');
                            if (empty($currentValueIds)) return;

                            $validIds = CharacteristicValue::query()
                                ->whereIn('characteristic_id', $selectedChars)
                                ->whereIn('id', $currentValueIds)
                                ->pluck('id')->all();

                            if (count($validIds) !== count($currentValueIds)) {
                                $set('characteristicValues', $validIds);
                            }
                        }),
                    // Значения характеристик (зависят от выбранных характеристик)
                    Forms\Components\MultiSelect::make('characteristicValues')
                        ->label(__('promo_code.fields.characteristic_values'))
                        ->relationship(name: 'characteristicValues', titleAttribute: 'value')
                        ->reactive()                // ← важно: пересчитывать options при изменении других полей
                        ->preload()                 // ← показывать список сразу (ограничен выбранными характеристиками)
                        ->searchable()
                        ->options(function (Get $get): array {
                            $locale  = Setting::value('default_language_code') ?: app()->getLocale();
                            $charIds = array_filter((array) $get('characteristics'));

                            if (empty($charIds)) {
                                return []; // пока не выбрали характеристики — не показываем ничего
                            }

                            return CharacteristicValue::query()
                                ->with('characteristic:id,name')
                                ->whereIn('characteristic_id', $charIds)
                                ->orderBy('characteristic_id')
                                ->orderBy('id')
                                ->get()
                                ->mapWithKeys(function (CharacteristicValue $v) use ($locale) {
                                    $charName = $v->characteristic->name ?? ('#'.$v->characteristic_id);
                                    // если value переведён — замени на $v->getTranslation('value', $locale)
                                    $valText  = $v->value ?? ('#'.$v->id);
                                    return [$v->id => "{$charName}: {$valText}"];
                                })
                                ->toArray();
                        })
                        ->disabled(fn (Get $get) => empty($get('characteristics')))
                        ->helperText(__('promo_code.helpers.characteristic_values_hint')),

        ]),

            Forms\Components\Section::make(__('promo_code.sections.statistics'))
                ->collapsed()
                ->schema([
                    Forms\Components\Placeholder::make('used_total')
                        ->label(__('promo_code.fields.used_total'))
                        ->content(fn (?PromoCode $record) => $record?->usages()->count() ?? 0),

                    Forms\Components\Placeholder::make('remaining')
                        ->label(__('promo_code.fields.remaining'))
                        ->content(function (?PromoCode $record) {
                            if (!$record || $record->max_uses === null) return '∞';
                            $rem = max(0, $record->max_uses - $record->usages()->count());
                            return (string) $rem;
                        }),
                ]),
        ]);
    }
    protected static function getTableQuery(): Builder
    {
        // вот здесь добавляем withCount
        return PromoCode::query()->withCount('usages');
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label(__('promo_code.columns.code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('percent')
                    ->label(__('promo_code.columns.percent'))
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('promo_code.columns.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label(__('promo_code.columns.starts_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label(__('promo_code.columns.ends_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('usages_count')
                    ->label(__('promo_code.columns.usages_count'))
                    ->counts('usages') // withCount + вывод
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining')
                    ->label(__('promo_code.columns.remaining'))
                    ->state(function (PromoCode $record) {
                        if ($record->max_uses === null) return '∞';
                        return max(0, $record->max_uses - $record->usages()->count());
                    }),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label(__('promo_code.filters.is_active')),
                Tables\Filters\Filter::make('active_now')
                    ->label(__('promo_code.filters.active_now'))
                    ->query(function ($query) {
                        $now = now();
                        $query->where('is_active', true)
                            ->where(function ($q) use ($now) {
                                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                            })
                            ->where(function ($q) use ($now) {
                                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                            });
                    }),
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
            'index'  => Pages\ListPromoCodes::route('/'),
            'create' => Pages\CreatePromoCode::route('/create'),
            'edit'   => Pages\EditPromoCode::route('/{record}/edit'),
        ];
    }
}
