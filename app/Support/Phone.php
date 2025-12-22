<?php

namespace App\Support;

class Phone {
    public static function normalize(string $raw): string
    {
        $d = preg_replace('/\D+/', '', $raw);
        if (str_starts_with($d, '0'))  $d = '38'.$d;  // 0XXXXXXXXX -> 380XXXXXXXXX
        if (strlen($d) === 9)          $d = '380'.$d; // 9 цифр -> 380XXXXXXXXX
        return $d;
    }
}
