# Краткая инструкция по настройке отправки писем (как в старом проекте)

## Как работало в старом проекте (3piroga)

В старом проекте использовалась стандартная PHP функция `mail()`, которая отправляет письма напрямую через sendmail/postfix на сервере.

Пример кода из старого проекта:
```php
mail("info@3piroga.ua", 'Новый заказ на 3piroga.ua', $message, "From: <info@3piroga.ua>\r\nContent-Type: text/plain; charset=UTF-8");
```

## Настройка для нового проекта (Laravel)

### 1. В файле `.env` добавьте:

```env
MAIL_MAILER=sendmail
MAIL_SENDMAIL_PATH=/usr/sbin/sendmail -bs -i
MAIL_FROM_ADDRESS=info@3piroga.ua
MAIL_FROM_NAME="Три пироги"

ORDER_NOTIFICATION_EMAIL=info@3piroga.ua
```

### 2. Очистите кеш:

```bash
php artisan config:clear
php artisan cache:clear
```

### 3. Готово!

Теперь письма будут отправляться точно так же, как в старом проекте - напрямую через sendmail/postfix на сервере, без внешнего SMTP.

**Важно:**
- Это работает только на Linux сервере, где установлен sendmail или postfix
- На Windows (локально) используйте SMTP для тестирования
- На продакшн сервере рекомендуется настроить SPF/DKIM записи в DNS

