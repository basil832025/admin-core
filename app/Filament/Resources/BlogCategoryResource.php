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
    protected static ?string $navigationGroup = 'Контент';
    protected static ?string $navigationLabel = 'Категории статей';
    protected static ?string $modelLabel = 'Категория статей';
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $pluralModelLabel = 'Категории статей';
    // … навигация, иконки и т.д. …

    public static function form(Form $form): Form
    {
        // код основного языка
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        // список активных языков из таблицы languages
        $locales = static::getActiveLocales();

        return $form
            ->schema([
                Tabs::make('Контент')
                    ->columns(1)
                    ->tabs([
                        static::getMainTab($locales, $defaultLocale),
                        static::getSeoTab($locales),
                    ]),
            ])->columns(1);


    }
    protected static function getSeoTab(array $locales): Tab
    {
        return Tab::make('SEO')
            ->schema([
                Translate::make()
                    ->locales($locales)
                    ->prefixLocaleLabel()
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(fn(string $locale) => [
                        TextInput::make("meta_title")
                            ->label('Meta Title'),
                        Textarea::make("meta_description")
                            ->label('Meta Description')
                            ->rows(3),
                        TextInput::make("meta_keywords")
                            ->label('Meta Keywords'),
                    ]),
            ]);
    }
    protected static function getMainTab(array $locales, string $defaultLocale): Tab
    {
        return Tab::make('Основные')
            ->schema([
                Translate::make()
                    ->locales($locales)
                    ->prefixLocaleLabel()
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(fn(string $locale) => [
                        TextInput::make("name")
                            ->label('Заголовок')
                            ->required($locale === $defaultLocale),
                        RichEditor::make("description")
                            ->label('Описание')
                           // ->required($locale === $defaultLocale)
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('uploads')
                            ->fileAttachmentsVisibility('public'),
                    ]),
                TextInput::make('slug')
                    ->label('Slug')
                    ->disabledOn('edit')
                    ->required()
                    ->unique(table: BlogCategory::class, column: 'slug', ignorable: fn ($record) => $record)
                //->unique(table: Pages::class, column: 'slug', ignorable: fn($r) => $r)
                ,


                Section::make('Дополнительно')->schema([
                    TextInput::make('sort_order')
                        ->default(0)       // <-- значение по умолчанию
                        ->label('Порядок')
                        ->numeric(),
                    Toggle::make('is_active')
                        ->label('Активна')
                        ->inline(),
                    FileUpload::make('image')
                        ->label('Изображение')
                        ->directory('blog-categories')
                        ->visibility('public'),

                ])->columns(3),
            ])
            ->columns(1);
    }
    protected static function getActiveLocales(): array
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
                    ->label('Название')
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug'),

                IconColumn::make('is_active')
                    ->label('Активность')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Порядок'),

                TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Активные')
                    ->options([
                        1 => 'Да',
                        0 => 'Нет',
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
