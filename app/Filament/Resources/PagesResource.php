<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PagesResource\Pages\CreatePages;
use App\Filament\Resources\PagesResource\Pages\EditPages;
use App\Filament\Resources\PagesResource\Pages\ListPages;
use App\Models\Pages;
use Filament\Forms;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
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
//use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;
use AmidEsfahani\FilamentTinyEditor\TinyEditor;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
class PagesResource extends Resource
{
    use Translatable;
    protected static ?string $model = Pages::class;

    protected static ?string $navigationGroup = null;
    protected static ?string $navigationLabel = null;
    protected static ?string $navigationIcon  = 'heroicon-o-document-text';
    protected static ?int    $navigationSort  = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('page.nav.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('page.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('page.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('page.nav.plural_model_label');
    }
    public static function form(Forms\Form $form): Forms\Form
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        $locales = static::getActiveLocales();
    //    dd($locales);

        return $form
            ->schema([
                Tabs::make(__('page.tabs.content'))
                    ->columns(1)
                    ->tabs([
                        static::getMainTab($locales, $defaultLocale),
                        static::getSeoTab($locales),
                    ]),
            ])->columns(1);
    }
    protected static function getMainTab(array $locales, string $defaultLocale): Tab
    {
        return Tab::make(__('page.tabs.main'))
            ->schema([
                Translate::make()
                    ->locales($locales)
                    ->prefixLocaleLabel()
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(fn(string $locale) => [
                        TextInput::make("title")
                            ->label(__('page.fields.title'))
                            ->required($locale === $defaultLocale),
                        TinyEditor::make("content")
                            ->label(__('page.fields.content'))
                            ->maxHeight(1200)
                            ->profile('full')
                            //->required($locale === $defaultLocale)
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('uploads')
                            ->fileAttachmentsVisibility('public'),
                    ]),
                TextInput::make('slug')
                    ->label(__('page.fields.slug'))
                    ->disabledOn('edit')
                    ->required()
                    ->unique(table: Pages::class, column: 'slug', ignorable: fn ($record) => $record)
                //->unique(table: Pages::class, column: 'slug', ignorable: fn($r) => $r)
                ,
                Select::make('status')
                    ->label(__('page.fields.status'))
                    ->options([
                        'draft'     => __('page.statuses.draft'),
                        'published' => __('page.statuses.published'),
                    ])
                    ->default('draft')
                    ->required(),
                Forms\Components\Section::make(__('page.sections.builder'))
                    ->collapsible()
                    ->schema([
                        Builder::make('fields')
                            ->label(__('page.fields.fields'))
                            ->blocks([
                                Block::make('text')
                                    ->label(__('page.blocks.text'))
                                    ->icon('heroicon-m-chat-bubble-left-right')
                                    ->schema([
                                        Forms\Components\TextInput::make('slug')
                                            ->label(__('page.fields.slug_block'))->required()->rule('alpha_dash'),
                                        Translate::make()->locales($locales)->prefixLocaleLabel()->columns(1)
                                            ->statePath('values')
                                            ->schema(fn (string $locale) => [
                                                Forms\Components\TextInput::make('content')
                                                    ->label(__('page.fields.content_text'))
                                                    ->required($locale === $defaultLocale),
                                            ]),
                                    ]),

                                Block::make('rich')
                                    ->label(__('page.blocks.rich'))
                                    ->icon('heroicon-m-bars-3-bottom-left')
                                    ->schema([
                                        Forms\Components\TextInput::make('slug')
                                            ->label(__('page.fields.slug_block'))->required()->rule('alpha_dash'),
                                        Translate::make()->locales($locales)->prefixLocaleLabel()->columns(1)
                                            ->statePath('values')
                                            ->schema(fn (string $locale) => [
                                                TinyEditor::make("content")
                                                    ->label(__('page.fields.content_rich'))
                                                    ->options([
                                                        'toolbar_sticky' => false,
                                                        'fixed_toolbar_container' => '#tinymce-toolbar', // div над полем
                                                    ])
                                                    ->minHeight(150)        // минимальная высота (px)
                                                    ->maxHeight(1200)
                                                    //  ->maxHeight(240)        // максимальная высота (px)
                                                  //  ->resize(false)        // отключить ручное растягивание (опц.)
                                                    ->required($locale === $defaultLocale)
                                                    ->fileAttachmentsDisk('public')
                                                    ->fileAttachmentsDirectory('uploads')
                                                    ->fileAttachmentsVisibility('public'),
                                            ]),
                                    ]),

                                Block::make('image')
                                    ->label(__('page.blocks.image'))
                                    ->icon('heroicon-m-photo')
                                    ->schema([
                                        Forms\Components\TextInput::make('slug')
                                            ->label(__('page.fields.slug_block'))->required()->rule('alpha_dash'),
                                        Forms\Components\FileUpload::make('image')
                                            ->label(__('page.fields.image'))->disk('public')->directory('static')
                                            ->image()->visibility('public')
                                            ->downloadable(false)->openable()->imageEditor()->required(),
                                    ]),
                            ]),
                    ]),
            ])
            ->columns(1);
    }

    protected static function getSeoTab(array $locales): Tab
    {
        return Tab::make(__('page.tabs.seo'))
            ->schema([
                Translate::make()
                    ->locales($locales)
                    ->prefixLocaleLabel()
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(fn(string $locale) => [
                        TextInput::make("meta_title")
                            ->label(__('page.fields.meta_title')),
                        Textarea::make("meta_description")
                            ->label(__('page.fields.meta_description'))
                            ->rows(3),
                        TextInput::make("meta_keywords")
                            ->label(__('page.fields.meta_keywords')),
                    ]),
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
    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('page.columns.title'))
                    ->limit(50)
                    ->wrap(),

                TextColumn::make('slug')
                    ->label(__('page.columns.slug')),

                TextColumn::make('updated_at')
                    ->label(__('page.columns.updated_at'))
                    ->dateTime('d.m.Y H:i'),

                TextColumn::make('status')
                    ->label(__('page.columns.status'))
                    // сюда подставляем перевод/подпись
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft'     => __('page.statuses.draft'),
                        'published' => __('page.statuses.published'),
                        default     => $state,
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('page.filters.status'))
                    ->options([
                        'draft'     => __('page.statuses.draft'),
                        'published' => __('page.statuses.published'),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListPages::route('/'),
            'create' => CreatePages::route('/create'),
            'edit'   => EditPages::route('/{record}/edit'),
        ];
    }
}
