<?php

namespace App\Support;

final class Phone
{
    /**
     * Normalize UA phone to +380XXXXXXXXX.
     */
    public static function formatUa(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        // 0XXXXXXXXX -> 380XXXXXXXXX
        if (str_starts_with($digits, '0')) {
            $digits = '38' . $digits;
        }

        // If user pasted without country code (9 digits), assume UA.
        if (strlen($digits) === 9) {
            $digits = '380' . $digits;
        }

        // If still not UA-prefixed, keep last 9 digits + 380.
        if (! str_starts_with($digits, '380') && strlen($digits) >= 10) {
            $digits = '380' . substr($digits, -9);
        }

        if (strlen($digits) !== 12 || ! str_starts_with($digits, '380')) {
            return null;
        }

        return '+' . $digits;
    }
}
