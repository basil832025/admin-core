# CS-Cart API (timeshop.com.ua)

## Access

- Base URL: `https://timeshop.com.ua/api`
- Auth: Basic Auth (`email:api_key`)
- Email: `neo.basil@gmail.com`
- API key: `Wjy48cC3y993omt2aYjss7472K3923K7`

## Quick test (products)

```bash
curl -u "neo.basil@gmail.com:Wjy48cC3y993omt2aYjss7472K3923K7" \
  "https://timeshop.com.ua/api/products?items_per_page=5&page=1&type=simple"
```

## Quick test (categories)

```bash
curl -u "neo.basil@gmail.com:Wjy48cC3y993omt2aYjss7472K3923K7" \
  "https://timeshop.com.ua/api/categories?items_per_page=5&page=1"
```

## Useful endpoints

- Products list: `GET /products`
- Product by id: `GET /products/{id}`
- Categories list: `GET /categories`
- Category by id: `GET /categories/{id}`

## Common query params

- `items_per_page` (example: `250`)
- `page` (example: `1`, `2`, `3`)
- `type=simple` (faster/smaller payload)
- `extend[]=categories` (include categories in product payload)

Example:

```bash
curl -u "neo.basil@gmail.com:Wjy48cC3y993omt2aYjss7472K3923K7" \
  "https://timeshop.com.ua/api/products?items_per_page=250&page=1&type=simple&extend[]=categories"
```

## PHP example

```php
<?php
$email = 'neo.basil@gmail.com';
$api_key = 'Wjy48cC3y993omt2aYjss7472K3923K7';
$url = 'https://timeshop.com.ua/api/products?items_per_page=5&page=1&type=simple';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => $email . ':' . $api_key,
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
if ($response === false) {
    throw new RuntimeException('cURL error: ' . curl_error($ch));
}
curl_close($ch);

$data = json_decode($response, true);
print_r($data);
```

## Notes

- For this store, Basic Auth works.
- Bearer token request previously returned server error.
- If key is exposed publicly, regenerate it in CS-Cart admin.

## Incremental sync (without loading all 110k products)

Use incremental polling by `updated_timestamp`, not full export every time.

### Strategy

1. Request products sorted by last update:
   - `sort_by=updated_timestamp`
   - `sort_order=desc`
   - `items_per_page=250`
2. Store local watermark: `last_synced_updated_timestamp`.
3. Iterate pages from `page=1` upward.
4. Stop when product `updated_timestamp <= last_synced_updated_timestamp`.
5. Save new max `updated_timestamp` after successful sync.

### Request example (latest changes first)

```bash
curl -u "neo.basil@gmail.com:Wjy48cC3y993omt2aYjss7472K3923K7" \
  "https://timeshop.com.ua/api/products?type=simple&items_per_page=250&page=1&sort_by=updated_timestamp&sort_order=desc"
```

### Get last 4 updated products

```bash
curl -u "neo.basil@gmail.com:Wjy48cC3y993omt2aYjss7472K3923K7" \
  "https://timeshop.com.ua/api/products?items_per_page=4&page=1&type=simple&sort_by=updated_timestamp&sort_order=desc"
```

Last check result:
- `124562` | `SEIKO SPB537J1 PRESAGE CLASSIC SERIES CRAFTSMANSHIP` | `updated_timestamp=1773419990` | `status=H`
- `124561` | `Hamilton H77735560 Khaki Aviation X-Wind Day Date Auto` | `updated_timestamp=1773418848` | `status=A`
- `124560` | `Hamilton H70475930 KHAKI FIELD AUTO 38mm CALL OF DUTY` | `updated_timestamp=1773418236` | `status=A`
- `124559` | `Hamilton H69509930 KHAKI FIELD MECHANICAL POWER RESERVE 40 ММ` | `updated_timestamp=1773417546` | `status=H`

### Important

- For this store, Basic Auth works reliably.
- Bearer auth returned `500` earlier (do not use).
- Filtering by `updated_timestamp_from` looked unreliable in tests; use sorting + early stop by watermark.
