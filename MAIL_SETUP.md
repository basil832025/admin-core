# Настройка отправки почты для уведомлений о заказах

## Проблема
По умолчанию Laravel использует драйвер `log`, который не отправляет письма, а только сохраняет их в лог-файл.

## Решение

### 1. Настройка SMTP (рекомендуется для продакшена)

#### Настройка SMTP для 3piroga.ua (mail.adm.tools)

Добавьте в файл `.env` следующие настройки:

```env
MAIL_MAILER=smtp
MAIL_HOST=mail.adm.tools
MAIL_PORT=465
MAIL_USERNAME=test@3piroga.ua
MAIL_PASSWORD=yV0gOdlN3d
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=test@3piroga.ua
MAIL_FROM_NAME="Три пироги"

# Email для уведомлений о заказах
ORDER_NOTIFICATION_EMAIL=neo.basil@gmail.com
```

**Альтернативные порты (если 465 не работает):**
- Порт 587 с TLS: `MAIL_PORT=587` и `MAIL_ENCRYPTION=tls`
- Порт 25 (без шифрования): `MAIL_PORT=25` и `MAIL_ENCRYPTION=null`

#### Альтернативная настройка SMTP для Gmail

Если нужно использовать Gmail:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

**Важно для Gmail:**
- Нужно использовать "Пароль приложения" (App Password), а не обычный пароль
- Для создания пароля приложения: https://myaccount.google.com/apppasswords

### 2. Альтернативные варианты

#### Mailgun
```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=your-domain.mailgun.org
MAILGUN_SECRET=your-mailgun-secret
MAILGUN_ENDPOINT=api.mailgun.net
```

#### Sendmail (прямая отправка через PHP, без SMTP) ⭐ Рекомендуется для продакшена

**Как было настроено в старом проекте (3piroga):**

В старом проекте использовалась стандартная PHP функция `mail()`, которая работает напрямую через sendmail/postfix на сервере. Это самый простой способ без внешних SMTP серверов.

**Настройка для Linux сервера:**

Если на сервере установлен sendmail или postfix (обычно уже установлены), можно отправлять письма напрямую:

```env
MAIL_MAILER=sendmail
MAIL_SENDMAIL_PATH=/usr/sbin/sendmail -bs -i
MAIL_FROM_ADDRESS=info@3piroga.ua
MAIL_FROM_NAME="Три пироги"

ORDER_NOTIFICATION_EMAIL=info@3piroga.ua
```

**Важно:** Это работает только на Linux сервере, где настроен sendmail/postfix. На Windows (локально) функция `mail()` не работает без дополнительной настройки.

**Установка sendmail на Ubuntu/Debian:**
```bash
sudo apt-get update
sudo apt-get install sendmail
sudo sendmailconfig  # настройка
```

**Установка postfix (альтернатива sendmail):**
```bash
sudo apt-get update
sudo apt-get install postfix
# Во время установки выберите "Internet Site"
```

**Важно для продакшена:**
- Настройте SPF запись в DNS: `v=spf1 a mx ip4:YOUR_SERVER_IP ~all`
- Настройте DKIM записи (для postfix)
- Без этих записей письма могут попадать в спам

**Для Windows (локально):**
На Windows функция `mail()` PHP обычно не работает. Для локальной разработки лучше использовать SMTP (Gmail, Mailtrap).

### 3. Проверка логов (если используется драйвер 'log')

Если используется драйвер `log`, письма сохраняются в:
- `storage/logs/laravel.log`

Проверьте логи на наличие сообщений об отправке писем:
```bash
tail -f storage/logs/laravel.log | grep "Mail"
```

### 4. Тестирование отправки

После настройки можно протестировать отправку письма через tinker:
```bash
php artisan tinker
```

Затем в консоли:
```php
use App\Models\Shop\Order;
use App\Mail\OrderNotificationMail;
use Illuminate\Support\Facades\Mail;

$order = Order::latest()->first();
Mail::to('neo.basil@gmail.com')->send(new OrderNotificationMail($order));
```

### 5. Очистка кеша конфигурации

После изменения `.env` файла выполните:
```bash
php artisan config:clear
php artisan cache:clear
```

## Текущая конфигурация

Проверить текущий драйвер почты:
```bash
php artisan tinker
```
```php
config('mail.default')
```

Проверить email для уведомлений:
```php
config('notifications.order_notification_email')
```

