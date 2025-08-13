<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Shop\ClientAddress;
use App\Observers\ClientAddressObserver;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ClientAddress::observe(ClientAddressObserver::class);
    }
}
