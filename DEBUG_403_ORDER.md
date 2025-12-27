# Диагностика 403 ошибки на детальной странице заказа

Если после обновления кода и очистки кэша все еще получаете 403 ошибку при переходе на детальную страницу заказа (`/profile/orders/{id}`), выполните следующие шаги:

## 1. Проверьте, что код обновлен

```bash
git pull origin main  # или master
php artisan optimize:clear
```

## 2. Проверьте, что заказ существует и принадлежит пользователю

Подключитесь к базе данных и проверьте:

```sql
-- Проверьте, что заказ существует
SELECT id, clients_id, status FROM bs_shop_orders WHERE id = 34428;

-- Проверьте ID текущего пользователя (замените на ваш email или телефон)
SELECT id, email, phone FROM bs_clients WHERE id = (SELECT clients_id FROM bs_shop_orders WHERE id = 34428);
```

## 3. Добавьте временное логирование для отладки

Временно добавьте в `routes/web.php` перед проверкой:

```php
Route::get('/profile/orders/{order}', function ($orderId) {
    $user = auth()->user();
    
    // ВРЕМЕННО: логирование для отладки
    \Log::info('Order access check', [
        'order_id' => $orderId,
        'user_id' => $user?->id,
        'user_type' => get_class($user),
    ]);
    
    if (!$user) {
        abort(403, 'User not authenticated');
    }
    
    $order = \App\Models\Shop\Order::where('id', $orderId)
        ->where('clients_id', $user->id)
        ->first();
    
    \Log::info('Order found', [
        'order_exists' => $order !== null,
        'order_clients_id' => $order?->clients_id,
        'user_id' => $user->id,
    ]);
    
    if (!$order) {
        abort(403, 'Order not found or access denied');
    }
    
    return view('pages.profile.orders.show', compact('order'));
})->name('profile.orders.show');
```

Затем проверьте логи:
```bash
tail -f storage/logs/laravel.log
```

## 4. Проверьте guard для аутентификации

Убедитесь, что используется правильный guard. В `routes/web.php` роут находится внутри `middleware(['web', 'auth'])`, что означает использование guard по умолчанию (`web`).

Проверьте, что модель `Client` использует guard `web`:

В `config/auth.php` должно быть:
```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'clients',  // или 'users'
    ],
],
```

## 5. Проверьте Soft Deletes

Если модель `Order` использует `SoftDeletes`, убедитесь, что заказ не удален:

```php
// В routes/web.php измените запрос:
$order = \App\Models\Shop\Order::withTrashed()  // если нужно видеть удаленные
    ->where('id', $orderId)
    ->where('clients_id', $user->id)
    ->first();
```

## 6. Проверьте типы данных

Убедитесь, что типы данных совпадают:

```php
// Добавьте проверку типов
$user = auth()->user();
$userId = is_object($user) ? $user->id : $user;

\Log::info('Type check', [
    'user_id_type' => gettype($userId),
    'user_id_value' => $userId,
]);

$order = \App\Models\Shop\Order::where('id', $orderId)
    ->where('clients_id', (int)$userId)  // явное приведение
    ->first();
```

## Быстрое решение

Если ничего не помогает, попробуйте упрощенную версию без Route Model Binding (уже применена в коде):

```php
Route::get('/profile/orders/{order}', function ($orderId) {
    $user = auth()->user();
    
    if (!$user) {
        abort(403);
    }
    
    // Явный запрос с проверкой принадлежности
    $order = \App\Models\Shop\Order::where('id', $orderId)
        ->where('clients_id', $user->id)
        ->first();
    
    if (!$order) {
        abort(403);
    }
    
    return view('pages.profile.orders.show', compact('order'));
})->name('profile.orders.show');
```

## После исправления

Удалите временное логирование и очистите кэш:

```bash
php artisan optimize:clear
```

