<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class FooterMessengerTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        SiteText::updateOrCreate(
            ['slug' => 'all.write-to-us-in-messengers'],
            [
                'group' => 'all',
                'value' => [
                    'uk' => 'Напишіть нам у месенджери',
                    'ru' => 'Напишите нам в мессенджеры',
                    'en' => 'Message us on messengers',
                ],
                'description' => 'Заголовок блока мессенджеров в футере',
            ]
        );

        $this->command?->info('Footer messenger translations added/updated.');
    }
}
