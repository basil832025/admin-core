<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class MonobankPaypartsTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        $translations = [
            [
                'group' => 'cart',
                'slug' => 'cart.payment.payparts_type_mono',
                'value' => [
                    'uk' => base64_decode('0J7Qv9C70LDRgtCwINGH0LDRgdGC0LjQvdCw0LzQuA=='),
                    'ru' => base64_decode('0J7Qv9C70LDRgtCwINGH0LDRgdGC0Y/QvNC4'),
                    'en' => 'Monobank installments',
                ],
                'description' => 'Checkout Payparts: Monobank type label',
            ],
            [
                'group' => 'checkout',
                'slug' => 'checkout.payparts.monobank_push_hint',
                'value' => [
                    'uk' => base64_decode('0J/RltC00YLQstC10YDQtNGM0YLQtSDQv9C+0LrRg9C/0LrRgyDRh9Cw0YHRgtC40L3QsNC80Lgg0YMg0LfQsNGB0YLQvtGB0YPQvdC60YMgbW9ub2JhbmsuINCm0Y4g0YHRgtC+0YDRltC90LrRgyDQvdC1INC30LDQutGA0LjQstCw0LnRgtC1OiDQvNC4INC+0YfRltC60YPRlNC80L4g0L/RltC00YLQstC10YDQtNC20LXQvdC90Y8g0LLRltC0INCx0LDQvdC60YMu'),
                    'ru' => base64_decode('0J/QvtC00YLQstC10YDQtNC40YLQtSDQv9C+0LrRg9C/0LrRgyDRh9Cw0YHRgtGP0LzQuCDQsiDQv9GA0LjQu9C+0LbQtdC90LjQuCBtb25vYmFuay4g0J3QtSDQt9Cw0LrRgNGL0LLQsNC50YLQtSDRjdGC0YMg0YHRgtGA0LDQvdC40YbRgzog0LzRiyDQvtC20LjQtNCw0LXQvCDQv9C+0LTRgtCy0LXRgNC20LTQtdC90LjQtSDQvtGCINCx0LDQvdC60LAu'),
                    'en' => 'Confirm payment by parts in the monobank app. Keep this page open while we wait for bank confirmation.',
                ],
                'description' => 'Payparts payment page Monobank push hint',
            ],
            [
                'group' => 'checkout',
                'slug' => 'checkout.payparts.waiting_monobank',
                'value' => [
                    'uk' => base64_decode('0J7Rh9GW0LrRg9GU0LzQviDQv9GW0LTRgtCy0LXRgNC00LbQtdC90L3RjyDRgyDQt9Cw0YHRgtC+0YHRg9C90LrRgyBtb25vYmFuay4uLg=='),
                    'ru' => base64_decode('0J7QttC40LTQsNC10Lwg0L/QvtC00YLQstC10YDQttC00LXQvdC40LUg0LIg0L/RgNC40LvQvtC20LXQvdC40LggbW9ub2JhbmsuLi4='),
                    'en' => 'Waiting for confirmation in the monobank app...',
                ],
                'description' => 'Payparts payment page Monobank waiting status',
            ],
        ];

        foreach ($translations as $data) {
            SiteText::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'group' => $data['group'],
                    'value' => $data['value'],
                    'description' => $data['description'],
                ]
            );
        }

        $this->command?->info('Monobank payparts translations added/updated.');
    }
}