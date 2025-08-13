<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
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
            ->authGuard('web') // <- явно
       //     ->plugin(\BezhanSalleh\FilamentShield\FilamentShieldPlugin::make())
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
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                SpatieLaravelTranslatablePlugin::make()
                    // список локалей — обязателен
                    ->defaultLocales(['uk', 'en', 'ru']),
        // включаем переключатель языков
        //->localeSwitcher()
        // показывать в шапке (опционально)
        //->showInHeader(),

    FilamentTranslateFieldPlugin::make()
        ->defaultLocales(['ua', 'en', 'ru']),

    FilamentShieldPlugin::make()
        // опционально:
     //   ->slim()               // компактные политики
       // ->generateTenants(false)
      //  ->resourceCheckboxes() // чекбоксы в UI
]);
          /*  ->plugin(
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
                    ->defaultLocales(['ua', 'en', 'ru']),
                FilamentShieldPlugin::make()
            // опционально:
             ->slim()               // компактные политики
             ->generateTenants(false)
             ->resourceCheckboxes() // если хочешь чекбоксы в UI
            )  ;*/
    }
}
