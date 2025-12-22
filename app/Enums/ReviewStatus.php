<?php

namespace App\Enums;

enum ReviewStatus: string
{
case Pending   = 'pending';   // На рассмотрении (дефолт с сайта)
case Published = 'published'; // Опубликован
case Rejected  = 'rejected';  // Отвергнут админом

    public static function labels(): array
    {
        return [
            self::Pending->value   => __('review_status.pending'),
            self::Published->value => __('review_status.published'),
            self::Rejected->value  => __('review_status.rejected'),
        ];
    }
}
