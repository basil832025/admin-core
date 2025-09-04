<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Http\Middleware\SetLocaleFromSession;
use App\Models\Language;
use App\Models\Setting;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use SolutionForest\FilamentTranslateField\FilamentTranslateFieldPlugin;
use Filament\SpatieLaravelTranslatablePlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Navigation\UserMenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Enums\ThemeMode;
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Определяем все варианты «ключ» => Color::…

        $palettes = [
            'Rose'  => Color::Rose,
            'gray'    => Color::Gray,
            'Blue'    => Color::Blue,
            'Amber' => Color::Amber,
            'Emerald' => Color::Emerald,
            'Orange' => Color::Orange,
    ];
        // --- ЗАЩИТА ОТ ОТСУТСТВИЯ ТАБЛИЦ ПРИ package:discover ---
        $hasLang     = $this->safeHasTable('languages');
        $hasSettings = $this->safeHasTable('settings');
            // Локали (дефолты, если таблиц нет)
        $ordered = $hasLang
            ? Language::where('active', true)->orderBy('position')->pluck('code')->map(fn ($c) => strtolower($c))
            : collect(['uk', 'en', 'ru']);


        // Дефолт из настроек (или config('app.locale'))
      //  $default = strtolower(Setting::value('default_language_code') ?: config('app.locale'));
        $default = strtolower(
            $hasSettings ? (Setting::value('default_language_code') ?: config('app.locale'))
                : config('app.locale')
        );
        // $locales = Language::activeCodesOrdered(); // порядок из БД
        $locales = $ordered
            ->sortBy(fn ($code) => $code === $default ? 0 : 1)
            ->values()
            ->all();
        // Цветовая схема (дефолт, если нет settings)
        $scheme = $hasSettings ? (optional(Setting::first())->admin_color_scheme ?? 'primary') : 'primary';

        // Формируем полный массив цветов, где ключи — как выше, а
        // primary (или любой другой) перезаписывает ту роль, что задали
        //  Config::set('filament.dark', false); // если нужно
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->authGuard('web') // <- явно
            ->brandName('Basil Admin')
            ->login()
            ->colors([
                'primary' =>  $palettes[$scheme],
                // токены, которые будем возвращать из enum:
                'blue'    => Color::Blue,
                'amber'   => Color::Amber,
                'emerald' => Color::Emerald,
                'orange'  => Color::Orange,
                'rose'    => Color::Rose,
                'gray'    => Color::Gray,
                'violet'  => Color::Violet,
                'teal'    => Color::Teal,
                'indigo'  => Color::Indigo,
                'orange'  => Color::Orange,
                'cyan'    => Color::Cyan,
                'lime'    => Color::Lime,
                'amber'   => Color::Amber,   // можно не дублировать, но пусть будет явным
                'sky'     => Color::Sky,
            ])
            // Явный порядок групп:
            ->navigationGroups([
                'Инфопанель',
                'Магазин',      // <-- добавили
                'Контент',
                'Настройки',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                // 👇 ДОЛЖНА идти ПОСЛЕ StartSession
                SetLocaleFromSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->userMenuItems([
                'locale-uk' => UserMenuItem::make()
                    ->label('Українська')->icon('heroicon-m-language')
                    ->url(fn () => route('admin.switch-locale', 'uk')),

                'locale-en' => UserMenuItem::make()
                    ->label('English')->icon('heroicon-m-language')
                    ->url(fn () => route('admin.switch-locale', 'en')),

                'locale-ru' => UserMenuItem::make()
                    ->label('Русский')->icon('heroicon-m-language')
                    ->url(fn () => route('admin.switch-locale', 'ru')),
            ])
            ->plugins([
                SpatieLaravelTranslatablePlugin::make()
                    // список локалей — обязателен
                     ->defaultLocales($locales),

                FilamentTranslateFieldPlugin::make()
                    ->defaultLocales($locales),

                FilamentShieldPlugin::make()

            ]);
        // гамбургер на десктопе
        if ($hasSettings && Setting::admin('sidebar.collapsible_on_desktop', true)) {
            $panel->sidebarFullyCollapsibleOnDesktop();
        }

        // полностью сворачиваемая (иконки)
        if ($hasSettings && Setting::admin('sidebar.fully_collapsible_on_desktop', false)) {
            $panel->sidebarFullyCollapsibleOnDesktop();
        }

        // тема/режим (пример)
        if ($hasSettings && Setting::admin('theme.dark_mode', true)) {

            $panel->defaultThemeMode(ThemeMode::Dark);
        }
        if ($hasSettings && Setting::admin('nav.position') === 'top') {

            $panel->topNavigation();
        }

        // ширина контента (пример)
        if ($hasSettings && ($w = Setting::admin('layout.max_content_width'))) {
            $panel->maxContentWidth($w); // например '7xl' | 'full'
        }
    return $panel;
    }
    /** Безопасная проверка наличия таблицы (не падает в package:discover). */
    private function safeHasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            // во время раннего boot/при отсутствии соединения к БД просто возвращаем false
            return false;
        }
    }
}
