<?php

namespace App\Enums;

enum PaymentMethodEnum: int
{
    case CARD = 1;
    case CASH = 2;
    case CLUB = 3;
    case ORG_TRANSFER = 4;
    case FREE = 5;
    case POS = 9;
    case INVOICE = 10;
    case LIQPAY = 11;
    case PAYPARTS = 12;

    public function label(?string $locale = null): string
    {
        return __('enums.payment_method.' . $this->name, [], $locale);
    }

    public static function options(?string $locale = null): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label($locale)])
            ->toArray();
    }
}
