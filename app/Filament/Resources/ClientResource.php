<?php

namespace App\Filament\Resources;
use App\Filament\Resources\ClientResource\RelationManagers\AddressesRelationManager;
use App\Models\Shop\Client;
use App\Models\Shop\ClientGroup;

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
    protected static ?string $navigationGroup = null;
    protected static ?string $navigationLabel = null;
    protected static ?string $modelLabel = null;
    protected static ?string $pluralModelLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('client.nav.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('client.nav.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('client.nav.plural_model_label');
    }
    public static function form(Form $form): Form
    {

        return $form ->schema([
            Forms\Components\Section::make(__('client.sections.main'))
                ->schema([
                    TextInput::make('name')->required()->label(__('client.fields.name')),
                    Grid::make(['default' => 1, 'lg' => 12])->schema([
                        TextInput::make('phone')
                            ->label(__('client.fields.phone'))
                            ->required()
                            ->tel()
                            ->columnSpan(['lg' => 8])
                            // Маска только для UA (цифра в маске — 9)
                            ->mask(fn (Get $get) => $get('is_foreign_phone') ? null : '(999) 999-99-99')
                            ->placeholder(fn (Get $get) => $get('is_foreign_phone')
                                ? __('client.placeholders.phone_foreign')
                                : __('client.placeholders.phone_ua'))
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
                            ->label(__('client.fields.is_foreign_phone'))
                            ->helperText(__('client.helpers.is_foreign_phone'))
                            ->inline(true)
                            ->live()                 // ← это ключ: заставит пересчитаться маска/placeholder у TextInput
                            ->dehydrated(false)
                            ->columnSpan(['lg' => 4])
                            ->extraAttributes(['class' => 'lg:mt-6']),
                    ]),
                    TextInput::make('email')->email()->label(__('client.fields.email')),
                    DatePicker::make('birthday')->label(__('client.fields.birthday')),
                    Select::make('gender')
                        ->label(__('client.fields.gender'))
                        ->options([
                            'male' => __('client.gender.male'),
                            'female' => __('client.gender.female'),
                        ])
                        ->nullable(),
                    TextInput::make('password')
                        ->password()
                        ->label(__('client.fields.password'))
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : null)
                        ->dehydrated(fn ($state) => filled($state)),
                    //  ->required(fn (string $context): bool => $context === 'create'),
                    FileUpload::make('photo')->image()->directory('clients')->label(__('client.fields.photo')),
                    Textarea::make('note')->label(__('client.fields.note')),
                    Select::make('client_group_id')
                        ->label('Группа клиента')
                        ->options(fn () => ClientGroup::query()
                            ->orderBy('id')
                            ->get()
                            ->mapWithKeys(fn (ClientGroup $group) => [$group->id => $group->display_name])
                            ->all())
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    Toggle::make('is_active')->label(__('client.fields.is_active'))->default(true),
                ])
                ->columns(2)
                ->columnSpan(['lg' => fn (?Client $record) => $record === null ? 3 : 2]),

            Forms\Components\Section::make(__('client.sections.metadata'))
                ->schema([
                    Forms\Components\Placeholder::make('created_at')
                        ->label(__('client.metadata.created_at'))
                        ->content(fn (Client $record): ?string => $record->created_at?->diffForHumans()),

                    Forms\Components\Placeholder::make('updated_at')
                        ->label(__('client.metadata.updated_at'))
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
                TextColumn::make('name')
                    ->searchable()
                    ->label(__('client.columns.name'))
                    ->color(fn (Client $record) => $record->group?->is_blacklist ? 'danger' : null),
                TextColumn::make('phone')->label(__('client.columns.phone')),
                TextColumn::make('email')->label(__('client.columns.email')),
                TextColumn::make('group.name')
                    ->label('Группа клиента')
                    ->formatStateUsing(fn (?string $state, Client $record): string => $record->group?->display_name ?? '—')
                    ->color(fn (Client $record) => $record->group?->is_blacklist ? 'danger' : null)
                    ->toggleable(),
                TextColumn::make('gender')->label(__('client.columns.gender')),
                BooleanColumn::make('is_active')->label(__('client.columns.is_active')),
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
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.shop');
    }

}
