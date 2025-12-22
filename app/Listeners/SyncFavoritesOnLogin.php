<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use App\Models\Shop\Client;
use App\Support\FavoritesSync;

class SyncFavoritesOnLogin
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof Client) {
            FavoritesSync::mergeSessionToClient($event->user);
        }
    }
}
