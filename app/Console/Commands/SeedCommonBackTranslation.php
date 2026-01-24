<?php

namespace App\Console\Commands;

use App\Models\SiteText;
use Illuminate\Console\Command;

class SeedCommonBackTranslation extends Command
{
    protected $signature = 'translations:seed-common-back';
    protected $description = 'Добавить перевод для кнопки "Назад" (common.back) в bs_site_texts';

    public function handle()
    {
        $this->info('Добавление перевода для кнопки "Назад"...');

        $translations = [
            [
                'slug' => 'common.back',
                'value' => [
                    'uk' => 'Назад',
                    'ru' => 'Назад',
                    'en' => 'Back',
                ],
                'description' => 'Кнопка "Назад" на странице товара',
            ],
            [
                'slug' => 'common.ok',
                'value' => [
                    'uk' => 'ОК',
                    'ru' => 'ОК',
                    'en' => 'OK',
                ],
                'description' => 'Кнопка "ОК" в модальных окнах',
            ],
        ];

        foreach ($translations as $data) {
            SiteText::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'group' => 'common',
                    'value' => $data['value'],
                    'description' => $data['description'],
                ]
            );
            $this->line("✓ Добавлен/обновлен: {$data['slug']}");
        }

        $this->info('✅ Переводы успешно добавлены!');
        return 0;
    }
}