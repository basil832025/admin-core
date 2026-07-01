<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Http\Middleware\SetLocaleFromSession;
use App\Models\Language;
use App\Models\Setting;
use Filament\Facades\Filament;
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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Filament\View\PanelsRenderHook;
use App\Support\AdminStartPage;
// ← ВАЖНО
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Определяем все варианты «ключ» => Color::…

        $palettes = [
            'primary' => Color::Indigo,
            'rose'    => Color::Rose,
            'gray'    => Color::Gray,
            'blue'    => Color::Blue,
            'amber'   => Color::Amber,
            'emerald' => Color::Emerald,
            'orange'  => Color::Orange,
            'violet'  => Color::Violet,
            'teal'    => Color::Teal,
            'indigo'  => Color::Indigo,
            'cyan'    => Color::Cyan,
            'lime'    => Color::Lime,
            'sky'     => Color::Sky,
        ];
        // --- ЗАЩИТА ОТ ОТСУТСТВИЯ ТАБЛИЦ ПРИ package:discover ---
        $hasLang     = $this->safeHasTable('bs_languages');
        $hasSettings = $this->safeHasTable('bs_settings');
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
        $scheme = $hasSettings
            ? (Setting::first())->admin_color_scheme ?? 'primary'            : 'primary';
     $primaryColor = is_string($scheme) ?  ($palettes[$scheme]) : $palettes['primary']; // фоллбек, если ключа нет
        
        // Динамически генерируем пункты меню для языков
        $userMenuItems = [];
        
        if ($hasLang) {
            $languages = Language::where('active', true)
                ->orderBy('position')
                ->get(['code', 'name']);
            
            foreach ($languages as $lang) {
                $code = strtolower($lang->code);
                $userMenuItems["locale-{$code}"] = UserMenuItem::make()
                    ->label($lang->name)
                    ->icon('heroicon-m-language')
                    ->url(fn () => route('admin.switch-locale', $code));
            }
        } else {
            // Fallback, если таблицы нет
            $fallback = [
                'uk' => 'Українська',
                'en' => 'English',
                'ru' => 'Русский',
            ];
            
            foreach ($fallback as $code => $name) {
                $userMenuItems["locale-{$code}"] = UserMenuItem::make()
                    ->label($name)
                    ->icon('heroicon-m-language')
                    ->url(fn () => route('admin.switch-locale', $code));
            }
        }
        
        // Добавляем пункт "Очистить кеш" в конец
        $userMenuItems['clear-cache'] = UserMenuItem::make()
            ->label(__('admin.user_menu.clear_cache'))
            ->icon('heroicon-m-arrow-path')
            ->url(fn () => route('admin.clear-cache'))
            ->sort(100);
        
            $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
           // ->authGuard('web') // <- явно
            ->authGuard('admin') // <- явно
            ->brandName('Basil Admin')
            ->login()
            ->homeUrl(fn (): string => AdminStartPage::resolveForCurrentUser())
           // ->viteTheme('resources/css/filament/admin/theme.css') // подключаем свои стили

            ->colors([
                'primary' =>  $primaryColor,
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
                __('admin.nav.groups.dashboard'),
                __('admin.nav.groups.shop'),
                __('admin.nav.groups.content'),
                __('admin.nav.groups.settings'),
            ])
            ->navigationItems([
                NavigationItem::make('callcenter-orders')
                    ->label(fn (): string => __('callcenter.nav.navigation_label'))
                    ->icon('heroicon-o-phone')
                    ->url(fn (): string => \App\Filament\Resources\Callcenter\OrderResource::getUrl('index'))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.callcenter.orders.*'))
                    ->visible(function (): bool {
                        $user = Filament::auth()->user();
                        if (! $user instanceof \App\Models\User) {
                            return false;
                        }

                        return $user->can('access_callcenter_orders')
                            || $user->can('view_any_callcenter::order')
                            || $user->can('view_callcenter::order');
                    }),

                NavigationItem::make('logistics-orders')
                    ->label(fn (): string => __('logistics.nav.navigation_label'))
                    ->icon('heroicon-o-truck')
                    ->url(fn (): string => \App\Filament\Resources\Logistics\OrderResource::getUrl('index'))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.logistics.orders.*'))
                    ->visible(function (): bool {
                        $user = Filament::auth()->user();
                        if (! $user instanceof \App\Models\User) {
                            return false;
                        }

                        return $user->can('access_logistics_orders')
                            || $user->can('view_any_logistics::order')
                            || $user->can('view_logistics::order');
                    }),

                NavigationItem::make('reports')
                    ->label(fn (): string => __('report.nav.navigation_label'))
                    ->icon('heroicon-o-chart-bar-square')
                    ->url(fn (): string => \App\Filament\Resources\ReportResource::getUrl('index'))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.reports.*'))
                    ->visible(function (): bool {
                        $user = Filament::auth()->user();
                        if (! $user) {
                            return false;
                        }

                        return (method_exists($user, 'hasRole') && $user->hasRole(config('shield.super_admin.name', 'super_admin')))
                            || $user->can('view_any_report');
                    }),
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
            ->userMenuItems($userMenuItems)
            ->plugins([
                SpatieLaravelTranslatablePlugin::make()
                    // список локалей — обязателен
                     ->defaultLocales($locales),
                FilamentShieldPlugin::make(),
                FilamentTranslateFieldPlugin::make()
                    ->defaultLocales($locales),
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
        
        // Добавляем обработчик 419 ошибок для автоматической перезагрузки
        $panel->renderHook(
            PanelsRenderHook::HEAD_END,
            fn () => view('filament.hooks.csrf-handler')
        );
        
        // Добавляем скрипт для расчета зоны доставки (использует ту же логику, что и /delivery)
        $panel->renderHook(
            PanelsRenderHook::HEAD_END,
            fn () => view('filament.hooks.delivery-zone-calculator')
        );
        
        // Добавляем скрипт для инициализации автокомплита адресов в Filament админке
        $panel->renderHook(
            PanelsRenderHook::BODY_END,
            fn () => view('filament.hooks.address-autocomplete-init')
        );

        $panel->renderHook(
            PanelsRenderHook::HEAD_END,
            fn () => view('filament.hooks.callcenter-order-inline-table-styles')
        );

        $panel->renderHook(
            PanelsRenderHook::HEAD_END,
            fn () => view('filament.hooks.product-category-product-table-styles')
        );

        $panel->renderHook(
            PanelsRenderHook::BODY_END,
            fn () => view('filament.hooks.logistics-route-map-init')
        );

        if (config('services.binotel.admin_polling_enabled', true)) {
            $panel->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.hooks.binotel-incoming-call-popup')
            );
        }

        if (config('services.payparts.admin_polling_enabled', true)) {
            $panel->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.hooks.payparts-status-poller')
            );
        }

        if (config('services.courier_comment.admin_polling_enabled', true)) {
            $panel->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.hooks.courier-comment-popup')
            );
        }

        $panel->renderHook(
            PanelsRenderHook::BODY_END,
            fn () => view('filament.hooks.keyboard-shortcuts')
        );
        
    return $panel;
    }
    /** Безопасная проверка наличия таблицы (не падает в package:discover). */
    /**
     * Безопасная проверка наличия таблицы.
     *  - НЕ игнорирует $table
     *  - Пытается через Schema, при ошибке — через information_schema
     */
    private function safeHasTable(string $table): bool
    {
        try {
            // иногда в раннюю фазу это может бросить exception — отловим ниже
            if (Schema::connection(config('database.default'))->hasTable($table)) {
                return true;
            }
        } catch (\Throwable $e) {
            // провалимся во 2-й способ
        }

        try {
            $db = DB::getDatabaseName();
            if (! $db) return false;

            $row = DB::selectOne(
                'SELECT COUNT(*) AS c
                 FROM information_schema.tables
                 WHERE table_schema = ? AND table_name = ?',
                [$db, $table]
            );
            return ($row && (int) $row->c > 0);
        } catch (\Throwable $e) {
            return false;
        }
    }

}
