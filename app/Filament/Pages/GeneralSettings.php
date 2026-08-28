<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Models\Callcenter\Source as CallcenterSource;
use App\Models\Shop\Client;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Pages\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;

// components
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Services\LoyaltyService;
use App\Services\NovaPostApiClient;
use App\Services\PrintNode\KitchenDuplicatePrintService;
use Illuminate\Support\Facades\Cache;
use Throwable;
use Wiebenieuwenhuis\FilamentCodeEditor\Components\CodeEditor;

class GeneralSettings extends Page implements Forms\Contracts\HasForms
{

    use Forms\Concerns\InteractsWithForms;
    public array $admin_settings = [];
    protected static ?string $navigationGroup = null;
    protected static ?string $navigationLabel = 'Общие настройки сайта';
    protected static ?string $navigationIcon  = 'heroicon-o-cog-6-tooth';
    protected static string $view             = 'filament.pages.general-settings';

    // публичные свойства для биндинга формы (можно оставить как есть)
    public $site_name;
    public $logo_path;
    public $favicon_path;
    public $phone;
    public $email;
    public $social_links = [];
    public $default_language_code;
    public $admin_color_scheme;
    public $cart_auth_method;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        if (! $user || !$user instanceof \App\Models\User) return false;

        $keys = [
            'page_' . Str::of(static::getSlug())->snake(),
            'page_' . class_basename(static::class),
        ];

        foreach ($keys as $key) {
            if ($user->can($key)) return true;
        }

        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected function getActions(): array
    {
        return [
            Action::make('save')
                ->label('Сохранить')
                ->button()
                ->color('primary')
                ->icon('heroicon-s-check')
                ->action('save'),

            Action::make('generate_sitemap')
                ->label('Сгенерировать sitemap.xml')
                ->button()
                ->color('gray')
                ->icon('heroicon-o-globe-alt')
                ->requiresConfirmation()
                ->modalHeading('Сгенерировать sitemap.xml')
                ->modalDescription('Файл будет записан в public/sitemap.xml. Это может занять немного времени.')
                ->action(function (): void {
                    try {
                        Artisan::call('seo:sitemap');

                        $path = public_path('sitemap.xml');
                        $mtime = is_file($path) ? date('Y-m-d H:i:s', (int) filemtime($path)) : null;

                        Notification::make()
                            ->title('Sitemap сгенерирован')
                            ->success()
                            ->body('URL: ' . url('/sitemap.xml') . ($mtime ? (" | " . $mtime) : ''))
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Ошибка генерации sitemap')
                            ->danger()
                            ->body($exception->getMessage())
                            ->send();
                    }
                }),

            Action::make('printnode_test')
                ->label('Тест друку чека')
                ->button()
                ->color('gray')
                ->icon('heroicon-o-printer')
                ->visible(fn (): bool => (string) request()->query('tab', '') !== '-callcenter-binotel-tab')
                ->modalHeading('Тестовий друк чека')
                ->modalDescription('Оберіть шаблон, перевірте превʼю з поточною шириною паперу та полями, після чого надрукуйте тест.')
                ->modalSubmitActionLabel('Печать')
                ->modalCancelActionLabel('Закрыть')
                ->fillForm(function (): array {
                    $preview = app(KitchenDuplicatePrintService::class)->buildTestPreview('kitchen');

                    return [
                        'template_type' => (string) ($preview['template_type'] ?? 'kitchen'),
                        'copies' => (int) ($preview['copies'] ?? 1),
                        'preview_html' => (string) ($preview['preview_html'] ?? ''),
                    ];
                })
                ->form([
                    Hidden::make('preview_html')
                        ->dehydrated(false),

                    Grid::make(12)
                        ->schema([
                            Select::make('template_type')
                                ->label('Шаблон тестового чека')
                                ->options([
                                    'kitchen' => 'Кухня',
                                    'client' => 'Клієнт',
                                    'courier' => 'Службовий (курʼєр)',
                                ])
                                ->default('kitchen')
                                ->live()
                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                    $preview = app(KitchenDuplicatePrintService::class)
                                        ->buildTestPreview((string) ($state ?: 'kitchen'));

                                    $set('preview_html', (string) ($preview['preview_html'] ?? ''));
                                })
                                ->required()
                                ->columnSpan(7),

                            TextInput::make('copies')
                                ->label('Кількість копій')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(20)
                                ->default(1)
                                ->required()
                                ->columnSpan(5),
                        ]),

                    Placeholder::make('preview')
                        ->label('Предпросмотр чека')
                        ->content(fn (Get $get): HtmlString => new HtmlString((string) ($get('preview_html') ?? '')))
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $templateType = (string) ($data['template_type'] ?? 'kitchen');
                    $copies = max(1, (int) ($data['copies'] ?? 1));

                    try {
                        $result = app(KitchenDuplicatePrintService::class)
                            ->sendTestReceipt($templateType, $copies);

                        Notification::make()
                            ->title('Тестовий друк відправлено')
                            ->success()
                            ->body('Шаблон: ' . (string) ($result['template_type'] ?? $templateType) . '. Копій: ' . $copies)
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Помилка тестового друку')
                            ->danger()
                            ->body($exception->getMessage())
                            ->send();
                    }
                }),
        ];
    }

    /** Основная схема формы: табы */
    protected function getFormSchema(): array
    {
        return [
            Tabs::make('settings_tabs')
                ->tabs([
                    static::generalTab(),
                    static::cartTab(),
                    static::novaPostTab(),
                    static::adminTab(),
                    static::binotelTab(),
                    static::printNodeTab(),
                ])
                ->persistTabInQueryString(),
        ];
    }

    /** Вкладка: Общие */
    protected static function generalTab(): Tab
    {
        return Tab::make('Загальні')->schema([
            TextInput::make('site_name')
                ->label('Название сайта')
                ->required(),

            Section::make('Баннери')
                ->description('Налаштування ротації банерів на головній сторінці.')
                ->schema([
                    Grid::make(12)
                        ->statePath('admin_settings')
                        ->schema([
                            TextInput::make('site.banner_rotation_delay_seconds')
                                ->label('Інтервал зміни банерів (сек)')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(120)
                                ->default(10)
                                ->helperText('Використовується для автоперемикання слайдера банерів (Swiper autoplay delay).')
                                ->columnSpan(6),
                        ]),
                ])
                ->compact(),

            Section::make('Контакты')
                ->description('Заполните ваши контактные данные:')
                ->schema([
                    TextInput::make('phone')->label('Телефон'),
                    TextInput::make('email')->label('Email'),
                ])
                ->columns(2)
                ->compact(),

            Section::make('Языки и цвета')
                ->description('Выберите язык по умолчанию для сайта и цветовую схему админки:')
                ->schema([
                    Select::make('default_language_code')
                        ->label('Язык по умолчанию')
                        ->options(\App\Models\Language::pluck('name', 'code'))
                        ->required(),

                    Select::make('admin_color_scheme')
                        ->label('Цветовая схема админки')
                        ->options([
                            'primary' => 'Amber (по умолчанию)',
                            'rose'    => 'Красная (Rose)',
                            'gray'    => 'Серая (Gray)',
                            'blue'    => 'Голубая (Blue)',
                            'emerald' => 'Зелёная (Emerald)',
                            'orange'  => 'Оранжевая (Orange)',
                        ])
                        ->default('primary'),
                ])
                ->columns(2)
                ->compact(),

            Group::make()
                ->schema([
                    FileUpload::make('logo_path')
                        ->label('Логотип')
                        ->image()
                        ->imageEditor()
                        ->directory('settings')
                        ->required(),

                    FileUpload::make('favicon_path')
                        ->label('Favicon')
                        ->image()
                        ->directory('settings'),
                ])
                ->columns(2)
                ->columnSpanFull()
                ->label('Изображения сайта'),

            Repeater::make('social_links')
                ->label('Соцсети')
                ->schema([
                    TextInput::make('platform')
                        ->label('Платформа (Facebook, Instagram…)')
                        ->required(),
                    TextInput::make('url')
                        ->label('Ссылка')
                        ->url()
                        ->required(),
                ])
                ->columns(2),
        ]);
    }

    /** Вкладка: Корзина */
    protected static function cartTab(): Tab
    {
        return Tab::make('Корзина')->schema([
            Section::make('Варианты авторизации на сайте')
                ->description('Выберите способ авторизации пользователей на сайте:')
                ->schema([
                    Select::make('cart_auth_method')
                        ->label('Варианты авторизации на сайте')
                        ->options([
                            'phone_sms' => 'Только телефон и SMS',
                            'phone_password_sms' => 'Телефон и пароль (+ SMS)',
                        ])
                        ->default('phone_password_sms')
                        ->required()
                        ->helperText('Выберите способ авторизации: только по телефону с SMS-кодом или телефон + пароль с дополнительной SMS-подтверждением'),
                ])
                ->compact(),

            Section::make('Доставка')
                ->description('Параметры доставки в корзине и checkout.')
                ->schema([
                    TextInput::make('cart.free_shipping_from')
                        ->label('Сума від якої безкоштовна доставка')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->suffix('₴')
                        ->helperText('0 — безкоштовна доставка вимкнена, доставка завжди платна.'),
                ])
                ->statePath('admin_settings')
                ->compact(),

            Section::make('Товары в заказе')
                ->description('Вид списка товаров в заказе коллцентра.')
                ->schema([
                    Select::make('callcenter.order_items_view')
                        ->label('Вид по умолчанию')
                        ->options([
                            'compact' => 'Товары в заказе компактным списком',
                            'photo' => 'Товары с фото',
                        ])
                        ->default('compact')
                        ->native(false)
                        ->required(),
                ])
                ->statePath('admin_settings')
                ->compact(),

            Section::make('Фільтри статусів колцентру')
                ->description('Оберіть, які вкладки статусів показувати на сторінці замовлень колцентру. Вкладка «Всі» показується завжди.')
                ->schema([
                    CheckboxList::make('callcenter.order_status_tabs')
                        ->label('Вкладки статусів')
                        ->options(fn (): array => collect(OrderStatus::sorted())
                            ->mapWithKeys(fn (OrderStatus $status): array => [
                                $status->value => $status->getLabel(),
                            ])
                            ->all())
                        ->default(fn (): array => collect(OrderStatus::sorted())
                            ->map(fn (OrderStatus $status): string => $status->value)
                            ->all())
                        ->columns(3)
                        ->bulkToggleable(),
                ])
                ->statePath('admin_settings')
                ->compact(),

            Section::make('Бонусна програма')
                ->description('Оберіть, від якої суми нараховувати бонуси клієнту.')
                ->schema([
                    Select::make('loyalty.earn_base_mode')
                        ->label('База для нарахування бонусів')
                        ->options(LoyaltyService::earnBaseModeOptions())
                        ->default(LoyaltyService::EARN_BASE_NET_AFTER_DISCOUNTS)
                        ->native(false)
                        ->required()
                        ->helperText('Поточний вибір впливає і на розрахунок у кошику, і на фактичне нарахування бонусів після оплати замовлення.'),
                ])
                ->statePath('admin_settings')
                ->compact(),

            Section::make('Оплата частинами')
                ->description('Увімкніть метод на сайті та оберіть, кому показувати кнопку у кошику.')
                ->schema([
                    Grid::make(12)
                        ->schema([
                            Toggle::make('payparts.enabled')
                                ->label('Увімкнути оплату частинами')
                                ->default(false)
                                ->columnSpan(4),

                            Select::make('payparts.button_audience')
                                ->label('Кому показувати кнопку')
                                ->options([
                                    'all' => 'Всім',
                                    'specific' => 'Конкретним клієнтам',
                                ])
                                ->default('all')
                                ->live()
                                ->afterStateUpdated(function (Set $set): void {
                                    $set('payparts.button_client_ids', []);
                                })
                                ->native(false)
                                ->columnSpan(4),

                            Select::make('payparts.button_client_ids')
                                ->label('Тестові клієнти')
                                ->multiple()
                                ->searchable()
                                ->visible(fn (Get $get): bool => ($get('payparts.button_audience') ?? 'all') === 'specific')
                                ->getSearchResultsUsing(function (string $search): array {
                                    $digits = preg_replace('/\D+/', '', $search);

                                    return Client::query()
                                        ->select('id', 'name', 'surname', 'phone')
                                        ->when($search !== '', function ($query) use ($search): void {
                                            $query->where('name', 'like', "%{$search}%")
                                                ->orWhere('surname', 'like', "%{$search}%")
                                                ->orWhere('email', 'like', "%{$search}%");
                                        })
                                        ->when($digits !== '', function ($query) use ($digits): void {
                                            $query->orWhereRaw("REGEXP_REPLACE(phone, '[^0-9]', '') LIKE ?", ["%{$digits}%"]);
                                        })
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn (Client $client): array => [
                                            $client->id => $client->full_name . ' · ' . $client->phone_pretty,
                                        ])
                                        ->all();
                                })
                                ->getOptionLabelUsing(function ($value): ?string {
                                    if (! $value) {
                                        return null;
                                    }

                                    $client = Client::query()->select('id', 'name', 'surname', 'phone')->find($value);

                                    return $client ? ($client->full_name . ' · ' . $client->phone_pretty) : null;
                                })
                                ->columnSpan(4),
                        ]),
                ])
                ->statePath('admin_settings')
                ->compact(),
        ]);
    }

    /** Вкладка: Нова пошта */
    protected static function novaPostTab(): Tab
    {
        return Tab::make('Нова пошта')->schema([
            Section::make('API')
                ->description('Ключ можна залишити в .env. Поле нижче потрібне, якщо хочете керувати ключем через адмінку.')
                ->schema([
                    TextInput::make('nova_post.key')
                        ->label('API ключ')
                        ->password()
                        ->revealable()
                        ->placeholder('Якщо порожньо, використовується NOVA_POST_KEY з .env')
                        ->helperText('Ключ з бізнес-кабінету Нової пошти. Не обовʼязково дублювати, якщо NOVA_POST_KEY вже заданий.'),
                ])
                ->statePath('admin_settings')
                ->compact(),

            Section::make('Відправник')
                ->description('Ці Ref потрібні для створення ТТН методом InternetDocument.save.')
                ->schema([
                    Grid::make(12)
                        ->schema([
                            Select::make('nova_post.sender_ref')
                                ->label('Sender Ref')
                                ->placeholder('Оберіть відправника з API')
                                ->helperText('Дані ФОП/ТОВ, на якого оформлена НП.')
                                ->options(fn (): array => static::novaPostSenderOptions())
                                ->getOptionLabelUsing(fn ($value): ?string => filled($value) ? static::novaPostSenderOptions()[$value] ?? (string) $value : null)
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(function (Set $set): void {
                                    $set('nova_post.sender_contact_ref', null);
                                })
                                ->native(false)
                                ->columnSpan(6),

                            Select::make('nova_post.sender_contact_ref')
                                ->label('Contact Sender Ref')
                                ->placeholder('Оберіть контактну особу')
                                ->helperText('ПІБ контактної особи відправника.')
                                ->options(fn (Get $get): array => static::novaPostSenderContactOptions((string) $get('nova_post.sender_ref')))
                                ->getOptionLabelUsing(fn ($value, Get $get): ?string => filled($value)
                                    ? static::novaPostSenderContactOptions((string) $get('nova_post.sender_ref'))[$value] ?? (string) $value
                                    : null)
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->columnSpan(6),

                            Select::make('nova_post.sender_city_ref')
                                ->label('City Sender Ref')
                                ->placeholder('Почніть вводити місто')
                                ->default(config('services.nova_post.sender_city_ref'))
                                ->helperText('Місто відправки, наприклад Київ.')
                                ->getSearchResultsUsing(fn (string $search): array => static::novaPostCityOptions($search))
                                ->getOptionLabelUsing(fn ($value): ?string => filled($value)
                                    ? static::novaPostChoiceLabel('city', (string) $value) ?? (string) $value
                                    : null)
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function (Set $set): void {
                                    $set('nova_post.sender_address_ref', null);
                                })
                                ->native(false)
                                ->columnSpan(6),

                            Select::make('nova_post.sender_address_ref')
                                ->label('Sender Address Ref')
                                ->placeholder('Оберіть відділення відправки')
                                ->helperText('Відділення НП, з якого відправляють.')
                                ->options(fn (Get $get): array => static::novaPostSenderAddressOptions((string) $get('nova_post.sender_city_ref'), '', 20))
                                ->getSearchResultsUsing(fn (string $search, Get $get): array => static::novaPostSenderAddressOptions((string) $get('nova_post.sender_city_ref'), $search))
                                ->getOptionLabelUsing(fn ($value): ?string => filled($value)
                                    ? static::novaPostChoiceLabel('warehouse', (string) $value) ?? (string) $value
                                    : null)
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->columnSpan(6),

                            TextInput::make('nova_post.sender_phone')
                                ->label('Телефон відправника')
                                ->placeholder('380XXXXXXXXX')
                                ->tel()
                                ->helperText('Телефон у форматі 380..., без +.')
                                ->maxLength(20)
                                ->columnSpan(6),
                        ]),
                ])
                ->statePath('admin_settings')
                ->compact(),

            Section::make('Параметри посилки за замовчуванням')
                ->description('Використовуються для оцінки вартості та створення ТТН, якщо менеджер не вказав інше.')
                ->schema([
                    Grid::make(12)
                        ->schema([
                            TextInput::make('nova_post.default_weight')
                                ->label('Вага, кг')
                                ->numeric()
                                ->step(0.1)
                                ->minValue(0.1)
                                ->default(config('services.nova_post.price_weight', '0.5'))
                                ->helperText('За замовчуванням 0,5 кг.')
                                ->columnSpan(4),

                            TextInput::make('nova_post.default_length_cm')
                                ->label('Довжина, см')
                                ->numeric()
                                ->step(1)
                                ->minValue(1)
                                ->default(20)
                                ->columnSpan(4),

                            TextInput::make('nova_post.default_width_cm')
                                ->label('Ширина, см')
                                ->numeric()
                                ->step(1)
                                ->minValue(1)
                                ->default(15)
                                ->columnSpan(4),

                            TextInput::make('nova_post.default_height_cm')
                                ->label('Висота, см')
                                ->numeric()
                                ->step(1)
                                ->minValue(1)
                                ->default(10)
                                ->helperText('VolumeGeneral рахується автоматично: довжина × ширина × висота / 1 000 000. Для 20 × 15 × 10 см = 0.003 м³.')
                                ->columnSpan(4),

                            TextInput::make('nova_post.default_seats_amount')
                                ->label('Кількість місць')
                                ->numeric()
                                ->minValue(1)
                                ->default(1)
                                ->helperText('За замовчуванням 1.')
                                ->columnSpan(4),

                            TextInput::make('nova_post.default_cost')
                                ->label('Оціночна вартість за замовчуванням')
                                ->numeric()
                                ->step(1)
                                ->minValue(1)
                                ->default(config('services.nova_post.price_cost', '500'))
                                ->suffix('₴')
                                ->columnSpan(4),

                            TextInput::make('nova_post.default_description')
                                ->label('Опис відправлення')
                                ->placeholder('Парфуми')
                                ->default('Парфуми')
                                ->maxLength(120)
                                ->columnSpan(8),

                            Select::make('nova_post.default_payer_type')
                                ->label('Платник доставки')
                                ->options([
                                    'Recipient' => 'Отримувач',
                                    'Sender' => 'Відправник',
                                ])
                                ->default('Recipient')
                                ->native(false)
                                ->columnSpan(3),

                            Select::make('nova_post.default_payment_method')
                                ->label('Оплата доставки')
                                ->options([
                                    'Cash' => 'Готівка',
                                    'NonCash' => 'Безготівково',
                                ])
                                ->default('Cash')
                                ->native(false)
                                ->columnSpan(3),
                        ]),
                ])
                ->statePath('admin_settings')
                ->compact(),
        ]);
    }

    /** Вкладка: Админ-панель (Filament) — JSON admin_settings */
    protected static function adminTab(): Tab
    {
        return Tab::make('Адмін-панель (Filament)')->schema([
            // вся вложенная схема пишет в JSON поле admin_settings
            Section::make('Навигация и сайдбар')
                ->schema([
                    Grid::make(12)
                        ->statePath('admin_settings')
                        ->schema([
                            Toggle::make('sidebar.collapsible_on_desktop')
                                ->label('Кнопка «гамбургер» на десктопі')
                                ->default(true)
                                ->columnSpan(6),

                            Toggle::make('sidebar.fully_collapsible_on_desktop')
                                ->label('Повне згортання сайдбару (іконки)')
                                ->default(false)
                                ->columnSpan(6),

                            Select::make('nav.position')
                                ->label('Положення меню (кастом)')
                                ->options([
                                    'sidebar' => 'Сайдбар',
                                    'top'     => 'Верхнє меню',
                                    'left'  => 'Левое меню',
                                ])
                                ->native(false)
                                ->columnSpan(6),
                        ]),
                ]),

            Section::make('Тема и лейаут')
                ->schema([
                    Grid::make(12)
                        ->statePath('admin_settings')
                        ->schema([
                            Toggle::make('theme.dark_mode')
                                ->label('Темний режим')
                                ->default(false)
                                ->columnSpan(6),

                            Select::make('layout.max_content_width')
                                ->label('Макс. ширина контенту')
                                ->options([
                                    '7xl' => '7xl',
                                    'full' => 'Full',
                                ])
                                ->native(false)
                                ->columnSpan(6),
                        ]),
                ]),
        ]);
    }

    protected static function binotelTab(): Tab
    {
        return Tab::make('Callcenter / Binotel')->schema([
            Section::make('Callcenter / Binotel')
                ->description('Налаштування відповідності вхідних ліній Binotel до сайту замовлення.')
                ->schema([
                    Repeater::make('callcenter.binotel.rules')
                        ->label('Правила визначення сайту за лінією')
                        ->addActionLabel('Додати правило')
                        ->collapsed()
                        ->reorderableWithButtons()
                        ->defaultItems(0)
                        ->schema([
                            Grid::make(12)
                                ->schema([
                                    TextInput::make('point_name')
                                        ->label('Точка')
                                        ->placeholder('Наприклад: Три пирога')
                                        ->required()
                                        ->maxLength(120)
                                        ->columnSpan(5),

                                    Select::make('source_id')
                                        ->label('Сайт')
                                        ->options(fn (): array => [
                                            '0' => 'Основний сайт',
                                        ] + CallcenterSource::query()
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->mapWithKeys(fn ($name, $id) => [(string) $id => (string) $name])
                                            ->toArray())
                                        ->required()
                                        ->searchable()
                                        ->default('0')
                                        ->columnSpan(5),

                                    Toggle::make('is_active')
                                        ->label('Активно')
                                        ->default(true)
                                        ->columnSpan(2),
                                ]),

                            Repeater::make('phones')
                                ->label('Номери ліній (тільки цифри)')
                                ->addActionLabel('Додати номер')
                                ->defaultItems(1)
                                ->columns(1)
                                ->schema([
                                    TextInput::make('number')
                                        ->label('Телефон лінії')
                                        ->placeholder('0442999148')
                                        ->required()
                                        ->rule('regex:/^[0-9]+$/')
                                        ->minLength(10)
                                        ->maxLength(16)
                                        ->dehydrateStateUsing(fn ($state): string => preg_replace('/\D+/', '', (string) $state) ?: '')
                                        ->helperText('Вводьте лише цифри без пробілів, скобок, дефісів та +.')
                                        ->columnSpanFull(),
                                ])
                                ->itemLabel(fn (array $state): string => (string) ($state['number'] ?? 'Номер')),
                        ])
                        ->itemLabel(function (array $state): string {
                            $point = trim((string) ($state['point_name'] ?? ''));
                            $site = trim((string) ($state['source_id'] ?? ''));

                            if ($point !== '') {
                                return $point;
                            }

                            return $site !== '' ? ('Сайт #' . $site) : 'Правило';
                        }),
                ])
                ->statePath('admin_settings'),
        ]);
    }

    protected static function printNodeTab(): Tab
    {
        return Tab::make('PrintService')->schema([
            Section::make('PrintService (дублікат чека на кухню)')
                ->description('Налаштування автоматичного друку при переведенні замовлення у статус «На кухні». Принтер вказується як printer_selector для Windows Agent.')
                ->schema([
                    Grid::make(12)
                        ->statePath('admin_settings')
                        ->schema([
                            Toggle::make('printservice.enabled')
                                ->label('Увімкнути інтеграцію PrintService')
                                ->default(false)
                                ->columnSpan(6),

                            Toggle::make('printservice.trigger_on_processing')
                                ->label('Друкувати при статусі «На кухні»')
                                ->default(true)
                                ->columnSpan(6),

                            TextInput::make('printservice.api_key')
                                ->label('Site API Key (обовʼязково)')
                                ->password()
                                ->revealable()
                                ->columnSpan(12),

                            TextInput::make('printservice.api_base_url')
                                ->label('Base URL (домен сервиса)')
                                ->placeholder('https://printservice.test')
                                ->helperText('Шлях /api/print/v1 додається автоматично.')
                                ->default('https://printservice.test')
                                ->columnSpan(8),

                            TextInput::make('printservice.tenant_code')
                                ->label('Tenant code')
                                ->default('default')
                                ->columnSpan(4),

                            TextInput::make('printservice.printer_selector')
                                ->label('Printer selector (як в Windows Agent)')
                                ->placeholder('Наприклад: Microsoft Print to PDF або EPSON TM-T20')
                                ->columnSpan(12),

                            CodeEditor::make('printservice.report_css_global')
                                ->key('printservice_report_css_global_editor')
                                ->label('Глобальний CSS для звітів')
                                ->helperText('Застосовується до всіх шаблонів типу "Отчет". Локальний custom_css у шаблоні підключається після цього блоку і може перевизначати стилі.')
                                ->default('table.report { width: 100%; border-collapse: collapse; }')
                                ->columnSpan(12),
                        ]),
                ]),
        ]);
    }

    protected static function defaultReceiptTemplate(): string
    {
        return '<div style="text-align:center;font-weight:700;font-size:14pt;margin-bottom:2mm;">{{kitchen_header}}</div>'
            . '<div><strong>Заказ №:</strong> {{order_number}}</div>'
            . '<div><strong>Работник:</strong> {{operator}}</div>'
            . '<div><strong>Время печати:</strong> {{printed_at}}</div>'
            . '<div><strong>Контактный телефон:</strong> {{phone}}</div>'
            . '<div><strong>Время доставки:</strong> {{delivery_time}}</div>'
            . '<div><strong>Время выдачи:</strong> {{issued_time}}</div>'
            . '<div><strong>Тип:</strong> {{delivery_type}}</div>'
            . '<div><strong>Примечание:</strong> {{note}}</div>'
            . '<div><strong>Адрес:</strong> {{address}}</div>'
            . '<hr>'
            . '<div>{{items}}</div>'
            . '<hr>'
            . '<div style="font-size:13pt;font-weight:700;">Сумма: {{total}}</div>';
    }

    protected static function templateByKey(?string $key): string
    {
        return match ((string) $key) {
            'compact_58' => '<div style="text-align:center;font-weight:700;">{{kitchen_header}}</div>'
                . '<div><strong>Заказ:</strong> {{order_number}}</div>'
                . '<div><strong>Печать:</strong> {{printed_at}}</div>'
                . '<div><strong>Тел:</strong> {{phone}}</div>'
                . '<div><strong>Дост:</strong> {{delivery_time}}</div>'
                . '<div><strong>Выд:</strong> {{issued_time}}</div>'
                . '<hr><div>{{items}}</div><hr>'
                . '<div style="font-weight:700;">ИТОГО: {{total}}</div>',
            'compact_80' => '<div style="text-align:center;font-weight:700;font-size:13pt;margin-bottom:2mm;">{{kitchen_header}}</div>'
                . '<div><strong>Заказ №:</strong> {{order_number}}</div>'
                . '<div><strong>Работник:</strong> {{operator}}</div>'
                . '<div><strong>Печать:</strong> {{printed_at}}</div>'
                . '<div><strong>Телефон:</strong> {{phone}}</div>'
                . '<div><strong>Доставка:</strong> {{delivery_time}}</div>'
                . '<div><strong>Выдача:</strong> {{issued_time}}</div>'
                . '<div><strong>Тип:</strong> {{delivery_type}}</div>'
                . '<div><strong>Адрес:</strong> {{address}}</div>'
                . '<hr><div>{{items}}</div><hr>'
                . '<div style="font-weight:700;">Сумма: {{total}}</div>',
            default => static::defaultReceiptTemplate(),
        };
    }

    protected static function defaultClientReceiptTemplate(): string
    {
        return '<div style="text-align:center;font-weight:700;font-size:14pt;margin-bottom:1mm;">ЧЕК ДЛЯ КЛІЄНТА</div>'
            . '{{client_logo}}'
            . '<div><strong>Замовлення №:</strong> {{order_number}}</div>'
            . '<div><strong>Час друку:</strong> {{printed_at}}</div>'
            . '<div><strong>Телефон:</strong> {{phone}}</div>'
            . '<div><strong>Час доставки:</strong> {{delivery_time}}</div>'
            . '<div><strong>Тип:</strong> {{delivery_type}}</div>'
            . '<div><strong>Адреса:</strong> {{address}}</div>'
            . '<hr><div>{{items}}</div><hr>'
            . '<div style="font-size:13pt;font-weight:700;">До сплати: {{total}}</div>'
            . '<div style="text-align:center;margin-top:2mm;">ДЯКУЄМО ЗА ЗАМОВЛЕННЯ</div>';
    }

    protected static function clientTemplateByKey(?string $key): string
    {
        return match ((string) $key) {
            'client_compact' => '<div style="text-align:center;font-weight:700;">ЧЕК КЛІЄНТА</div>'
                . '{{client_logo}}'
                . '<div><strong>Замовлення:</strong> {{order_number}}</div>'
                . '<div><strong>Друк:</strong> {{printed_at}}</div>'
                . '<div><strong>Тел:</strong> {{phone}}</div>'
                . '<div><strong>Доставка:</strong> {{delivery_time}}</div>'
                . '<hr><div>{{items}}</div><hr>'
                . '<div style="font-weight:700;">До сплати: {{total}}</div>',
            default => static::defaultClientReceiptTemplate(),
        };
    }

    protected static function defaultCourierReceiptTemplate(): string
    {
        return '<div style="text-align:center;font-weight:700;font-size:14pt;margin-bottom:2mm;">СЛУЖБОВИЙ ЧЕК КУРʼЄРА</div>'
            . '<div><strong>Замовлення №:</strong> {{order_number}}</div>'
            . '<div><strong>Друк:</strong> {{printed_at}}</div>'
            . '<div><strong>Оператор:</strong> {{operator}}</div>'
            . '<div><strong>Клієнт:</strong> {{client_name}}</div>'
            . '<div><strong>Телефон:</strong> {{phone}}</div>'
            . '<div><strong>Час доставки:</strong> {{delivery_time}}</div>'
            . '<div><strong>Тип:</strong> {{delivery_type}}</div>'
            . '<div><strong>Адреса:</strong> {{address}}</div>'
            . '<div><strong>Коментар:</strong> {{note}}</div>'
            . '<hr><div>{{items}}</div><hr>'
            . '<div style="font-size:13pt;font-weight:700;">До сплати: {{total}}</div>';
    }

    protected static function courierTemplateByKey(?string $key): string
    {
        return match ((string) $key) {
            'courier_compact' => '<div style="text-align:center;font-weight:700;">СЛУЖБОВИЙ (КУРʼЄР)</div>'
                . '<div><strong>Замовлення:</strong> {{order_number}}</div>'
                . '<div><strong>Друк:</strong> {{printed_at}}</div>'
                . '<div><strong>Клієнт:</strong> {{client_name}}</div>'
                . '<div><strong>Тел:</strong> {{phone}}</div>'
                . '<div><strong>Адреса:</strong> {{address}}</div>'
                . '<hr><div>{{items}}</div><hr>'
                . '<div style="font-weight:700;">До сплати: {{total}}</div>',
            default => static::defaultCourierReceiptTemplate(),
        };
    }

    protected static function novaPostSenderOptions(): array
    {
        try {
            return collect(app(NovaPostApiClient::class)->senderCounterparties())
                ->mapWithKeys(fn (array $sender): array => [(string) $sender['ref'] => (string) $sender['label']])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected static function novaPostSenderContactOptions(string $senderRef): array
    {
        try {
            return collect(app(NovaPostApiClient::class)->senderContactPersons($senderRef))
                ->mapWithKeys(fn (array $contact): array => [(string) $contact['ref'] => (string) $contact['label']])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected static function novaPostChoiceCacheKey(string $type, string $ref): string
    {
        return 'nova_post:general_settings_choice:' . $type . ':' . $ref;
    }

    protected static function rememberNovaPostChoice(string $type, string $ref, string $label): string
    {
        if ($ref !== '' && $label !== '') {
            Cache::put(static::novaPostChoiceCacheKey($type, $ref), $label, now()->addHours(6));
        }

        return $ref;
    }

    protected static function novaPostChoiceLabel(string $type, string $ref): ?string
    {
        $ref = trim($ref);

        return $ref !== '' ? Cache::get(static::novaPostChoiceCacheKey($type, $ref)) : null;
    }

    protected static function novaPostCityOptions(string $search): array
    {
        if (mb_strlen(trim($search)) < 2) {
            return [];
        }

        try {
            return collect(app(NovaPostApiClient::class)->searchCities($search, 20))
                ->mapWithKeys(function (array $city): array {
                    $ref = (string) $city['ref'];
                    $label = (string) ($city['label'] ?? $city['display_name'] ?? $city['name'] ?? $city['ref']);

                    return [static::rememberNovaPostChoice('city', $ref, $label) => $label];
                })
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected static function novaPostSenderAddressOptions(string $cityRef, string $search = '', int $limit = 30): array
    {
        if (! Str::isUuid(trim($cityRef))) {
            return [];
        }

        try {
            return collect(app(NovaPostApiClient::class)->searchWarehouses($cityRef, $search, $limit, 'warehouse'))
                ->mapWithKeys(function (array $warehouse): array {
                    $ref = (string) $warehouse['ref'];
                    $label = (string) ($warehouse['label'] ?? $warehouse['name'] ?? $warehouse['ref']);

                    return [static::rememberNovaPostChoice('warehouse', $ref, $label) => $label];
                })
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    public function mount(): void
    {
        $settings = Setting::current();

        // гарантируем массив для JSON
        $this->admin_settings = $this->normalizeAdminSettings($settings->admin_settings ?? []);

        // можно просто отдать все данные + подставить admin_settings
        $data = $settings->toArray();
        $data['admin_settings'] = $this->admin_settings;

        $this->form->fill($data);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // на всякий случай
        $data['admin_settings'] = $this->normalizeAdminSettings($data['admin_settings'] ?? $this->admin_settings ?? []);

        $settings = Setting::current();
        $settings->update($data);

        $fresh = $settings->fresh();
        if ($fresh) {
            $formData = $fresh->toArray();
            $formData['admin_settings'] = $this->normalizeAdminSettings($fresh->admin_settings ?? []);
            $this->form->fill($formData);
        }

        Notification::make()
            ->title('Успех')
            ->success()
            ->body('Настройки сохранены')
            ->send();
    }

    private function normalizeAdminSettings(array $adminSettings): array
    {
        $nestedPayparts = data_get($adminSettings, 'admin_settings.payparts');

        if (! data_get($adminSettings, 'payparts') && is_array($nestedPayparts)) {
            data_set($adminSettings, 'payparts', $nestedPayparts);
        }

        data_forget($adminSettings, 'admin_settings.payparts');

        if (data_get($adminSettings, 'admin_settings') === []) {
            data_forget($adminSettings, 'admin_settings');
        }

        return $adminSettings;
    }

    public function sendPrintNodeTest(): void
    {
        try {
            app(KitchenDuplicatePrintService::class)->sendTestReceipt('kitchen', 1);

            Notification::make()
                ->title('Тестовий друк відправлено')
                ->success()
                ->body('Перевірте принтер та чергу PrintService')
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Помилка тестового друку')
                ->danger()
                ->body($exception->getMessage())
                ->send();
        }
    }
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settings');
    }

}
