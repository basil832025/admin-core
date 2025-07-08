<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LanguageResource\Pages;
use App\Filament\Resources\LanguageResource\RelationManagers;
use App\Models\Language;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\File;

class LanguageResource extends Resource
{
    protected static ?string $model = Language::class;
    protected static ?string $navigationGroup = 'Настройки';
    protected static ?string $navigationLabel = 'Языки';
    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Название языка')
                    ->required(),

                TextInput::make('code')
                    ->label('Код языка')
                    ->required()
                    ->readonly()  // запретить ручное редактирование
                    ->unique(
                        table: Language::class,
                        column: 'code',
                        ignorable: fn ($record) => $record,   // Filament сам возьмёт $record->id
                    )
                    ->maxLength(5),

                Select::make('country_code')
                    ->label('Страна')
                    //->options(self::getCountryOptions())
                    ->options(self::getCountryOptionsHtml())
                    ->allowHtml()
                  //  ->reactive() // чтобы события после обновления срабатывали
                    ->live(onBlur: true)
                    // как только пользователь выбрал страну — обновляем поле code
                  //  ->afterStateUpdated(function (Set $set, ?string $countryCode ) {
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        $cca3 = $get('country_code');
                       // $meta = self::getCountryMeta();
                        $meta = self::getCountryIso();
                        $cca3 = mb_strtolower($cca3);
                       // dd($meta);
                        // подставляем трёхбуквенный код (cca3) из вашего массива
                        $set('code', $meta[$cca3] ?? null);
                    })
                    // вот этот колбэк отдаёт Tom Select только подходящие options
                    ->getSearchResultsUsing(function (string $search): array {
                        // $search is exactly what the user has typed
                        return collect(static::getCountryOptionsHtml())
                            // strip HTML tags before comparing
                            ->filter(fn(string $htmlLabel, string $value) =>
                            str_contains(
                                mb_strtolower(strip_tags($htmlLabel)),
                                mb_strtolower($search),
                )
                            )
                            ->toArray();
                    })

                    ->searchable()
                    ->required(),
                TextInput::make('position')
                    ->label('Порядок')
                    ->numeric()
                    ->default(0)
                    ->helperText('Чем меньше — тем выше в списке'),

                Toggle::make('active')
                    ->label('Активен')
                    ->default(true)
                    ->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Язык'),
                TextColumn::make('code')->label('Код'),
                TextColumn::make('country_code')
                    ->label('Страна')
                    ->formatStateUsing(fn($state) => self::getCountryOptionsHtml()[$state] ?? $state)
                    ->html(),
                TextColumn::make('position')
                      ->label('Позиция')
                       ->sortable(),

                IconColumn::make('active')
                ->label('Активен')
                ->boolean()
                ->sortable(),

            TextColumn::make('updated_at')
                ->label('Изменён')
                ->dateTime(),
        ])
        ->defaultSort('position')

            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
    // читаем файл countries.json со всеми странами
    protected static function getCountryOptionsHtml(): array
    {
        $countries = collect(json_decode(
            file_get_contents(storage_path('app/data/countries.json')),
            true
        ));

        return $countries
            ->mapWithKeys(function ($item) {
                $code  = $item['cca3'];
                $code2  = $item['cca2'];
                $name  = $item['name']['common'];
                $url   = asset("vendor/countries/flags/".strtolower($code).".svg");

                // Собираем HTML-метку: <img> + текст
                $label = sprintf(
                    '<span class="inline-flex items-center gap-2 whitespace-nowrap">
                    <img src="%s" class="w-5 h-5 object-cover rounded" alt="%s">
                    <span>%s</span>
                 </span>',
                    $url,
                    e($name),
                    e($name),
            );
              /*  return [
                    $code2 => [
                        'html' => $label,
                        'cca3' => $code,
                    ],
                ];*/
                return [$code => $label];
            })
            ->toArray();
    }
    protected static function getCountryMeta(): array
    {
        $data = json_decode(file_get_contents(storage_path('app/data/countries.json')), true);

        return collect($data)
            ->mapWithKeys(fn($item) => [
                $item['cca3'] => $item['cca2'],
            ])->toArray();
    }
    protected static function getCountryIso(): array
    {
        $data = json_decode(file_get_contents(storage_path('app/data/countries_iso.json')), true);

        return collect($data)
            ->mapWithKeys(fn($item) => [
                $item['639-2'] => $item['639-1'],
            ])->toArray();
    }
  /* protected static function getCountryOptions(): array
    {
      //  $json = File::get(base_path('countries.json'));
     //   $countries = json_decode($json, true);
        $countries = collect(json_decode(
            file_get_contents(storage_path('app/data/countries.json')),
            true
        ));

        return $countries
            ->mapWithKeys(function ($item) {
                $code  = $item['cca2'];
                $name  = $item['name']['common'];


                return [$code => $name];
            })
            ->toArray();
    }*/
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLanguages::route('/'),
            'create' => Pages\CreateLanguage::route('/create'),
            'edit' => Pages\EditLanguage::route('/{record}/edit'),
        ];
    }
}
