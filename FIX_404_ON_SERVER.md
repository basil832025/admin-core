# Исправление 404 ошибок на сервере

Если после синхронизации через GitHub новые страницы показывают 404 ошибку, необходимо очистить кэш Laravel на сервере.

## Решение проблемы

### 1. Подключитесь к серверу по SSH:
```bash
ssh user@your-server.com
cd /path/to/your/project
```

### 2. Очистите кэш Laravel:

**Полная очистка кэша (рекомендуется):**
```bash
php artisan optimize:clear
```

Эта команда очистит:
- Кэш конфигурации
- Кэш роутов
- Кэш представлений (views)
- Кэш событий
- Кэш приложения

**Или очистите кэш роутов отдельно:**
```bash
php artisan route:clear
php artisan route:cache
```

**Или более подробная очистка:**
```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

### 3. Проверьте, что файлы действительно скопированы:

Убедитесь, что файлы роутов и контроллеров существуют:
```bash
# Проверка файла роутов
ls -la routes/web.php

# Проверка контроллера адресов
ls -la app/Http/Controllers/Front/ClientAddressController.php

# Проверка view файлов
ls -la resources/views/pages/profile/bonuses/index.blade.php
ls -la resources/views/pages/profile/orders/index.blade.php
ls -la resources/views/pages/profile/addresses/index.blade.php
```

### 4. Проверьте права доступа:

Убедитесь, что файлы доступны для чтения:
```bash
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 5. Проверьте список роутов:

Убедитесь, что роуты зарегистрированы:
```bash
php artisan route:list | grep profile
```

Должны быть видны:
- `profile.bonuses.index` → `/profile/bonus`
- `profile.addresses.index` → `/profile/addresses`
- `profile.orders.index` → `/profile/orders`

### 6. Если проблема сохраняется:

**Проверьте логи Laravel:**
```bash
tail -f storage/logs/laravel.log
```

**Проверьте, что middleware auth работает:**
```bash
php artisan route:list --path=profile
```

**Проверьте .env файл:**
Убедитесь, что `APP_ENV=production` (или `local`) и `APP_DEBUG=false` для продакшена.

### 7. Если используется OPcache:

Очистите OPcache PHP:
```bash
# Через консоль (если есть доступ)
php -r "opcache_reset();"

# Или перезапустите PHP-FPM
sudo service php8.1-fpm restart
# или
sudo systemctl restart php-fpm
```

## Быстрое решение (все команды сразу):

```bash
php artisan optimize:clear && php artisan route:cache && php artisan config:cache
```

## После исправления:

1. Проверьте работу страниц:
   - `/profile/bonus`
   - `/profile/addresses`
   - `/profile/orders`

2. Если все работает, можно заново закэшировать для производительности:
   ```bash
   php artisan route:cache
   php artisan config:cache
   ```

