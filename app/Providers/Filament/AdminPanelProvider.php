<?php

namespace App\Providers\Filament;

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

        // Берём из БД выбранную схему
        $scheme = Setting::first()->admin_color_scheme ?? 'primary';
        //dd()
        // Формируем полный массив цветов, где ключи — как выше, а
        // primary (или любой другой) перезаписывает ту роль, что задали
      //  Config::set('filament.dark', false); // если нужно
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' =>  $palettes[$scheme],

            ])
            // Явный порядок групп:
            ->navigationGroups([
                'Инфопанель',
                'Каталог',      // <-- добавили
                'Контент',
                'Настройки',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
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
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugin(
                SpatieLaravelTranslatablePlugin::make()
                    // список ваших локалей — обязателен
                    ->defaultLocales(['uk', 'en', 'ru'])
                    // включаем сам переключатель языков
                   // ->localeSwitcher()
                    // по желанию: показывать в шапке
                  //  ->showInHeader()
            )
            ->plugin(
                FilamentTranslateFieldPlugin::make()
                    ->defaultLocales(['ua', 'en', 'ru'])
            )
            ;
    }
}
