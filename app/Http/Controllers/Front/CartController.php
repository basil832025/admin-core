<?php
namespace App\Http\Controllers\Front;

use App\Services\CartService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

// app/Http/Controllers/Front/CartController.php

public function add(Request $r)
{
    $pid    = $r->integer('product_id');
    $qty    = $r->integer('qty', 1);      // может быть отрицательным
    $price  = $r->has('price') ? (float) $r->input('price') : null;
    $isSet  = $r->boolean('set', false);  // ← новый флаг: установить qty

    // НИКАКИХ min:1 / abs() — нам нужна дельта с минусом

    $payload = $isSet
        ? $this->cart->setQty($pid, $qty, $price)   // абсолютное значение
        : $this->cart->changeQty($pid, $qty, $price); // дельта (+1 / -1)

    return response()->json($payload);
}
public function page()
{
    $info = $this->cart->info();

    return view('cart.index', [
        'items' => $info['items'] ?? [],
        'qty'   => (int)($info['qty'] ?? 0),
        'total' => (float)($info['total'] ?? $info['total_price'] ?? 0),
    ]);
}

public function remove(Request $r)
{
    $pid = $r->integer('product_id');
    $all = $r->boolean('all', false);

    $payload = $this->cart->remove($pid, $all);

    return response()->json($payload);
}

// HTML для сайдбара — ОТДЕЛЬНО
public function sidebar()
{
    $info = $this->cart->info();
    return view('partials.cart-sidebar', [
        'items' => $info['items'] ?? [],
        'qty'   => (int)($info['qty'] ?? 0),
        'total' => (float)($info['total'] ?? $info['total_price'] ?? 0),
    ]);
}
public function info()
{
    return response()->json($this->cart->info());
}
}
