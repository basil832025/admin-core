# Инструкция: что нажать в кабинете Binotel для входящих звонков
По Вашому запиту надсилаємо дані для інтеграції по API REST:
API Key: ccb2a1-98042db API Secret: f18c8f-6ab48c-ec12fd-a232be-91b7f67b
Дата: 2026-02-23

## Цель

Настроить Binotel так, чтобы при входящем звонке он отправлял `POST` на наш сайт, а оператор получал ссылку на создание заказа в админке.

## Перед началом

Подготовьте 2 URL:

- URL webhook-а (куда Binotel будет стучаться):
  - production: `https://your-domain.com/integrations/binotel/call-settings`
  - local test: `https://your-tunnel.ngrok-free.dev/integrations/binotel/call-settings`
- Базовый URL CRM (для открытия формы заказа):
  - production: `https://your-domain.com`
  - local test: `https://your-tunnel.ngrok-free.dev`

## Что нажать в кабинете Binotel

1. Войти в личный кабинет MyBinotel под администратором.
2. Открыть раздел интеграций / API / WebHook (название пункта может отличаться по версии интерфейса).
3. Найти блок `API CALL SETTINGS`.
4. Включить webhook `API CALL SETTINGS` (если выключен).
5. В поле URL вставить наш endpoint:
   - `https://.../integrations/binotel/call-settings`
6. Сохранить настройки.
7. (Рекомендуется) В том же разделе настроить `API CALL COMPLETED`:
   - URL: `https://.../integrations/binotel/call-completed`
   - сохранить.

## Что проверить сразу после сохранения

1. Сделать тестовый входящий звонок на номер Binotel.
2. Убедиться, что наш endpoint `call-settings` получил `POST` с `requestType=apiCallSettings`.
3. Убедиться, что наш сервер вернул JSON с `customerData.linkToCrmUrl`.
4. Проверить, что в уведомлении/плагине Binotel появляется ссылка "Створити замовлення".
5. Нажать ссылку и убедиться, что открывается:
   - `/admin/callcenter/orders/create?bt=...`

## Важно для локального теста

- `http://myadmin.test` не доступен Binotel из интернета.
- Для локалки используйте только публичный туннель (ngrok/Cloudflare Tunnel).
- Если включена строгая проверка IP Binotel, запрос через туннель может не пройти.
  - Для `APP_ENV=local` используйте режим relaxed + секрет webhook-а.
  - Для production оставляйте только strict whitelist IP Binotel.

## Минимальный ожидаемый ответ нашего endpoint

```json
{
  "customerData": {
    "name": "Клиент из MyAdmin",
    "description": "Вхідний дзвінок",
    "linkToCrmUrl": "https://your-domain-or-tunnel/admin/callcenter/orders/create?bt=TOKEN",
    "linkToCrmTitle": "Створити замовлення"
  }
}
```

## Частые ошибки

- Указан локальный URL (`http://localhost`, `http://myadmin.test`) в Binotel.
- Endpoint отвечает не JSON-ом.
- Не возвращается `linkToCrmUrl`.
- Блокируется запрос из-за IP-check при тесте через туннель.
- Для `API CALL COMPLETED` не возвращается `{"status":"success"}`.
