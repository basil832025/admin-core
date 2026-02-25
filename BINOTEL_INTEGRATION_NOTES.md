# Binotel -> Callcenter Orders (зафиксированный план)

Дата: 2026-02-23

## Важное из API Binotel именно для этого проекта

### 1) WebHook `API CALL SETTINGS` (ключевой для открытия формы)

- Метод: `POST`.
- Binotel отправляет webhook при звонке и ожидает JSON-ответ для CRM-плагина.
- Нужные поля входящего запроса:
  - `requestType` = `apiCallSettings`
  - `externalNumber` (номер клиента)
  - `callType` (`0` входящий, `1` исходящий)
  - `pbxNumber` (для входящего)
  - `internalNumber` (для исходящего)
  - `companyID`

Минимальный пример входящего payload (по смыслу):

```json
{
  "requestType": "apiCallSettings",
  "externalNumber": "0671234567",
  "callType": "0",
  "pbxNumber": "0443334023",
  "companyID": "26912"
}
```

Нужный для нас ответ Binotel (чтобы открыть CRM-страницу):

```json
{
  "customerData": {
    "name": "Клиент из MyAdmin",
    "description": "Вхідний дзвінок",
    "linkToCrmUrl": "http://myadmin.test/admin/callcenter/orders/create?bt=TOKEN",
    "linkToCrmTitle": "Створити замовлення"
  }
}
```

Важно для локального теста: если Binotel тестируется через публичный туннель, `linkToCrmUrl` должен быть публичным URL (например ngrok), а не `http://myadmin.test/...`.

Поля ответа, которые реально используем:

- `customerData.linkToCrmUrl` - ссылка, по которой оператор переходит в CRM.
- `customerData.linkToCrmTitle` - текст ссылки.
- `customerData.name` и `description` - полезно для подписи в уведомлении.

Дополнительно Binotel поддерживает (опционально):

- `customerData.assignedToEmployeeEmail|Number|ID` - привязка к ответственному.
- `routeData.type` + `routeData.id` - переопределение сценария звонка.
- `variables` - передача переменных в сценарий.

### 2) WebHook `API CALL COMPLETED` (завершение звонка)

- Метод: `POST`.
- Входящее поле: `requestType=apiCallCompleted`, плюс `callDetails`.
- Критично: для подтверждения получения нужно вернуть строго:

```json
{"status":"success"}
```

- Если не вернуть `{"status":"success"}`, Binotel делает до 7 повторных отправок (до ~38 часов).

### 3) Безопасность по документации Binotel

- Принимать `API CALL SETTINGS` только с Binotel IP.
- Список IP из документации:
  - `194.88.218.116`, `194.88.218.114`, `194.88.218.117`, `194.88.218.118`
  - `194.88.219.67`, `194.88.219.78`, `194.88.219.70`, `194.88.219.71`, `194.88.219.72`
  - `194.88.219.79`, `194.88.219.80`, `194.88.219.81`, `194.88.219.82`, `194.88.219.83`
  - `194.88.219.84`, `194.88.219.85`, `194.88.219.86`, `194.88.219.87`, `194.88.219.88`
  - `194.88.219.89`, `194.88.219.92`, `194.88.218.119`, `194.88.218.120`
  - `185.100.66.145`, `185.100.66.146`, `185.100.66.147`

Примечание для локальной отладки через туннель:

- При работе через ngrok/туннель `REMOTE_ADDR` может быть адресом туннеля, а не прямым IP Binotel.
- Поэтому для `APP_ENV=local` допускается ослабленный режим проверки (например, секретный токен webhook-а из `.env`).
- Для production оставлять только строгий IP whitelist Binotel.

### 4) Про REST API Binotel в контексте этой задачи

- Для сценария "открыть форму нового заказа при входящем звонке" REST API не обязателен.
- Основной поток строится на `API CALL SETTINGS` webhook.
- REST API (ключ/секрет, `https://api.binotel.com/api/4.0/...`) может понадобиться позже для отчетов, истории и записей звонков, но не критичен для первого этапа интеграции.

## Зафиксированный поток интеграции для MyAdmin

### 1) Endpoint для Binotel

- Добавить endpoint: `POST /integrations/binotel/call-settings`.
- Логика endpoint:
  - проверить IP по whitelist;
  - проверить `requestType=apiCallSettings`;
  - нормализовать `externalNumber` до digits;
  - найти клиента в `bs_clients`;
  - создать короткоживущий токен (`bt`) в cache с данными звонка;
  - вернуть `customerData.linkToCrmUrl` на `admin/callcenter/orders/create?bt=...`.

Рекомендация: не хардкодить домены в коде, вынести базовые URL в `.env`.

Пример:

```dotenv
# Binotel local/prod switch
BINOTEL_WEBHOOK_PUBLIC_URL=https://your-tunnel.ngrok-free.dev/integrations/binotel/call-settings
BINOTEL_CRM_BASE_URL=https://your-tunnel.ngrok-free.dev
BINOTEL_WEBHOOK_SECRET=change_me_long_random_string

# strict|relaxed (relaxed только для local)
BINOTEL_IP_CHECK_MODE=strict
```

Как использовать:

- `BINOTEL_CRM_BASE_URL` -> основа для `customerData.linkToCrmUrl`.
- `BINOTEL_WEBHOOK_PUBLIC_URL` -> адрес, который указываете в кабинете Binotel для webhook.
- `BINOTEL_WEBHOOK_SECRET` -> дополнительная проверка в local режиме.
- `BINOTEL_IP_CHECK_MODE`:
  - `strict` для production (только whitelist IP),
  - `relaxed` только для local тестов через туннель.

Пример JSON ответа для локального теста через ngrok:

```json
{
  "customerData": {
    "name": "Клиент из MyAdmin",
    "description": "Вхідний дзвінок",
    "linkToCrmUrl": "https://your-tunnel.ngrok-free.dev/admin/callcenter/orders/create?bt=TOKEN",
    "linkToCrmTitle": "Створити замовлення"
  }
}
```

### 2) Префилл формы заказа

- В `app/Filament/Resources/Callcenter/OrderResource/Pages/CreateOrder.php` в `mount()`:
  - прочитать `bt` токен;
  - если клиент найден - заполнить `clients_id` и зависимые поля;
  - если клиент не найден - показать входящий номер в отдельном поле, чтобы оператор быстро создал клиента.

### 3) Поведение для оператора (UX)

- Зафиксированный основной режим: переход по ссылке из уведомления Binotel (`linkToCrmUrl`).
- Режим авто-popup на странице списка заказов (`/admin/callcenter/orders`) не делать по умолчанию; только отдельной задачей.

## Что уже проверено в коде проекта

- Ресурс callcenter есть: `app/Filament/Resources/Callcenter/OrderResource.php` (slug `callcenter/orders`).
- Форма заказа переиспользуется из `Shop\OrderResource::getInfoTabSchema()`.
- `client_phone_view` сейчас привязан к выбранному `clients_id`, поэтому для кейса "клиент не найден" нужен отдельный incoming-phone механизм.

## Фиксация, чтобы не менять подход после перезапуска сессии

1. Используем Binotel `API CALL SETTINGS` webhook как основной интеграционный вход.
2. Всегда возвращаем `linkToCrmUrl` на create-страницу callcenter-заказа.
3. Префилл делаем через краткоживущий токен (`bt`), не через длинные открытые query-параметры.
4. Обязательно: IP whitelist Binotel и `{"status":"success"}` для `API CALL COMPLETED`.
5. Для local тестов через туннель используем публичный домен из `.env` и (при необходимости) relaxed-проверку с секретом; для production только strict IP whitelist.
6. Auto-popup без клика не внедряем по умолчанию.
