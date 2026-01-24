# Проверка отправки почты на удаленном сервере

## Возможные причины, почему письма не отправляются после деплоя

### 1. Проверьте настройки в `.env` на сервере

Убедитесь, что на удаленном сервере в файле `.env` правильно настроены параметры почты:

```env
MAIL_MAILER=smtp
MAIL_HOST=mail.adm.tools
MAIL_PORT=465
MAIL_USERNAME=test@3piroga.ua
MAIL_PASSWORD=yV0gOdlN3d
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=test@3piroga.ua
MAIL_FROM_NAME="Три пироги"
```

**Важно:** Файл `.env` НЕ должен быть в Git! Проверьте, что на сервере он существует и содержит правильные значения.

### 2. Очистите кеш конфигурации на сервере

После изменения `.env` нужно очистить кеш:

```bash
php artisan config:clear
php artisan cache:clear
php artisan config:cache  # Пересоздать кеш конфигурации
```

### 3. Проверьте логи ошибок

Проверьте логи Laravel на наличие ошибок отправки почты:

```bash
tail -f storage/logs/laravel.log
```

Или проверьте логи PHP:

```bash
tail -f /var/log/php-fpm/error.log
# или
tail -f /var/log/apache2/error.log
```

### 4. Проверьте доступность SMTP сервера с сервера

Проверьте, может ли сервер подключиться к SMTP:

```bash
telnet mail.adm.tools 465
# или
nc -zv mail.adm.tools 465
```

Если порт заблокирован, попробуйте альтернативный порт:

```env
MAIL_PORT=587
MAIL_ENCRYPTION=tls
```

### 5. Проверьте firewall на сервере

Убедитесь, что исходящие соединения на порты 465, 587, 25 не заблокированы:

```bash
# Проверка исходящих соединений
curl -v telnet://mail.adm.tools:465
```

### 6. Проверьте права доступа

Убедитесь, что PHP может писать в директорию логов:

```bash
chmod -R 775 storage/logs
chown -R www-data:www-data storage/logs
```

### 7. Тестовая отправка через tinker

Подключитесь к серверу и выполните тестовую отправку:

```bash
php artisan tinker
```

Затем:

```php
use Illuminate\Support\Facades\Mail;

Mail::raw('Test email', function ($message) {
    $message->to('neo.basil@gmail.com')
            ->subject('Test from server');
});
```

### 8. Проверьте, используется ли драйвер `log` вместо `smtp`

По умолчанию Laravel использует драйвер `log`. Убедитесь, что в `.env` указан:

```env
MAIL_MAILER=smtp
```

И проверьте текущую конфигурацию:

```bash
php artisan tinker
```

```php
config('mail.default')
// Должно вернуть 'smtp', а не 'log'
```

### 9. Проверьте SSL/TLS сертификаты

Если возникают проблемы с SSL, попробуйте временно отключить проверку (только для теста):

В `config/mail.php` добавьте в конфигурацию SMTP:

```php
'smtp' => [
    'transport' => 'smtp',
    'host' => env('MAIL_HOST', '127.0.0.1'),
    'port' => env('MAIL_PORT', 2525),
    'encryption' => env('MAIL_ENCRYPTION'),
    'username' => env('MAIL_USERNAME'),
    'password' => env('MAIL_PASSWORD'),
    'timeout' => null,
    'stream' => [
        'ssl' => [
            'allow_self_signed' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ],
],
```

**Внимание:** Это небезопасно для продакшена! Используйте только для диагностики.

### 10. Альтернатива: используйте sendmail

Если SMTP не работает, можно использовать sendmail (если установлен на сервере):

```env
MAIL_MAILER=sendmail
MAIL_SENDMAIL_PATH=/usr/sbin/sendmail -bs -i
MAIL_FROM_ADDRESS=info@3piroga.ua
MAIL_FROM_NAME="Три пироги"
```

Проверьте, установлен ли sendmail:

```bash
which sendmail
# или
which postfix
```

### 11. Проверьте переменные окружения в веб-сервере

Если используете Apache/Nginx, убедитесь, что переменные окружения правильно передаются PHP.

Для Apache проверьте `.htaccess` или конфигурацию виртуального хоста.

Для Nginx проверьте `fastcgi_param` в конфигурации.

### 12. Проверьте очередь (если используется)

Если письма отправляются через очередь, проверьте, запущен ли воркер:

```bash
php artisan queue:work
```

Или проверьте таблицу `jobs` в базе данных.

### 13. Проверьте права на файл .env

Убедитесь, что веб-сервер может читать `.env`:

```bash
chmod 644 .env
chown www-data:www-data .env
```

### 14. Сравните конфигурацию локально и на сервере

Выполните на локальной машине и на сервере:

```bash
php artisan config:show mail
```

Сравните выводы и убедитесь, что настройки идентичны.

### 15. Проверьте версию PHP и расширения

Убедитесь, что на сервере установлены необходимые расширения:

```bash
php -m | grep -i openssl
php -m | grep -i socket
```

### Быстрая диагностика

Создайте тестовый роут для проверки:

```php
// routes/web.php
Route::get('/test-email', function () {
    try {
        Mail::raw('Test email from server', function ($message) {
            $message->to('neo.basil@gmail.com')
                    ->subject('Test Email');
        });
        
        return response()->json([
            'success' => true,
            'message' => 'Email sent successfully',
            'config' => [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});
```

Откройте `/test-email` в браузере и проверьте результат.
