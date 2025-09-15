<?php

namespace App\Filament\Resources;
use App\Filament\Resources\ClientResource\RelationManagers\AddressesRelationManager;
use App\Models\Shop\Client;

use App\Filament\Resources\ClientResource\Pages;

use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Resources\Resource;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Illuminate\Support\Str;
use Filament\Forms\Set;
use Filament\Forms\Get;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Магазин';
    protected static ?string $navigationLabel = 'Клиенты';
    protected static ?string $modelLabel = 'Клиент';
    protected static ?string $pluralModelLabel = 'Клиенты';
    public static function form(Form $form): Form
    {

        return $form ->schema([
            Forms\Components\Section::make()
                ->schema([
                    TextInput::make('name')->required()->label('ФИО'),
                    Grid::make(['default' => 1, 'lg' => 12])->schema([
                        TextInput::make('phone')
                            ->label('Телефон')
                            ->required()
                            ->tel()
                            ->columnSpan(['lg' => 8])
                            // Маска только для UA (цифра в маске — 9)
                            ->mask(fn (Get $get) => $get('is_foreign_phone') ? null : '(999) 999-99-99')
                            ->placeholder(fn (Get $get) => $get('is_foreign_phone')
                                ? 'Напр.: 491512345678 (лише цифри, 6–15)'
                                : '(067) 123-45-67')
                            ->extraAttributes(fn (Get $get) => [
                                'inputmode'    => 'numeric',
                                'autocomplete' => 'tel',
                                'pattern'      => $get('is_foreign_phone')
                                    ? '\+?\d{6,15}'
                                    : '\(0\d{2}\)\s\d{3}-\d{2}-\d{2}',
                            ])
                            // Авто-детект “иностранного” номера при редактировании
                            ->afterStateHydrated(function (TextInput $component, $state, Get $get, Set $set) {
                                $d = preg_replace('/\D+/', '', (string) $state);
                                if ($d === '') return;

                                // Если не 0XXXXXXXXX — считаем иностранным и включаем тумблер
                                if (! preg_match('/^0\d{9}$/', $d)) {
                                    $set('is_foreign_phone', true);          // тумблер “оживит” маску из-за ->live() ниже
                                    $component->state(substr($d, 0, 15));     // отобразим просто цифры
                                    return;
                                }

                                // Украина — красиво форматируем
                                if (str_starts_with($d, '380'))      $d = '0' . substr($d, 3);
                                elseif (str_starts_with($d, '80'))   $d = '0' . substr($d, 2);
                                elseif (strlen($d) === 9)            $d = '0' . $d;

                                $d = substr($d, 0, 10);
                                if (preg_match('/^(0\d{2})(\d{3})(\d{2})(\d{2})$/', $d, $m)) {
                                    $component->state(sprintf('(%s) %s-%s-%s', $m[1], $m[2], $m[3], $m[4]));
                                }
                            })
                            // В БД — только цифры (UA: 10; Intl: до 15)
                            ->dehydrateStateUsing(function ($state, Get $get) {
                                $d = preg_replace('/\D+/', '', (string) $state);

                                if ($get('is_foreign_phone')) {
                                    return substr($d, 0, 15);
                                }

                                if (str_starts_with($d, '380'))      $d = '0' . substr($d, 3);
                                elseif (str_starts_with($d, '80'))   $d = '0' . substr($d, 2);
                                elseif (strlen($d) === 9)            $d = '0' . $d;

                                return substr($d, 0, 10);
                            })
                            // Валидация по режиму
                            ->rule(fn (Get $get) => $get('is_foreign_phone')
                                ? 'regex:/^\+?\d{6,15}$/'
                                : 'regex:/^\(0\d{2}\)\s\d{3}-\d{2}-\d{2}$|^0\d{9}$/')
                            ->validationAttribute('телефон'),

                        Toggle::make('is_foreign_phone')
                            ->label('Телефон іншої країни')
                            ->helperText('Увімкніть, якщо номер не український')
                            ->inline(true)
                            ->live()                 // ← это ключ: заставит пересчитаться маска/placeholder у TextInput
                            ->dehydrated(false)
                            ->columnSpan(['lg' => 4])
                            ->extraAttributes(['class' => 'lg:mt-6']),
                    ]),
                    TextInput::make('email')->email(),
                    DatePicker::make('birthday')->label('Дата рождения'),
                    Select::make('gender')
                        ->options([
                            'male' => 'Мужчина',
                            'female' => 'Женщина',
                        ])
                        ->nullable(),
                    TextInput::make('password')
                        ->password()
                        ->label('Пароль')
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : null)
                        ->dehydrated(fn ($state) => filled($state)),
                    //  ->required(fn (string $context): bool => $context === 'create'),
                    FileUpload::make('photo')->image()->directory('clients')->label('Фото'),
                    Textarea::make('note')->label('Примечание'),
                    Toggle::make('is_active')->label('Активен')->default(true),
                ])
                ->columns(2)
                ->columnSpan(['lg' => fn (?Client $record) => $record === null ? 3 : 2]),

            Forms\Components\Section::make()
                ->schema([
                    Forms\Components\Placeholder::make('created_at')
                        ->label('Created at')
                        ->content(fn (Client $record): ?string => $record->created_at?->diffForHumans()),

                    Forms\Components\Placeholder::make('updated_at')
                        ->label('Last modified at')
                        ->content(fn (Client $record): ?string => $record->updated_at?->diffForHumans()),
                ])
                ->columnSpan(['lg' => 1])
                ->hidden(fn (?Client $record) => $record === null),
        ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->label('ФИО'),
                TextColumn::make('phone')->label('Телефон'),
                TextColumn::make('email'),
                TextColumn::make('gender')->label('Пол'),
                BooleanColumn::make('is_active')->label('Активен'),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
    public static function getRelations(): array
    {
        return [
            AddressesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
