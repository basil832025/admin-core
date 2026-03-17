<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Pages\Actions\Action;
use Filament\Notifications\Notification;
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
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Services\PrintNode\KitchenDuplicatePrintService;
use AmidEsfahani\FilamentTinyEditor\TinyEditor;

class GeneralSettings extends Page implements Forms\Contracts\HasForms
{

    use Forms\Concerns\InteractsWithForms;
    public array $admin_settings = [];
    protected static ?string $navigationGroup = 'Настройки';
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

    protected function getActions(): array
    {
        return [
            Action::make('save')
                ->label('Сохранить')
                ->button()
                ->color('primary')
                ->icon('heroicon-s-check')
                ->action('save'),
            Action::make('printnode_test')
                ->label('Тест друку чека')
                ->button()
                ->color('gray')
                ->icon('heroicon-o-printer')
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
                    static::adminTab(),
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

    protected static function printNodeTab(): Tab
    {
        return Tab::make('PrintNode')->schema([
            Section::make('PrintNode (дублікат чека на кухню)')
                ->description('Налаштування автоматичного друку при переведенні замовлення у статус «На кухні». Принтер можна вказати за назвою як у Windows/PrintNode.')
                ->schema([
                    Grid::make(12)
                        ->statePath('admin_settings')
                        ->schema([
                            Toggle::make('printnode.enabled')
                                ->label('Увімкнути інтеграцію PrintNode')
                                ->default(false)
                                ->columnSpan(6),

                            Toggle::make('printnode.trigger_on_processing')
                                ->label('Друкувати при статусі «На кухні»')
                                ->default(true)
                                ->columnSpan(6),

                            TextInput::make('printnode.api_key')
                                ->label('PrintNode API Key')
                                ->password()
                                ->revealable()
                                ->columnSpan(12),

                            TextInput::make('printnode.printer_name')
                                ->label('Назва принтера (як в Windows / PrintNode)')
                                ->placeholder('Наприклад: Microsoft Print to PDF або EPSON TM-T20')
                                ->helperText('Якщо заповнено ID принтера, назва ігнорується.')
                                ->columnSpan(8),

                            TextInput::make('printnode.printer_id')
                                ->label('PrintNode printerId (необов’язково)')
                                ->numeric()
                                ->minValue(1)
                                ->columnSpan(4),

                            TextInput::make('printnode.kitchen_duplicate_copies')
                                ->label('Кількість копій дубліката')
                                ->numeric()
                                ->minValue(1)
                                ->default(2)
                                ->columnSpan(4),

                            Select::make('printnode.content_type')
                                ->label('Формат відправки в принтер')
                                ->options([
                                    'auto' => 'Авто (PDF для PDF-принтерів, RAW для термопринтерів)',
                                    'pdf_base64' => 'Завжди PDF',
                                    'raw_base64' => 'Завжди RAW',
                                ])
                                ->default('auto')
                                ->columnSpan(8),

                            Select::make('printnode.raw_encoding')
                                ->label('Кодування RAW друку')
                                ->options([
                                    'utf-8' => 'UTF-8 (типово)',
                                    'cp866' => 'CP866 (для деяких термопринтерів)',
                                ])
                                ->default('utf-8')
                                ->columnSpan(8),

                            Select::make('printnode.template_key')
                                ->label('Готовий шаблон')
                                ->options([
                                    'classic' => 'Класичний (як дублікат доставки)',
                                    'compact_58' => 'Компактний 58мм',
                                    'compact_80' => 'Компактний 80мм',
                                ])
                                ->default('classic')
                                ->live()
                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                    $set('printnode.receipt_template', static::templateByKey($state));
                                    $set('printnode.template_overwrite_pending', true);
                                })
                                ->helperText('При виборі шаблону текст нижче буде замінено, але запис в БД відбудеться тільки після натискання "Зберегти".')
                                ->columnSpan(6),

                            Hidden::make('printnode.template_overwrite_pending')
                                ->dehydrated(false)
                                ->default(false),

                            Placeholder::make('printnode.template_overwrite_warning')
                                ->label('')
                                ->content('Увага: шаблон чека підставлено у форму. Зміни будуть застосовані тільки після натискання кнопки "Зберегти".')
                                ->visible(fn (Get $get): bool => (bool) ($get('printnode.template_overwrite_pending') ?? false))
                                ->columnSpan(12),

                            Select::make('printnode.pdf_paper_preset')
                                ->label('Розмір PDF (preset)')
                                ->options([
                                    '58mm' => '58 мм термочек',
                                    '80mm' => '80 мм термочек',
                                    'custom' => 'Кастомний',
                                ])
                                ->default('80mm')
                                ->columnSpan(6),

                            TextInput::make('printnode.pdf_page_width_mm')
                                ->label('Ширина чека (мм)')
                                ->numeric()
                                ->step(0.1)
                                ->default(80)
                                ->columnSpan(3),

                            TextInput::make('printnode.pdf_page_height_mm')
                                ->label('Висота чека (мм)')
                                ->numeric()
                                ->step(0.1)
                                ->default(180)
                                ->helperText('Можна збільшити, якщо обрізає низ чека.')
                                ->columnSpan(3),

                            TextInput::make('printnode.pdf_font_size_pt')
                                ->label('Розмір шрифту (pt)')
                                ->numeric()
                                ->step(0.5)
                                ->default(10)
                                ->columnSpan(3),

                            TextInput::make('printnode.pdf_line_height')
                                ->label('Міжрядковий інтервал')
                                ->numeric()
                                ->step(0.05)
                                ->default(1.25)
                                ->columnSpan(3),

                            TextInput::make('printnode.pdf_margin_top_mm')
                                ->label('Відступ зверху (мм)')
                                ->numeric()
                                ->step(0.1)
                                ->default(3)
                                ->columnSpan(3),

                            TextInput::make('printnode.pdf_margin_right_mm')
                                ->label('Відступ справа (мм)')
                                ->numeric()
                                ->step(0.1)
                                ->default(2)
                                ->columnSpan(3),

                            TextInput::make('printnode.pdf_margin_bottom_mm')
                                ->label('Відступ знизу (мм)')
                                ->numeric()
                                ->step(0.1)
                                ->default(3)
                                ->columnSpan(3),

                            TextInput::make('printnode.pdf_margin_left_mm')
                                ->label('Відступ зліва (мм)')
                                ->numeric()
                                ->step(0.1)
                                ->default(2)
                                ->columnSpan(3),

                            TinyEditor::make('printnode.receipt_template')
                                ->label('Шаблон чека')
                                ->profile('full')
                                ->maxHeight(520)
                                ->fileAttachmentsDisk('public')
                                ->fileAttachmentsDirectory('settings/printnode/templates')
                                ->fileAttachmentsVisibility('public')
                                ->placeholder(static::defaultReceiptTemplate())
                                ->helperText('Доступні змінні: {{kitchen_header}}, {{order_number}}, {{operator}}, {{printed_at}}, {{phone}}, {{delivery_time}}, {{issued_time}}, {{delivery_type}}, {{note}}, {{address}}, {{items}}, {{total}}, {{print_count}}')
                                ->columnSpan(12),

                            Placeholder::make('printnode.client_receipt_header')
                                ->label('')
                                ->content('Чек для клієнта')
                                ->columnSpan(12),

                            FileUpload::make('printnode.client_logo_path')
                                ->label('Логотип для чека клієнта')
                                ->image()
                                ->directory('settings/printnode')
                                ->helperText('Рекомендовано чорно-білий логотип високого контрасту. Для стабільного друку з логотипом використовуйте PDF-формат.')
                                ->columnSpan(6),

                            Select::make('printnode.client_template_key')
                                ->label('Готовий шаблон (клієнт)')
                                ->options([
                                    'client_classic' => 'Клієнтський з логотипом',
                                    'client_compact' => 'Клієнтський компактний',
                                ])
                                ->default('client_classic')
                                ->live()
                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                    $set('printnode.client_receipt_template', static::clientTemplateByKey($state));
                                    $set('printnode.client_template_overwrite_pending', true);
                                })
                                ->helperText('При виборі шаблону текст нижче буде замінено, але запис в БД відбудеться тільки після натискання "Зберегти".')
                                ->columnSpan(6),

                            Hidden::make('printnode.client_template_overwrite_pending')
                                ->dehydrated(false)
                                ->default(false),

                            Placeholder::make('printnode.client_template_overwrite_warning')
                                ->label('')
                                ->content('Увага: шаблон клієнтського чека підставлено у форму. Зміни будуть застосовані тільки після натискання кнопки "Зберегти".')
                                ->visible(fn (Get $get): bool => (bool) ($get('printnode.client_template_overwrite_pending') ?? false))
                                ->columnSpan(12),

                            TinyEditor::make('printnode.client_receipt_template')
                                ->label('Шаблон чека клієнта')
                                ->profile('full')
                                ->maxHeight(520)
                                ->fileAttachmentsDisk('public')
                                ->fileAttachmentsDirectory('settings/printnode/templates')
                                ->fileAttachmentsVisibility('public')
                                ->placeholder(static::defaultClientReceiptTemplate())
                                ->helperText('Доступні змінні: {{client_logo}}, {{order_number}}, {{printed_at}}, {{phone}}, {{delivery_time}}, {{delivery_type}}, {{address}}, {{items}}, {{total}}')
                                ->columnSpan(12),

                            Placeholder::make('printnode.courier_receipt_header')
                                ->label('')
                                ->content('Службовий чек для курʼєра')
                                ->columnSpan(12),

                            Select::make('printnode.courier_template_key')
                                ->label('Готовий шаблон (курʼєр)')
                                ->options([
                                    'courier_service' => 'Службовий (курʼєр)',
                                    'courier_compact' => 'Службовий компактний',
                                ])
                                ->default('courier_service')
                                ->live()
                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                    $set('printnode.courier_receipt_template', static::courierTemplateByKey($state));
                                    $set('printnode.courier_template_overwrite_pending', true);
                                })
                                ->helperText('При виборі шаблону текст нижче буде замінено, але запис в БД відбудеться тільки після натискання "Зберегти".')
                                ->columnSpan(6),

                            Hidden::make('printnode.courier_template_overwrite_pending')
                                ->dehydrated(false)
                                ->default(false),

                            Placeholder::make('printnode.courier_template_overwrite_warning')
                                ->label('')
                                ->content('Увага: шаблон службового чека підставлено у форму. Зміни будуть застосовані тільки після натискання кнопки "Зберегти".')
                                ->visible(fn (Get $get): bool => (bool) ($get('printnode.courier_template_overwrite_pending') ?? false))
                                ->columnSpan(12),

                            TinyEditor::make('printnode.courier_receipt_template')
                                ->label('Шаблон службового чека (курʼєр)')
                                ->profile('full')
                                ->maxHeight(520)
                                ->fileAttachmentsDisk('public')
                                ->fileAttachmentsDirectory('settings/printnode/templates')
                                ->fileAttachmentsVisibility('public')
                                ->placeholder(static::defaultCourierReceiptTemplate())
                                ->helperText('Доступні змінні: {{order_number}}, {{printed_at}}, {{operator}}, {{client_name}}, {{phone}}, {{delivery_time}}, {{delivery_type}}, {{address}}, {{note}}, {{items}}, {{total}}')
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

    public function mount(): void
    {
        $settings = Setting::first() ?? Setting::create([]);

        // гарантируем массив для JSON
        $this->admin_settings = $settings->admin_settings ?? [];

        // можно просто отдать все данные + подставить admin_settings
        $data = $settings->toArray();
        $data['admin_settings'] = $this->admin_settings;

        $this->form->fill($data);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // на всякий случай
        $data['admin_settings'] = $data['admin_settings'] ?? $this->admin_settings ?? [];

        $settings = Setting::first() ?? Setting::create([]);
        $settings->update($data);

        $fresh = $settings->fresh();
        if ($fresh) {
            $formData = $fresh->toArray();
            $formData['admin_settings'] = $fresh->admin_settings ?? [];
            data_set($formData, 'admin_settings.printnode.template_overwrite_pending', false);
            data_set($formData, 'admin_settings.printnode.client_template_overwrite_pending', false);
            data_set($formData, 'admin_settings.printnode.courier_template_overwrite_pending', false);
            $this->form->fill($formData);
        }

        Notification::make()
            ->title('Успех')
            ->success()
            ->body('Настройки сохранены')
            ->send();
    }

    public function sendPrintNodeTest(): void
    {
        try {
            app(KitchenDuplicatePrintService::class)->sendTestReceipt('kitchen', 1);

            Notification::make()
                ->title('Тестовий друк відправлено')
                ->success()
                ->body('Перевірте принтер та чергу PrintNode')
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Помилка тестового друку')
                ->danger()
                ->body($exception->getMessage())
                ->send();
        }
    }
}
