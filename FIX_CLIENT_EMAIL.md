# Исправление проблемы с отправкой писем клиентам

## Проблема
Письма менеджерам отправляются нормально, но письма клиентам не отправляются на удаленном сервере.

## Причина
Проблема может быть связана с:
1. Установкой локали 'uk' в шаблоне письма
2. Ошибками в шаблоне при рендеринге
3. Отсутствием email у клиента
4. Ошибками в функции `st()` при установке локали

## Решение

### 1. Проверьте логи на сервере

```bash
tail -f storage/logs/laravel.log | grep -i "client\|email"
```

Ищите ошибки типа:
- `Failed to send order email to client`
- `Error rendering email template`
- `Call to undefined function st()`
- `Email не указан`

### 2. Проверьте, есть ли email у клиента

В базе данных проверьте:

```sql
SELECT id, name, email, phone FROM bs_clients WHERE id = [ID_КЛИЕНТА];
```

Или через tinker:

```bash
php artisan tinker
```

```php
$order = \App\Models\Shop\Order::find([ID_ЗАКАЗА]);
$order->load('clients');
echo "Client email: " . ($order->clients->email ?? 'NULL') . "\n";
```

### 3. Тестовая отправка письма клиенту

```bash
php artisan tinker
```

```php
use App\Models\Shop\Order;
use App\Mail\OrderClientMail;
use Illuminate\Support\Facades\Mail;

$order = Order::latest()->first();
$order->load('clients');

$clientEmail = $order->clients->email ?? 'neo.basil@gmail.com';

echo "Sending to: $clientEmail\n";
echo "Locale: " . app()->getLocale() . "\n";

try {
    app()->setLocale('uk');
    Mail::to($clientEmail)->send(new OrderClientMail($order));
    echo "Email sent successfully\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
```

### 4. Проверьте шаблон письма

Убедитесь, что все функции `st()` работают правильно. Проверьте, что переводы существуют в базе:

```bash
php artisan tinker
```

```php
app()->setLocale('uk');
echo st('order.email.thank_you', 'Дякуємо за ваше замовлення!') . "\n";
echo st('order.email.greeting', 'Шановний клієнте!') . "\n";
```

### 5. Проверьте, что шаблон рендерится без ошибок

Создайте тестовый роут:

```php
// routes/web.php (временно)
Route::get('/test-client-email', function () {
    $order = \App\Models\Shop\Order::latest()->first();
    if (!$order) {
        return 'No orders found';
    }
    
    try {
        app()->setLocale('uk');
        $mailable = new \App\Mail\OrderClientMail($order);
        $html = $mailable->render();
        return $html;
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage() . '<br><pre>' . $e->getTraceAsString() . '</pre>';
    }
});
```

Откройте `/test-client-email` в браузере и проверьте, рендерится ли шаблон.

### 6. Сравните с письмом менеджерам

Письма менеджерам работают, потому что:
- Не устанавливается локаль в шаблоне
- Используется простой шаблон без сложной логики
- Не используется функция `st()` с установкой локали

### 7. Временное решение: упростить шаблон

Если проблема в шаблоне, можно временно упростить его, убрав установку локали из шаблона (оставить только в контроллере).

### 8. Проверьте, что переводы загружены

```bash
php artisan tinker
```

```php
app()->setLocale('uk');
\App\Models\SiteText::where('slug', 'like', 'order.email.%')->get(['slug', 'value']);
```

Убедитесь, что все необходимые переводы существуют.

### 9. Проверьте права доступа

Убедитесь, что PHP может читать шаблоны:

```bash
ls -la resources/views/emails/order-client.blade.php
chmod 644 resources/views/emails/order-client.blade.php
```

### 10. Проверьте кеш шаблонов

Очистите кеш шаблонов:

```bash
php artisan view:clear
php artisan config:clear
php artisan cache:clear
```

---

## Быстрая диагностика

Выполните на сервере:

```bash
php artisan tinker
```

```php
// 1. Проверьте последний заказ
$order = \App\Models\Shop\Order::latest()->first();
$order->load('clients');
echo "Order ID: {$order->id}\n";
echo "Client email: " . ($order->clients->email ?? 'NULL') . "\n";

// 2. Проверьте локаль
echo "Current locale: " . app()->getLocale() . "\n";
app()->setLocale('uk');
echo "After setLocale('uk'): " . app()->getLocale() . "\n";

// 3. Проверьте функцию st()
echo "Test st(): " . st('order.email.thank_you', 'Дякуємо') . "\n";

// 4. Попробуйте отправить
try {
    \Mail::to($order->clients->email ?? 'neo.basil@gmail.com')
         ->send(new \App\Mail\OrderClientMail($order));
    echo "Email sent!\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
```

---

**Важно:** После исправления проверьте логи на наличие ошибок и убедитесь, что письма отправляются.
