<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class CheckoutDeliveryModeTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        SiteText::updateOrCreate(
            ['slug' => 'cart.delivery.mode.asap_unavailable'],
            [
                'group' => 'cart',
                'value' => [
                    'uk' => 'Недоступно у неробочий час',
                    'ru' => 'Недоступно в нерабочее время',
                    'en' => 'Unavailable outside working hours',
                ],
                'description' => 'Підказка біля недоступного режиму доставки "Якнайшвидше"',
            ],
        );

        $this->command?->info('Checkout delivery mode translations added/updated.');
    }
}
