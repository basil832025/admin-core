<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlogResource\Pages;
use App\Filament\Resources\BlogResource\RelationManagers;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Language;
use App\Models\Setting;
use App\Models\Shop\ProductCategory;
use Carbon\Carbon;

use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use  Filament\Tables\Actions;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Support\Arr;
use SolutionForest\FilamentTranslateField\Forms\Component\Translate;
//use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;
use AmidEsfahani\FilamentTinyEditor\TinyEditor;
use Filament\Resources\Concerns\Translatable;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Columns\ImageColumn;

class BlogResource extends Resource
{
    use Translatable;
    protected static ?string $model = Blog::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    //protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = null;
    protected static ?string $modelLabel = null;
    protected static ?string $pluralModelLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('blog.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('blog.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('blog.nav.plural_model_label');
    }
    protected static ?string $navigationGroup = null;
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
    public static function form(Form $form): Form
    {
        // код основного языка
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        // список активных языков из таблицы languages
        $locales = \App\Models\Setting::getActiveLocales();            // ['uk','en','ru']
        $localeOptions = collect($locales)->mapWithKeys(
            fn ($v) => [$v => $v]
        )->all();                                         // ['uk'=>'uk','en'=>'en','ru'=>'ru']
        return $form
            ->schema([
                Tabs::make(__('blog.tabs.content'))
                    ->columns(1)
                    ->tabs([
                        static::getMainTab($locales, $defaultLocale,$localeOptions),
                        static::getSeoTab($locales),
                    ]),
            ])
            ->columns(1);



    }
    protected static function getMainTab(array $locales, string $defaultLocale,array $localeOptions): Tab
    {
     //   dd($localeOptions);
        return Tab::make(__('blog.tabs.main'))
            ->schema([
                Translate::make()
                    ->locales($locales)
                    ->prefixLocaleLabel()
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(fn(string $locale) => [
                        TextInput::make("title")
                            ->label(__('blog.fields.title'))
                            ->required($locale === $defaultLocale),
                        RichEditor::make("anons")
                            ->label(__('blog.fields.anons'))
                            ->disableToolbarButtons([ // убираем кнопки какието
                                'attachFiles',

                            ])
                            ->required($locale === $defaultLocale)
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('uploads')
                            ->fileAttachmentsVisibility('public'),
                        TinyEditor::make("content")
                            ->label(__('blog.fields.content'))
                            ->required($locale === $defaultLocale)
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('uploads')
                            ->fileAttachmentsVisibility('public'),
                    ]),
                TextInput::make('slug')
                    ->label(__('blog.fields.slug'))
                    ->disabledOn('edit')
                    //   ->required()
                    ->unique(table: Blog::class, column: 'slug', ignorable: fn ($record) => $record)
                //->unique(table: Pages::class, column: 'slug', ignorable: fn($r) => $r)
                ,


                Section::make(__('blog.sections.additional'))->schema([
                    Grid::make(3)
                        ->schema([
                            Select::make('blog_category_id')
                                ->relationship('category', 'name')
                                ->label(__('blog.fields.blog_category_id'))
                                // вернем на языке по умоллчанию название категорий
                                ->options(function () use ($defaultLocale) {
                                    return BlogCategory::query()
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
                                ->required()
                                ->columnSpan(1),
                            Toggle::make('is_published')
                                ->label(__('blog.fields.is_published'))
                                ->default(true)
                                ->columnSpan(1),
                            DateTimePicker::make('published_at')
                                ->label(__('blog.fields.published_at'))
                                ->default(Carbon::now())
                                ->columnSpan(1),
                            tagsinput::make('tags')->label(__('blog.fields.tags'))->columnSpan(3),


                    /*        Forms\Components\FileUpload::make('preview_image')->label('Изображение анонса')
                                ->image()
                                //->required()
                                ->directory('articles')->columnSpan(1),

                            Forms\Components\FileUpload::make('detail_image')->label('Детальное изображение')
                                ->image()
                                //->required()
                                ->directory('articles')->columnSpan(1),*/
                        ]),
                ])->columns(3),

                Forms\Components\Section::make(__('blog.sections.images'))
                    ->columns(1)
                    ->schema([

                        // ---- Превью (дефолт) ----
                        Forms\Components\FileUpload::make('preview_image')
                            ->label(__('blog.placeholders.preview_default'))
                            ->disk('public')->directory('blogs/preview')->image(),

                        // ---- Превью по языкам ----
                        Forms\Components\Repeater::make('preview_image_i18n_rows')
                            ->label(__('blog.fields.preview_image_i18n'))
                            ->columns(2)
                            ->default([])                                      // ВАЖНО
                            ->schema([
                                Forms\Components\Select::make('lang')
                                    ->label(__('blog.fields.language'))
                                    ->options($localeOptions)
                                    ->required()
                                    ->disablePlaceholderSelection(),

                                Forms\Components\FileUpload::make('file')
                                    ->label(__('blog.fields.file'))
                                    ->disk('public')->directory('blogs/preview')->image()
                                    ->maxFiles(1)
                                    ->dehydrateStateUsing(function ($state) {
                                        if (is_array($state)) {
                                            $p = $state['path'] ?? $state['url'] ?? $state['file'] ?? (is_string(reset($state)) ? reset($state) : null);
                                            return $p;
                                        }
                                        return $state;
                                    })
                                    ->required(),
                            ])

                            ->reactive()


                            ->reorderable(false),
                        Hidden::make('preview_image_i18n')
                            ->dehydrateStateUsing(function (Get $get) use ($locales) {
                                $rows    = (array) $get('preview_image_i18n_rows');
                                $allowed = array_flip($locales);
                                $map     = [];

                                foreach ($rows as $r) {
                                    $l = (string) ($r['lang'] ?? '');

                                    // нормализуем file -> строка
                                    $val = $r['file'] ?? null;
                                    $p   = '';
                                    if (is_string($val)) {
                                        $p = $val;
                                    } elseif (is_array($val)) {
                                        // самые частые варианты состояния FileUpload
                                        $p = $val['path'] ?? $val['url'] ?? $val['file'] ?? '';
                                        // иногда внутри может лежать строка первым элементом
                                        if ($p === '' && ($first = reset($val)) && is_string($first)) {
                                            $p = $first;
                                        }
                                    }

                                    if ($l !== '' && $p !== '' && isset($allowed[$l])) {
                                        $map[$l] = $p;
                                    }
                                }

                                return $map; // сохранится как JSON (cast 'array')
                            })
                            ->default([]),
                        // ---- Детальная (дефолт) ----
                        Forms\Components\FileUpload::make('detail_image')
                            ->label(__('blog.placeholders.detail_default'))
                            ->disk('public')->directory('blogs/detail')->image(),

                        // ---- Детальные по языкам ----
                        Forms\Components\Repeater::make('detail_image_i18n_rows')
                            ->label(__('blog.fields.detail_image_i18n'))
                            ->columns(2)
                            ->default([])
                            ->schema([
                                Forms\Components\Select::make('lang')
                                    ->label(__('blog.fields.language'))
                                    ->options($localeOptions)
                                    ->required()
                                    ->disablePlaceholderSelection(),

                                Forms\Components\FileUpload::make('file')
                                    ->label(__('blog.fields.file'))
                                    ->disk('public')->directory('blogs/detail')
                                    ->image()
                                    ->maxFiles(1)
                                    ->dehydrateStateUsing(function ($state) {
                                        if (is_array($state)) {
                                            $p = $state['path'] ?? $state['url'] ?? $state['file'] ?? null;
                                            if (! $p && ($first = reset($state)) && is_string($first)) $p = $first;
                                            return $p;
                                        }
                                        return $state;
                                    })
                                    ->required(),
                            ])// для детальных
                            ->reactive()

                            ->reorderable(false),
                        // JSON: детальные по языкам
                        Hidden::make('detail_image_i18n')
                            ->dehydrateStateUsing(function (Get $get) use ($locales) {
                                $rows    = (array) $get('detail_image_i18n_rows');
                                $allowed = array_flip($locales);
                                $map     = [];

                                foreach ($rows as $r) {
                                    $l = (string) ($r['lang'] ?? '');

                                    $val = $r['file'] ?? null;
                                    $p   = '';
                                    if (is_string($val)) {
                                        $p = $val;
                                    } elseif (is_array($val)) {
                                        $p = $val['path'] ?? $val['url'] ?? $val['file'] ?? '';
                                        if ($p === '' && ($first = reset($val)) && is_string($first)) {
                                            $p = $first;
                                        }
                                    }

                                    if ($l !== '' && $p !== '' && isset($allowed[$l])) {
                                        $map[$l] = $p;
                                    }
                                }

                                return $map;
                            })
                            ->default([]),
        ])
            ])
            ->columns(1);
    }
    protected static function getSeoTab(array $locales): Tab
    {
        return Tab::make(__('blog.tabs.seo'))
            ->schema([
                Translate::make()
                    ->locales($locales)
                    ->prefixLocaleLabel()
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(fn(string $locale) => [
                        TextInput::make("meta_title")
                            ->label(__('blog.fields.meta_title')),
                        Textarea::make("meta_description")
                            ->label(__('blog.fields.meta_description'))
                            ->rows(3),
                        TextInput::make("meta_keywords")
                            ->label(__('blog.fields.meta_keywords')),
                    ]),
            ]);
    }
    public static function table(Table $table): Table
    {
        //   $locale = app()->getLocale();
        //   $fallback = config('app.fallback_locale');
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        // список активных языков из таблицы languages
        $locales = \App\Models\Setting::getActiveLocales();
        $locale = app()->getLocale() ?? $defaultLocale;
        //  dd(app()->getLocale());
        return $table
            ->columns([
                ImageColumn::make('preview_image')->label(__('blog.columns.preview_image')),
                TextColumn::make('title')
                    ->label(__('blog.columns.title'))

                    ->sortable(),

                TextColumn::make('BlogCategory.name')
                    ->label(__('blog.columns.category'))

                    ->getStateUsing(function (Blog $record, TextColumn $column, $livewire) use ($locale) {
                      //  $locale = $livewire->activeLocale;

                        // Возвращаем нужный перевод или дефолт
                        return $record->category
                            // используя Spatie, вытянем нужный перевод:
                            ? $record->category->getTranslation('name', $locale)
                            : '—';

                    })

                    /*     ->getStateUsing(fn (Blog $record) =>
                         ($record->BlogCategory->name[$defaultLocale] ?? $record->BlogCategory->name[$defaultLocale])
                         )*/
                    ->numeric()
                    ->sortable(),

               TextColumn::make('slug')
                    ->searchable(),
                IconColumn::make('is_published')->label(__('blog.columns.is_published'))
                    ->boolean(),
                TextColumn::make('published_at')->label(__('blog.columns.published_at'))
                    ->dateTime()
                    ->sortable(),
               TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ]) ->defaultSort('created_at', 'desc')
            ->persistFiltersInSession()
            ->filtersFormColumns(2) // сколько колонок занимают фильтры в строке
            ->filters([
                SelectFilter::make('category')
                    ->label(__('blog.filters.category'))
                    ->columnSpan(2)
                    ->placeholder(__('blog.filters.category_all'))                         // вместо «Всі»
                    // ->searchPrompt('Введите текст для поиска…')
                    ->relationship('category', 'id') // фильтруем по belongsTo 'category'
                    ->getOptionLabelFromRecordUsing(function (BlogCategory $record): string {
                        $locale = Setting::value('default_language_code') ?: app()->getLocale();
                   //     dd($record);
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
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
 /*   public static function getActiveLocales(): array
    {
        return Language::where('active', true)
            ->orderBy('position')
            ->pluck('code')
            ->map(fn($c) => strtolower($c))
            ->toArray();
    }*/
    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            Pages\ViewBlog::class,           // ваш класс View
            Pages\EditBlog::class,           // ваш класс Edit
            Pages\ManageBlogComments::class, // ваш кастомный Comments
        ]);
    }
    public static function getRelations(): array
    {
        return [
            //
        ];
    }
// Чтобы всегда подгружать связь:
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('category');
    }
    public static function getPages(): array
    {
        return [

            'index'    => Pages\ListBlogs::route('/'),
            'create'   => Pages\CreateBlog::route('/create'),
            'view'     => Pages\ViewBlog::route('/{record}'),
            'edit'     => Pages\EditBlog::route('/{record}/edit'),
            'comments' => Pages\ManageBlogComments::route('/{record}/comments'),

        ];
    }
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.content');
    }

}
