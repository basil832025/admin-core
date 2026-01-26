<?php

use Illuminate\Foundation\Application;

use App\Providers\Filament\AdminPanelProvider;
use Filament\Support\SupportServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Tables\TablesServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function ($middleware) {
        $middleware->appendToGroup('web', [
            \App\Http\Middleware\SetLocaleFromSession::class,
        ]);
    })
    ->withExceptions(function ($exceptions) {
        // Обработка 419 ошибки (CSRF token expired) - автоматическая перезагрузка
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
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
