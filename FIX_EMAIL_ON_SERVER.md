# Решение проблемы с отправкой писем на удаленном сервере

## Проблема
В логах появляется: `Order notification email not configured`

Это означает, что переменная `ORDER_NOTIFICATION_EMAIL` не найдена в конфигурации.

## Решение

### 1. Проверьте файл `.env` на сервере

Убедитесь, что в файле `.env` на сервере есть следующие переменные:

```env
MAIL_MAILER=smtp
MAIL_HOST=mail.adm.tools
MAIL_PORT=465
MAIL_USERNAME=test@3piroga.ua
MAIL_PASSWORD=yV0gOdlN3d
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=test@3piroga.ua
MAIL_FROM_NAME="Три пироги"

ORDER_NOTIFICATION_EMAIL=neo.basil@gmail.com,another@example.com
```

### 2. Очистите кеш конфигурации

После добавления/изменения переменных в `.env` выполните на сервере:

```bash
php artisan config:clear
php artisan cache:clear
php artisan config:cache
```

### 3. Проверьте конфигурацию

Выполните на сервере:

```bash
php artisan tinker
```

Затем в консоли:

```php
config('notifications.order_notification_email')
```

Должно вернуть массив email-адресов, например: `["neo.basil@gmail.com", "another@example.com"]` (или один email в массиве, если указан только один)

Если возвращает пустой массив или `null`, значит переменная не прочитана. Проверьте:
- Правильность написания `ORDER_NOTIFICATION_EMAIL` в `.env` (без пробелов, без кавычек)
- Что файл `config/notifications.php` существует и был задеплоен через GitHub

### 4. Проверьте, что файл config/notifications.php существует

Убедитесь, что файл `config/notifications.php` был загружен на сервер через GitHub:

```bash
ls -la config/notifications.php
```

Если файла нет, выполните на сервере:

```bash
git pull
```

### 5. Альтернативное решение (если проблема сохраняется)

Если проблема сохраняется, можно временно указать email напрямую в коде (не рекомендуется для продакшена):

**Примечание:** Можно указать несколько email-адресов через запятую:
```env
ORDER_NOTIFICATION_EMAIL=neo.basil@gmail.com,another@example.com,third@example.com
```

Конфигурация автоматически преобразует строку в массив email-адресов.

Но лучше исправить проблему с `.env` файлом.

## Быстрая проверка всех настроек почты

```bash
php artisan tinker
```

```php
echo "Mail driver: " . config('mail.default') . "\n";
echo "Mail host: " . config('mail.mailers.smtp.host') . "\n";
echo "Mail port: " . config('mail.mailers.smtp.port') . "\n";
echo "Mail from: " . config('mail.from.address') . "\n";
$emails = config('notifications.order_notification_email');
echo "Notification emails: " . (is_array($emails) ? implode(', ', $emails) : ($emails ?? 'not configured')) . "\n";
```

Все значения должны быть заполнены (не null).

