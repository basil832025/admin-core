<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Pages\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

// components
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;

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

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) return false;

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
        ];
    }

    /** Основная схема формы: табы */
    protected function getFormSchema(): array
    {
        return [
            Tabs::make('settings_tabs')
                ->tabs([
                    static::generalTab(),
                    static::adminTab(),
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

        Notification::make()
            ->title('Успех')
            ->success()
            ->body('Настройки сохранены')
            ->send();
    }
}
