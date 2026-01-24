# Быстрое решение проблемы с почтой на удаленном сервере

## Самые частые причины

### 1. Файл `.env` не обновлен на сервере

**Проблема:** После деплоя через GitHub файл `.env` НЕ обновляется автоматически (он в `.gitignore`).

**Решение:** Вручную проверьте и обновите `.env` на сервере:

```bash
# На сервере
nano .env
```

Убедитесь, что есть эти строки:

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

### 2. Кеш конфигурации не очищен

**Проблема:** Laravel кеширует конфигурацию, старые настройки остаются в кеше.

**Решение:** Очистите кеш на сервере:

```bash
php artisan config:clear
php artisan cache:clear
php artisan config:cache
```

### 3. Проверьте текущую конфигурацию

Выполните на сервере:

```bash
php artisan tinker
```

Затем:

```php
config('mail.default')  // Должно быть 'smtp', а не 'log'
config('mail.mailers.smtp.host')  // Должно быть 'mail.adm.tools'
config('mail.mailers.smtp.port')  // Должно быть 465
config('mail.mailers.smtp.encryption')  // Должно быть 'ssl'
```

### 4. Тестовая отправка

```bash
php artisan tinker
```

```php
use Illuminate\Support\Facades\Mail;

try {
    Mail::raw('Test email', function ($message) {
        $message->to('neo.basil@gmail.com')
                ->subject('Test from server');
    });
    echo "Email sent successfully\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

### 5. Проверьте логи

```bash
tail -n 50 storage/logs/laravel.log | grep -i mail
```

Ищите ошибки типа:
- `Connection refused`
- `Authentication failed`
- `SSL certificate problem`
- `Timeout`

### 6. Если порт 465 не работает, попробуйте 587

В `.env` измените:

```env
MAIL_PORT=587
MAIL_ENCRYPTION=tls
```

Затем снова:

```bash
php artisan config:clear
php artisan config:cache
```

### 7. Проверьте доступность SMTP с сервера

```bash
telnet mail.adm.tools 465
# или
nc -zv mail.adm.tools 465
```

Если не подключается, возможно firewall блокирует порт.

### 8. Быстрый тест через роут

Добавьте временно в `routes/web.php`:

```php
Route::get('/test-email', function () {
    try {
        \Mail::raw('Test', function ($m) {
            $m->to('neo.basil@gmail.com')->subject('Test');
        });
        return 'OK';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});
```

Откройте `/test-email` в браузере.

---

## Чек-лист для проверки

- [ ] Файл `.env` на сервере содержит правильные настройки MAIL_*
- [ ] Выполнено `php artisan config:clear` и `php artisan config:cache`
- [ ] `config('mail.default')` возвращает `'smtp'`
- [ ] Тестовая отправка через tinker работает
- [ ] Логи не показывают ошибок подключения
- [ ] Порт 465/587 доступен с сервера

---

**Подробная инструкция:** см. `CHECK_EMAIL_ON_SERVER.md`
