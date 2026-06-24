# Payparts PrivatBank Flow

## Checkout

1. Customer selects `Payparts` as payment method.
2. Customer selects bank, merchant type and payment count:
   - `PP` - payment by parts.
   - `II` - instant installment.
3. Checkout posts:
   - `payment_method=payparts`
   - `payparts_bank_id`
   - `payparts_plan_key`

## Order Creation

1. Create/update the order.
2. Keep the order out of normal kitchen processing while bank approval is pending.
3. Store:
   - selected bank id;
   - selected merchant type;
   - selected parts count;
   - payparts transaction log row.
4. Do not send order emails before bank success callback.

## PrivatBank Request

Send create payment request to:

```text
{PRIVATBANK_PAYPARTS_BASE_URL}/ipp/v2/payment/create
```

The endpoint path is configured in `config/services.php`:

```php
services.payparts.privatbank.create_path
```

Payload contains:

```text
storeId
orderId
amount
partsCount
merchantType
products
responseUrl
redirectUrl
signature
```

## Redirect

If PrivatBank returns a token, redirect the customer to:

```text
{PRIVATBANK_PAYPARTS_BASE_URL}/ipp/v2/payment?token=...
```

The endpoint path is configured in:

```php
services.payparts.privatbank.payment_path
```

## Callback

PrivatBank sends server callback to:

```text
POST /payparts/response
```

For local tunnel testing set:

```dotenv
PRIVATBANK_PAYPARTS_PUBLIC_URL=https://your-current-tunnel.example
```

Then `responseUrl` and `redirectUrl` sent to PrivatBank will be built from the tunnel domain:

```text
https://your-current-tunnel.example/payparts/response
https://your-current-tunnel.example/payparts/redirect
```

Route name:

```php
payparts.response
```

On success:

1. Verify callback signature.
2. Mark transaction as `payment_success`.
3. Move order to normal new-order flow.
4. Send admin and client emails.

On failure:

1. Mark transaction as `payment_failed`.
2. Keep order out of kitchen processing.
3. Return customer to checkout or show payment failure.

## Customer Return

PrivatBank redirects customer to:

```text
GET /payparts/redirect
```

Route name:

```php
payparts.redirect
```

This page should show success only if callback/status confirms successful payment.
