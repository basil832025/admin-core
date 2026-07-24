<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class CheckoutPromoFulfillmentTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        SiteText::updateOrCreate(
            ['slug' => 'checkout.promo.wrong_fulfillment_method'],
            [
                'group' => 'checkout',
                'value' => [
                    'uk' => 'Промокод не діє для вибраного способу отримання',
                    'ru' => 'Промокод не действует для выбранного способа получения',
                    'en' => 'This promo code is not valid for the selected fulfillment method',
                ],
                'description' => 'Checkout promo error when promo code is limited to delivery or pickup',
            ],
        );
    }
}
