<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Shop\ClientAddress;
use App\Observers\ClientAddressObserver;
use App\Models\Shop\Order;
use App\Observers\OrderObserver;
use App\Models\Kitchen\KitchenTicket;
use App\Observers\KitchenTicketObserver;
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
        Order::observe(OrderObserver::class);
        KitchenTicket::observe(KitchenTicketObserver::class);
    }
}
