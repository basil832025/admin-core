<?php

namespace App\Filament\Resources\Shop;

use App\Enums\PaypartsBankTypeEnum;
use App\Filament\Resources\Shop\PaypartsBankResource\Pages;
use App\Models\Setting;
use App\Models\Shop\Client;
use App\Models\Shop\PaypartsBank;
use App\Services\PrivatBankPaypartsService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaypartsBankResource extends Resource
{
    protected static ?string $model = PaypartsBank::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Оплата частинами';
    protected static ?string $pluralModelLabel = 'Оплата частинами';
    protected static ?string $slug = 'shop/payparts-banks';
    protected static ?int $navigationSort = 19;

    protected static function canAccessModule(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        return (method_exists($user, 'hasRole') && $user->hasRole(config('shield.super_admin.name', 'super_admin')))
            || $user->can('view_any_shop::payparts::bank')
            || $user->can('create_shop::payparts::bank');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccessModule();
    }

    public static function canViewAny(): bool
    {
        return static::canAccessModule();
    }

    public static function canCreate(): bool
    {
        return static::canAccessModule();
    }

    public static function form(Form $form): Form
    {
        $locales = Setting::getActiveLocales();
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');

        return $form->schema([
            Section::make('Банк')
                ->schema([
                    Grid::make(12)->schema([
                        Select::make('bank_type')
                            ->label('Банк')
                            ->options(PaypartsBankTypeEnum::options())
                            ->native(false)
                            ->required()
                            ->columnSpan(4),

                        Toggle::make('is_active')
                            ->label('Активний')
                            ->default(true)
                            ->columnSpan(2),

                        Select::make('audience_mode')
                            ->label('Кому показувати')
                            ->options([
                                'all' => 'Всім',
                                'specific' => 'Конкретним клієнтам',
                            ])
                            ->default('all')
                            ->native(false)
                            ->columnSpan(3),

                        Select::make('audience_client_ids')
                            ->label('Тестові клієнти')
                            ->multiple()
                            ->searchable()
                            ->visible(fn (Forms\Get $get): bool => ($get('audience_mode') ?? 'all') === 'specific')
                            ->getSearchResultsUsing(function (string $search): array {
                                $digits = preg_replace('/\D+/', '', $search);

                                return Client::query()
                                    ->select('id', 'name', 'phone')
                                    ->when($search !== '', function ($query) use ($search): void {
                                        $query->where('name', 'like', "%{$search}%")
                                            ->orWhere('email', 'like', "%{$search}%");
                                    })
                                    ->when($digits !== '', function ($query) use ($digits): void {
                                        $query->orWhereRaw("REGEXP_REPLACE(phone, '[^0-9]', '') LIKE ?", ["%{$digits}%"]);
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (Client $client): array => [
                                        $client->id => $client->name . ' · ' . $client->phone_pretty,
                                    ])
                                    ->all();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                if (! $value) {
                                    return null;
                                }

                                $client = Client::query()->select('id', 'name', 'phone')->find($value);

                                return $client ? ($client->name . ' · ' . $client->phone_pretty) : null;
                            })
                            ->columnSpan(3),
                    ]),
                ]),

            Section::make('Тексти')
                ->schema([
                    Forms\Components\Tabs::make('payparts_locales')
                        ->tabs(array_map(
                            fn (string $locale) => Forms\Components\Tabs\Tab::make(strtoupper($locale))->schema([
                                TextInput::make("name.$locale")
                                    ->label('Назва банку')
                                    ->required($locale === $defaultLocale)
                                    ->maxLength(255),

                                RichEditor::make("description.$locale")
                                    ->label('Опис')
                                    ->columnSpanFull(),

                                RichEditor::make("terms.$locale")
                                    ->label('Умови кредитування')
                                    ->columnSpanFull(),
                            ]),
                            $locales
                        ))
                        ->columnSpanFull(),
                ]),

            Section::make('ПриватБанк')
                ->schema([
                    Grid::make(12)->schema([
                        TextInput::make('store_id')
                            ->label('storeId')
                            ->columnSpan(6),

                        TextInput::make('account_password')
                            ->label('Пароль акаунта')
                            ->password()
                            ->revealable()
                            ->columnSpan(6),
                    ]),
                ]),

            Section::make('PrivatBank API URL')
                ->description('URL для налаштування у кабінеті ПриватБанку та службові endpoint-и інтеграції.')
                ->schema([
                    Grid::make(12)->schema([
                        Forms\Components\Placeholder::make('payparts_response_url')
                            ->label('responseUrl')
                            ->content(fn (): string => PrivatBankPaypartsService::callbackUrl('payparts.response'))
                            ->columnSpan(6),

                        Forms\Components\Placeholder::make('payparts_redirect_url')
                            ->label('redirectUrl')
                            ->content(fn (): string => PrivatBankPaypartsService::callbackUrl('payparts.redirect'))
                            ->columnSpan(6),

                        Forms\Components\Placeholder::make('payparts_create_url')
                            ->label('create payment endpoint')
                            ->content(fn (): string => rtrim((string) config('services.payparts.privatbank.base_url'), '/') . (string) config('services.payparts.privatbank.create_path'))
                            ->columnSpan(6),

                        Forms\Components\Placeholder::make('payparts_payment_url')
                            ->label('customer redirect endpoint')
                            ->content(fn (): string => rtrim((string) config('services.payparts.privatbank.base_url'), '/') . (string) config('services.payparts.privatbank.payment_path') . '?token=...')
                            ->columnSpan(6),
                    ]),
                ]),

            Section::make('Розстрочки')
                ->description('Одна сума може підтримувати кілька варіантів PP / II.')
                ->schema([
                    Forms\Components\Repeater::make('rules')
                        ->label('Правила')
                        ->defaultItems(1)
                        ->reorderableWithButtons()
                        ->schema([
                            Grid::make(12)->schema([
                                TextInput::make('min_amount')
                                    ->label('Від суми')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step('0.01')
                                    ->required()
                                    ->columnSpan(4),

                                TextInput::make('parts_count')
                                    ->label('К-сть платежів')
                                    ->numeric()
                                    ->minValue(3)
                                    ->required()
                                    ->columnSpan(4),

                                Toggle::make('is_active')
                                    ->label('Активно')
                                    ->default(true)
                                    ->columnSpan(2),
                            ]),

                            Forms\Components\CheckboxList::make('merchant_types')
                                ->label('Типи оплати')
                                ->options([
                                    'pp' => 'PP — Оплата частинами',
                                    'ii' => 'II — Миттєва розстрочка',
                                ])
                                ->columns(2)
                                ->default(['pp'])
                                ->required(),
                        ])
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bank_type')
                    ->label('Банк')
                    ->formatStateUsing(fn (?string $state): string => PaypartsBankTypeEnum::tryFrom((string) $state)?->label() ?? (string) $state)
                    ->badge(),
                TextColumn::make('name')
                    ->label('Назва')
                    ->formatStateUsing(fn ($state): string => (string) data_get($state, app()->getLocale()) ?: (string) data_get($state, config('app.locale')) ?: '—'),
                TextColumn::make('audience_mode')
                    ->label('Показ')
                    ->formatStateUsing(fn (?string $state): string => $state === 'specific' ? 'Конкретним' : 'Всім'),
                IconColumn::make('is_active')
                    ->label('Активний')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Оновлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaypartsBanks::route('/'),
            'create' => Pages\CreatePaypartsBank::route('/create'),
            'edit' => Pages\EditPaypartsBank::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.shop');
    }
}
