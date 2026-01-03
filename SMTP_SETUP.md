# Настройка SMTP для отправки писем

## Настройки SMTP сервера

Используйте следующие настройки в файле `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=mail.adm.tools
MAIL_PORT=465
MAIL_USERNAME=test@3piroga.ua
MAIL_PASSWORD=yV0gOdlN3d
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=test@3piroga.ua
MAIL_FROM_NAME="Три пироги"

ORDER_NOTIFICATION_EMAIL=neo.basil@gmail.com
```

**Альтернативные порты (если 465 не работает):**
- Порт 587 с TLS: `MAIL_PORT=587` и `MAIL_ENCRYPTION=tls`
- Порт 25 (без шифрования): `MAIL_PORT=25` и `MAIL_ENCRYPTION=null` (или удалите строку)

## После настройки

1. Очистите кеш конфигурации:
```bash
php artisan config:clear
php artisan cache:clear
```

2. Протестируйте отправку:
```bash
php artisan tinker
```

Затем:
```php
use App\Models\Shop\Order;
use App\Mail\OrderNotificationMail;
use Illuminate\Support\Facades\Mail;

$order = Order::latest()->first();
Mail::to('neo.basil@gmail.com')->send(new OrderNotificationMail($order));
```

