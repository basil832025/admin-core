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
class PagesResource extends Resource
{
    use Translatable;
    protected static ?string $model = Pages::class;

    protected static ?string $navigationGroup = 'Контент';
    protected static ?string $navigationLabel = 'Страницы';
    protected static ?string $navigationIcon  = 'heroicon-o-document-text';

    public static function form(Forms\Form $form): Forms\Form
    {
        // пытаемся взять из БД, иначе берём из конфига
        $defaultLocale = Setting::value('default_language_code')
            ?: config('app.locale');
      //  dd($defaultLocale);
        // получаем из БД массив кодов активных языков,
        // например ['uk','en','ru'] (вместо ['ua',...])
        $locales = Language::query()
            ->where('active', true)       // если есть флаг активности
            ->orderBy('position')         // если нужна сортировка
            ->pluck('code')               // вытягиваем колонку code
            ->map(fn($c) => strtolower($c)) // опционально: приводим к lower-case
            ->toArray();

        return $form
            ->schema(array(
                TextInput::make('slug')
                    ->label('Slug')
                    ->disabledOn('edit')
                    // требуем только на создании

                    ->required()
                  //  ->unique(Article::class, 'slug', ignoreRecord: true)
                    ->unique(table: Pages::class, column: 'slug', ignorable: fn ($record) => $record)
                    // отключаем в режиме редактирования
                  //  ->disabled(fn ($livewire) => $livewire->getRecord() !== null)
                   ,
                Translate::make()
                    // вот эта строка критична — без неё Translate вообще не клонирует поля
                    ->locales($locales)
                    // опционально: префиксы/суффиксы языков в метках
                    ->prefixLocaleLabel()
                    ->columnSpanFull()
                    // ->columns(2)
                    ->schema(fn(string $locale) => array(
                TextInput::make('title')
                    ->label('Заголовок')
                  //  ->reactive()
                    ->live(onBlur: true)

                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state)))

                    // ->translatable()
                   ->required($locale === $defaultLocale),

                RichEditor::make('content')
                    ->label('Контент')
                   // ->translatable()
                   ->required($locale === $defaultLocale),

                Section::make('SEO')
                    ->schema(array(
                        TextInput::make('meta_title')
                            ->label('Meta Title')
                         //   ->translatable()
                            ,

                        TextInput::make('meta_description')
                            ->label('Meta Description')
                            //->translatable()
                        ,

                        TextInput::make('meta_keywords')
                            ->label('Meta Keywords')
                            //->translatable()
                        ,
                    ))
                    ->collapsible(),
                    ))
                    ->fieldTranslatableLabel(fn($field, $locale) => __($field->getName(), locale: $locale)),
                Select::make('status')
                    ->label('Статус')
                    ->options(array(
                        'draft'     => 'Черновик',
                        'published' => 'Опубликована',
                    ))
                    ->default('draft')    // ← вот здесь
                    ->required()
            ));
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
