<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PagesResource\Pages\CreatePages;
use App\Filament\Resources\PagesResource\Pages\EditPages;
use App\Filament\Resources\PagesResource\Pages\ListPages;
use App\Models\Pages;
use Filament\Forms;
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
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
class PagesResource extends Resource
{
    use Translatable;
    protected static ?string $model = Pages::class;

    protected static ?string $navigationGroup = 'Контент';
    protected static ?string $navigationLabel = 'Страницы';
    protected static ?string $navigationIcon  = 'heroicon-o-document-text';
    protected static ?int    $navigationSort  = 2;
    public static function form(Forms\Form $form): Forms\Form
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');
        $locales = static::getActiveLocales();
    //    dd($locales);

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
                        TinyEditor::make("content")
                            ->label('Контент')
                            ->required($locale === $defaultLocale)
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('uploads')
                            ->fileAttachmentsVisibility('public'),
                    ]),
                TextInput::make('slug')
                    ->label('Slug')
                    ->disabledOn('edit')
                    ->required()
                    ->unique(table: Pages::class, column: 'slug', ignorable: fn ($record) => $record)
                //->unique(table: Pages::class, column: 'slug', ignorable: fn($r) => $r)
                ,
                Select::make('status')
                    ->label('Статус')
                    ->options([
                        'draft'     => 'Черновик',
                        'published' => 'Опубликована',
                    ])
                    ->default('draft')
                    ->required(),
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
                    ->label('Заголовок')
                    ->limit(50)
                    ->wrap(),

                TextColumn::make('slug')
                    ->label('Slug'),

                TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i'),

                TextColumn::make('status')
                    ->label('Статус')
                    // сюда подставляем перевод/подпись
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft'     => 'Черновик',
                        'published' => 'Опубликована',
                        default     => $state,
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'draft'     => 'Черновик',
                        'published' => 'Опубликована',
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
