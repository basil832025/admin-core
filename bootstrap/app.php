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
        //
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
