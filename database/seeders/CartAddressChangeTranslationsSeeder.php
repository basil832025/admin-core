<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class CartAddressChangeTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        SiteText::updateOrCreate(
            ['slug' => 'cart.address.change'],
            [
                'group' => 'cart',
                'value' => [
                    'uk' => 'Змінити',
                    'ru' => 'Изменить',
                    'en' => 'Change',
                ],
                'description' => 'Текст кнопки изменения адреса в checkout',
            ]
        );

        $this->command?->info('cart.address.change translations added/updated.');
    }
}
