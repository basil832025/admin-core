<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\PrroResource\Pages;
use App\Models\Shop\Prro;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class PrroResource extends Resource
{
    protected static ?string $model = Prro::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationLabel = 'ПРРО';
    protected static ?string $modelLabel = 'ПРРО';
    protected static ?string $pluralModelLabel = 'ПРРО';
    protected static ?string $navigationGroup = 'Настройки';
    protected static ?int $navigationSort = 96;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Данные ПРРО')
                ->columns(2)
                ->schema([
                    Forms\Components\DatePicker::make('registered_at')
                        ->label('Дата регистрации')
                        ->default(now()->toDateString())
                        ->required(),

                    Forms\Components\DatePicker::make('certificate_expires_at')
                        ->label('Дата окончания сертификата')
                        ->nullable(),

                    Forms\Components\TextInput::make('organization_name')
                        ->label('Название организации')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('prro_number')
                        ->label('Номер ПРРО')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('key_password')
                        ->label('Пароль ключа')
                        ->password()
                        ->revealable()
                        ->maxLength(255),

                    Forms\Components\FileUpload::make('certificate_path')
                        ->label('Сертификат')
                        ->disk('local')
                        ->directory('prro/certificates')
                        ->visibility('private')
                        ->helperText('Загрузите файл сертификата .CRT, .CER или .PEM')
                        ->downloadable()
                        ->openable()
                        ->required(fn (?Prro $record): bool => $record === null),

                    Forms\Components\FileUpload::make('key_path')
                        ->label('Ключ')
                        ->disk('local')
                        ->directory('prro/keys')
                        ->visibility('private')
                        ->helperText('Загрузите файл ключа .ZS2 или .JKS')
                        ->downloadable()
                        ->openable()
                        ->required(fn (?Prro $record): bool => $record === null),

                    Forms\Components\Toggle::make('use_for_liqpay')
                        ->label('Использовать в LiqPay на сайте')
                        ->helperText('Может быть включено только у одной записи')
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set): void {
                            if ($state) {
                                $set('is_active', true);
                            }
                        }),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Активность')
                        ->default(true)
                        ->required(),
                ]),

            Forms\Components\Section::make('Base64')
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('certificate_base64')
                        ->label('Сертификат (base64)')
                        ->rows(4)
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\Textarea::make('key_base64')
                        ->label('Ключ (base64)')
                        ->rows(4)
                        ->disabled()
                        ->dehydrated(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('registered_at')
                    ->label('Дата регистрации')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('certificate_expires_at')
                    ->label('Сертификат до')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('organization_name')
                    ->label('Организация')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('prro_number')
                    ->label('Номер ПРРО')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('use_for_liqpay')
                    ->label('LiqPay')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активность')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrros::route('/'),
            'create' => Pages\CreatePrro::route('/create'),
            'edit' => Pages\EditPrro::route('/{record}/edit'),
        ];
    }

    public static function prepareFormData(array $data, ?Prro $record = null): array
    {
        static::validateCertificateFilePath($data['certificate_path'] ?? $record?->certificate_path);
        static::validateKeyFilePath($data['key_path'] ?? $record?->key_path);

        $data['certificate_base64'] = static::resolveBase64ForPath(
            $data['certificate_path'] ?? null,
            $record?->certificate_path,
            $record?->certificate_base64,
        );

        $data['key_base64'] = static::resolveBase64ForPath(
            $data['key_path'] ?? null,
            $record?->key_path,
            $record?->key_base64,
        );

        if (! empty($data['use_for_liqpay'])) {
            $data['is_active'] = true;
        }

        return $data;
    }

    protected static function validateKeyFilePath(?string $path): void
    {
        if (! $path) {
            return;
        }

        Validator::make(
            ['key_path' => $path],
            ['key_path' => ['regex:/\.(zs2|jks)$/i']],
            ['key_path.regex' => 'Файл ключа должен быть формата .ZS2 или .JKS']
        )->validate();
    }

    protected static function validateCertificateFilePath(?string $path): void
    {
        if (! $path) {
            return;
        }

        Validator::make(
            ['certificate_path' => $path],
            ['certificate_path' => ['regex:/\.(crt|cer|pem)$/i']],
            ['certificate_path.regex' => 'Файл сертификата должен быть формата .CRT, .CER или .PEM']
        )->validate();
    }

    protected static function resolveBase64ForPath(?string $newPath, ?string $oldPath, ?string $oldBase64): ?string
    {
        $path = $newPath ?: $oldPath;
        if (! $path) {
            return null;
        }

        if ($oldPath === $path && ! empty($oldBase64)) {
            return $oldBase64;
        }

        if (! Storage::disk('local')->exists($path)) {
            return $oldBase64;
        }

        return base64_encode(Storage::disk('local')->get($path));
    }
}
