<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlogCategoryResource\Pages\ListBlogCategories;
use App\Filament\Resources\BlogCategoryResource\Pages\CreateBlogCategory;
use App\Filament\Resources\BlogCategoryResource\Pages\EditBlogCategory;
use App\Models\BlogCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use SolutionForest\FilamentTranslateField\Forms\Component\Translate;
use App\Models\Setting;
use App\Models\Language;
use Filament\Resources\Concerns\Translatable;
use Illuminate\Support\Str;
use Filament\Forms\Set;
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;

class BlogCategoryResource extends Resource
{
    use Translatable;
    protected static ?string $model = BlogCategory::class;
    protected static ?string $navigationGroup = null;
    protected static ?string $navigationLabel = null;
    protected static ?string $modelLabel = null;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $pluralModelLabel = null;

    public static function getNavigationGroup(): ?string
    {
        return __('blog_category.nav.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('blog_category.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('blog_category.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('blog_category.nav.plural_model_label');
    }
    // … навигация, иконки и т.д. …

    public static function form(Form $form): Form
    {
        // код основного языка
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        // список активных языков из таблицы languages
        $locales = static::getActiveLocales();

        return $form
            ->schema([
                Tabs::make(__('blog_category.tabs.content'))
                    ->columns(1)
                    ->tabs([
                        static::getMainTab($locales, $defaultLocale),
                        static::getSeoTab($locales),
                    ]),
            ])->columns(1);


    }
    protected static function getSeoTab(array $locales): Tab
    {
        return Tab::make(__('blog_category.tabs.seo'))
            ->schema([
                Translate::make()
                    ->locales($locales)
                    ->prefixLocaleLabel()
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(fn(string $locale) => [
                        TextInput::make("meta_title")
                            ->label(__('blog_category.fields.meta_title')),
                        Textarea::make("meta_description")
                            ->label(__('blog_category.fields.meta_description'))
                            ->rows(3),
                        TextInput::make("meta_keywords")
                            ->label(__('blog_category.fields.meta_keywords')),
                    ]),
            ]);
    }
    protected static function getMainTab(array $locales, string $defaultLocale): Tab
    {
        return Tab::make(__('blog_category.tabs.main'))
            ->schema([
                Translate::make()
                    ->locales($locales)
                    ->prefixLocaleLabel()
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(fn(string $locale) => [
                        TextInput::make("name")
                            ->label(__('blog_category.fields.name'))
                            ->required($locale === $defaultLocale),
                        RichEditor::make("description")
                            ->label(__('blog_category.fields.description'))
                            // ->required($locale === $defaultLocale)
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('uploads')
                            ->fileAttachmentsVisibility('public'),
                    ]),
                TextInput::make('slug')
                    ->label(__('blog_category.fields.slug'))
                    ->disabledOn('edit')
                    ->required()
                    ->unique(table: BlogCategory::class, column: 'slug', ignorable: fn ($record) => $record)
                //->unique(table: Pages::class, column: 'slug', ignorable: fn($r) => $r)
                ,


                Section::make(__('blog_category.sections.additional'))->schema([
                    TextInput::make('sort_order')
                        ->default(0)       // <-- значение по умолчанию
                        ->label(__('blog_category.fields.sort_order'))
                        ->numeric(),
                    Toggle::make('is_active')
                        ->label(__('blog_category.fields.is_active'))
                        ->inline(),
                    FileUpload::make('image')
                        ->label(__('blog_category.fields.image'))
                        ->directory('blog-categories')
                        ->visibility('public'),

                ])->columns(3),
            ])
            ->columns(1);
    }
    public static function getActiveLocales(): array
    {
        return Language::where('active', true)
            ->orderBy('position')
            ->pluck('code')
            ->map(fn($c) => strtolower($c))
            ->toArray();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('blog_category.columns.name'))
                    ->sortable(),

                TextColumn::make('slug')
                    ->label(__('blog_category.columns.slug')),

                IconColumn::make('is_active')
                    ->label(__('blog_category.columns.is_active'))
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label(__('blog_category.columns.sort_order')),

                TextColumn::make('updated_at')
                    ->label(__('blog_category.columns.updated_at'))
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label(__('blog_category.filters.is_active'))
                    ->options([
                        1 => __('blog_category.filter_options.yes'),
                        0 => __('blog_category.filter_options.no'),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
    public static function getPages(): array
    {
        return [
            'index'  =>ListBlogCategories::route('/'),
            'create' => CreateBlogCategory::route('/create'),
            'edit'   => EditBlogCategory::route('/{record}/edit'),
        ];
    }
}
