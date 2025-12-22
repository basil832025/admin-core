<?php

namespace App\Listeners;

use App\Services\CartService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;

class MergeCartOnLogin
{
    public function __construct(private readonly CartService $cart) {}

public function handle(Login|Registered $event): void
{
    $this->cart->mergeSessionIntoUser($event->user);
}
}
