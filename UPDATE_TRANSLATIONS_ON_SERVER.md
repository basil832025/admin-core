# Обновление переводов на удаленном сервере

После синхронизации с GitHub (`git pull`) для обновления переводов:

## Быстрая инструкция

1. **Подключитесь к серверу по SSH:**
   ```bash
   ssh user@your-server.com
   cd /path/to/your/project
   ```

2. **Убедитесь, что код синхронизирован:**
   ```bash
   git pull origin main
   # или git pull origin master
   ```

3. **Запустите команды для добавления переводов:**

   **Для адресов доставки:**
   ```bash
   php artisan translations:seed-addresses
   ```

   **Для страницы бонусов:**
   ```bash
   php artisan translations:seed-bonuses
   ```

   **Для истории заказов:**
   ```bash
   php artisan translations:seed-orders
   ```

   **Или выполните все команды сразу:**
   ```bash
   php artisan translations:seed-addresses && php artisan translations:seed-bonuses && php artisan translations:seed-orders
   ```

## Результат

Команды автоматически добавят/обновят все переводы. Вы увидите:
```
Добавление переводов для адресов доставки...
✓ Добавлен/обновлен: profile.addresses.title
...
✅ Все переводы успешно добавлены!

Добавление переводов для страницы бонусов...
✓ Добавлен/обновлен: profile.bonuses.title
...
✅ Все переводы успешно добавлены!

Добавление переводов для страницы истории заказов...
✓ Добавлен/обновлен: profile.orders.title
...
✅ Все переводы успешно добавлены!
```

## Примечание

- Команды безопасны для повторного запуска (используют `updateOrCreate`)
- Если команда не распознается, выполните: `php artisan clear-compiled` и `php artisan config:clear`
- Все переводы добавляются на 3 языка: украинский, русский, английский

