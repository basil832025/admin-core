<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlogResource\Pages;
use App\Filament\Resources\BlogResource\RelationManagers;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Language;
use App\Models\Setting;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use SolutionForest\FilamentTranslateField\Forms\Component\Translate;
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;
use Filament\Resources\Concerns\Translatable;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BlogResource extends Resource
{
    use Translatable;
    protected static ?string $model = Blog::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    //protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Блоги/статьи/новости';
    protected static ?string $modelLabel = 'Блог';
    protected static ?string $pluralModelLabel = 'Блоги/посты/новости';
    protected static ?string $navigationGroup = 'Контент';
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
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
                        TextInput::make("title")
                            ->label('Заголовок')
                            ->required($locale === $defaultLocale),
                        RichEditor::make("anons")
                            ->label('Анонс')
                            ->disableToolbarButtons([ // убираем кнопки какието
                                'attachFiles',

                            ])
                             ->required($locale === $defaultLocale)
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('uploads')
                            ->fileAttachmentsVisibility('public'),
                        TinyEditor::make("content")
                            ->label('полный текст')
                             ->required($locale === $defaultLocale)
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('uploads')
                            ->fileAttachmentsVisibility('public'),
                    ]),
                TextInput::make('slug')
                    ->label('Slug')
                    ->disabledOn('edit')
                 //   ->required()
                    ->unique(table: Blog::class, column: 'slug', ignorable: fn ($record) => $record)
                //->unique(table: Pages::class, column: 'slug', ignorable: fn($r) => $r)
                ,


                Section::make('Дополнительно')->schema([
                    Grid::make(3)
                        ->schema([
                    Select::make('blog_category_id')
                        ->relationship('category', 'name')
                        ->label('Категория')
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
                                ->label('Опубликовано')
                                ->default(true)
                                ->columnSpan(1),
                            DateTimePicker::make('published_at')
                                ->label('Дата публикации')
                                ->default(Carbon::now())
                                ->columnSpan(1),
                    tagsinput::make('tags')->label('Теги')->columnSpan(3),


                    Forms\Components\FileUpload::make('preview_image')->label('Изображение анонса')
                        ->image()
                        //->required()
                        ->directory('articles')->columnSpan(1),

                    Forms\Components\FileUpload::make('detail_image')->label('Детальное изображение')
                        ->image()
                        //->required()
                        ->directory('articles')->columnSpan(1),
                        ]),
                ])->columns(3),
            ])
            ->columns(1);
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
    public static function table(Table $table): Table
    {
     //   $locale = app()->getLocale();
     //   $fallback = config('app.fallback_locale');
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        // список активных языков из таблицы languages
        $locales = static::getActiveLocales();
         $locale = app()->getLocale() ?? $defaultLocale;
          //  dd(app()->getLocale());
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('preview_image')->label('Изображение анонса'),
                TextColumn::make('title')
                    ->label('Название')
                    ->sortable(),

                Tables\Columns\TextColumn::make('BlogCategory.name')
                    ->label('Категория')

                ->getStateUsing(function (Blog $record, TextColumn $column, $livewire) {
                    $locale = $livewire->activeLocale;
                  //  dd($locale);
                        // Тут можно делать сколько угодно dd() или других вызовов:
                        // Debug: какую строку реально отдаёт getRawOriginal()
                     //   $raw = $record->category?->getRawOriginal('name') ?? '[]';

                        // Декодируем в ассоциативный массив
                      //  $names = json_decode($raw, true) ?: [];
                        // Возвращаем нужный перевод или дефолт
                        return $record->category
                            // используя Spatie, вытянем нужный перевод:
                            ? $record->category->getTranslation('name', $locale)
                            : '—';
                      /*  return $names[$defaultLocale]
                            ?? $names[$defaultLocale]
                            ?? '—';*/
                    })

               /*     ->getStateUsing(fn (Blog $record) =>
                    ($record->BlogCategory->name[$defaultLocale] ?? $record->BlogCategory->name[$defaultLocale])
                    )*/
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_published')->label('Активность')
                    ->boolean(),
                Tables\Columns\TextColumn::make('published_at')->label('Опубликовано')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
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
}
