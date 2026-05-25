<?php

use Illuminate\Foundation\Application;

use App\Providers\Filament\AdminPanelProvider;
use Filament\Support\SupportServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(replace: [
            StartSession::class => \App\Http\Middleware\StartSessionForCacheableGuests::class,
            ShareErrorsFromSession::class => \App\Http\Middleware\ShareErrorsFromSessionForCacheableGuests::class,
        ]);

        $middleware->appendToGroup('web', [
            \App\Http\Middleware\ValidatePaginationPage::class,
            \App\Http\Middleware\SetLocaleFromSession::class,
            \App\Http\Middleware\CacheableGuestResponseHeaders::class,
        ]);
    })
    ->withExceptions(function ($exceptions) {
        // Обработка 419 ошибки (CSRF token expired) - автоматическая перезагрузка
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            // Logout should never show the "Page Expired" screen in production.
            // If CSRF token is stale (cached page / long-lived tab), redirect to login.
            if ($request->is('auth/logout', 'ru/auth/logout', 'en/auth/logout')) {
                if (\Illuminate\Support\Facades\Auth::guard('web')->check()) {
                    \Illuminate\Support\Facades\Auth::guard('web')->logout();
                }

                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }

                $locale = $request->segment(1);
                $target = in_array($locale, ['ru', 'en'], true)
                    ? '/' . $locale . '/auth'
                    : '/auth';

                return redirect($target, 303);
            }

            // Для AJAX запросов возвращаем JSON с флагом для перезагрузки
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'error' => 'session_expired',
                    'message' => 'Сессия истекла. Страница будет перезагружена.',
                    'reload' => true
                ], 419);
            }
            
            // Для обычных запросов возвращаем редирект на текущую страницу
            // Это обновит CSRF токен и перезагрузит страницу
            return redirect($request->fullUrl())->with('_token', csrf_token());
        });
    })
    ->withProviders([
        AdminPanelProvider::class,
        SupportServiceProvider::class,
        FilamentServiceProvider::class,
        FormsServiceProvider::class,
        TablesServiceProvider::class,
        // меню для сайта
        App\Providers\ViewServiceProvider::class,
    ])
    ->create();
