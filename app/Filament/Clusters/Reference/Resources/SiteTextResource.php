<?php

namespace App\Filament\Clusters\Reference\Resources;

use App\Filament\Clusters\Reference;
use App\Filament\Clusters\Reference\Resources\SiteTextResource\Pages;
use App\Filament\Clusters\Reference\Resources\SiteTextResource\RelationManagers;
use App\Models\Language;
use App\Models\Setting;
use App\Models\SiteText;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use App\Models\SiteTextGroup as SiteTextGroupModel;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Pages\SubNavigationPosition;
use SolutionForest\FilamentTranslateField\Forms\Component\Translate;
use Filament\Resources\Concerns\Translatable;
use Filament\Forms\Get;
use Filament\Forms\Set;

class SiteTextResource extends Resource
{
    use Translatable;

    protected static ?string $model = SiteText::class;
    protected static ?string $cluster = Reference::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
   // protected static ?string $navigationGroup = 'Контент';
    protected static ?string $navigationLabel = null;
    protected static ?string $modelLabel = null;
    protected static ?string $pluralModelLabel = null;
    protected static ?int    $navigationSort  = 1;
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getNavigationLabel(): string
    {
        return __('site_text.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('site_text.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('site_text.nav.plural_model_label');
    }
    public static function getTranslatableLocales(): array
    {
        return ['uk','en','ru'];
    }

    public static function form(Form $form): Form
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        $locales = static::getActiveLocales();
        return $form->schema([
            Forms\Components\Grid::make(12)->schema([
                Select::make('group_id')
                    ->label(__('site_text.fields.group_id'))
                    ->relationship('group', 'slug')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        // $state — выбранный group_id
                        if (!$state) return;

                        $groupSlug = SiteTextGroupModel::query()->whereKey($state)->value('slug');
                        if (!$groupSlug) return;

                        $current = (string) $get('slug');

                        // Если пользователь ещё ничего не ввёл — просто подставим "group."
                        if ($current === '') {
                            $set('slug', $groupSlug . '.');
                            return;
                        }

                        // Если slug не начинается с "group." — аккуратно префиксуем
                        $prefix = $groupSlug . '.';
                        if (! str_starts_with($current, $prefix)) {
                            $set('slug', $prefix . ltrim($current, '.'));
                        }
                    })
                    ->afterStateHydrated(function (Set $set, Get $get) {
                        if ($get('slug') !== '' || ! $get('group_id')) return;

                        $groupSlug = SiteTextGroupModel::query()->whereKey($get('group_id'))->value('slug');
                        if ($groupSlug) {
                            $set('slug', $groupSlug . '.');
                        }
                    })
                    ->createOptionForm([
                        TextInput::make('slug')
                            ->label(__('site_text.fields.group_slug'))
                            ->required()
                            // ✅ указываем ТАБЛИЦУ (строкой) ИЛИ модель (правильным классом)
                            ->unique(table: 'bs_site_text_groups', column: 'slug'),
                        Textarea::make('description')->label(__('site_text.fields.description'))->rows(2),
                        Translate::make()
                            ->locales(static::getActiveLocales())
                            ->prefixLocaleLabel()
                            ->schema(fn (string $locale) => [
                                TextInput::make("title.$locale")->label(__('site_text.fields.group_title'))->nullable(),
                            ]),
                    ])
                    ->createOptionAction(function (FormAction $action) {
                        return $action
                            ->label(__('site_text.actions.new_group'))
                            ->modalHeading(__('site_text.modals.new_group_heading'))
                            ->modalSubmitActionLabel(__('site_text.actions.create_group'))
                            ->modalWidth('3xl');
                    })

                    ->editOptionForm([
                        TextInput::make('slug')
                            ->label(__('site_text.fields.group_slug'))
                            ->required()
                            // ✅ используем верный alias модели ИЛИ имя таблицы
                            ->unique(table: 'bs_site_text_groups', column: 'slug', ignoreRecord: true),
                        // альтернативно: ->unique(table: SiteTextGroupModel::class, column: 'slug', ignoreRecord: true),
                        Textarea::make('description')->label(__('site_text.fields.description'))->rows(2),
                        Translate::make()
                            ->locales(static::getActiveLocales())
                            ->prefixLocaleLabel()
                            ->schema(fn (string $locale) => [
                                TextInput::make("title.$locale")->label(__('site_text.fields.group_title'))->nullable(),
                            ]),
                    ])
                    ->editOptionAction(fn (FormAction $action) => $action
                        ->label(__('site_text.actions.edit_group'))
                        ->modalHeading(__('site_text.modals.edit_group_heading'))
                        ->modalSubmitActionLabel(__('site_text.actions.save_group'))
                        ->modalWidth('3xl')
                    )
                    ->columnSpan(3),

                Forms\Components\TextInput::make('slug')
                    ->label(__('site_text.fields.slug'))
                    ->helperText(__('site_text.helpers.slug_example'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->rule('regex:/^[a-z0-9._-]+$/')
                    ->columnSpan(5),

                Forms\Components\TextInput::make('description')
                    ->label(__('site_text.fields.description'))
                    ->columnSpan(4),
                Translate::make()
                    ->locales($locales)
                    ->prefixLocaleLabel()
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(fn (string $locale) => [
                        Forms\Components\TextInput::make('value')
                            ->label(__('site_text.fields.value'))
                            ->required($locale === $defaultLocale),

                    ]),

            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
             //   Tables\Columns\TextColumn::make('group')->label('Группа')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('group.slug')
                    ->label(__('site_text.columns.group'))
                    ->sortable()
                    ->searchable(),
              //  Tables\Columns\TextColumn::make('slug')->label('Слаг')->sortable()->searchable(),
                TextColumn::make('slug')
                    ->label(__('site_text.columns.slug'))
                    ->sortable()
                    ->searchable()
                    ->copyable()                                 // вкл.
                    ->copyableState(fn ($record) => $record->slug) // ЧТО копировать
                    ->copyMessage(__('site_text.helpers.copy_message'))
                    ->copyMessageDuration(1500),
                Tables\Columns\TextColumn::make('value')
                    ->label(__('site_text.columns.value'))
                    ->formatStateUsing(fn($record) => str($record->getTranslation('value', app()->getLocale()))->limit(80))
                    ->wrap()
                    ->searchable(query: function ($query, $search) {
                        $locale = app()->getLocale();
                        return $query->where("value->$locale", 'like', "%{$search}%");
                    }),
                Tables\Columns\TextColumn::make('updated_at')->dateTime('Y-m-d H:i')->label(__('site_text.columns.updated_at'))->sortable(),
            ])
            ->filters([  Tables\Filters\SelectFilter::make('group_id')
                ->label(__('site_text.filters.group_id'))
                ->options(fn() => \App\Models\SiteTextGroup::query()
                    ->orderBy('position')
                    ->pluck('slug','id')
                    ->all()),])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
    public static function getActiveLocales(): array
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
            'index'  => Pages\ListSiteTexts::route('/'),
            'create' => Pages\CreateSiteText::route('/create'),
            'edit'   => Pages\EditSiteText::route('/{record}/edit'),
        ];
    }
}
