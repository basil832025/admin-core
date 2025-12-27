<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Shop\Order;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function __construct(
        protected CartService $cart
    ) {}

    /**
     * Повторить заказ - добавить все товары из заказа в корзину
     */
    public function repeat($orderId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return back()->with('error', st('profile.orders.repeat_requires_auth', 'Для повторения заказа необходимо авторизоваться'));
        }
        
        // Находим заказ с проверкой принадлежности
        $order = Order::where('id', $orderId)
            ->where('clients_id', $user->id)
            ->first();
        
        if (!$order) {
            abort(403, 'Order not found or access denied');
        }
        
        // Получаем все товары из заказа
        $items = $order->items()->with('product')->get();
        
        if ($items->isEmpty()) {
            return back()->with('error', st('profile.orders.repeat_empty', 'В заказе нет товаров для добавления в корзину'));
        }
        
        $addedCount = 0;
        $skippedCount = 0;
        
        // Добавляем каждый товар в корзину
        foreach ($items as $item) {
            $productId = $item->product_id;
            $qty = (int) $item->qty;
            $price = $item->unit_price ? (float) $item->unit_price : null;
            
            // Проверяем, что товар существует и активен (если нужно)
            if ($productId && $qty > 0) {
                // Проверяем существование товара (он может быть удален)
                $product = $item->product;
                if ($product && ($product->active ?? true)) {
                    $this->cart->add($productId, $qty, $price);
                    $addedCount++;
                } else {
                    $skippedCount++;
                }
            }
        }
        
        if ($addedCount === 0) {
            return back()->with('error', st('profile.orders.repeat_no_available', 'Нет доступных товаров для добавления в корзину'));
        }
        
        $message = st('profile.orders.repeat_success', 'Товары из заказа добавлены в корзину');
        if ($skippedCount > 0) {
            $message .= ' (' . st('profile.orders.repeat_skipped', 'пропущено') . ': ' . $skippedCount . ')';
        }
        
        return redirect()
            ->route('cart.page')
            ->with('success', $message);
    }
}

