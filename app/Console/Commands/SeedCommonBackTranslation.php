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

        SiteText::updateOrCreate(
            ['slug' => 'common.back'],
            [
                'group' => 'common',
                'value' => [
                    'uk' => 'Назад',
                    'ru' => 'Назад',
                    'en' => 'Back',
                ],
                'description' => 'Кнопка "Назад" на странице товара',
            ]
        );

        $this->line("✓ Добавлен/обновлен: common.back");
        $this->info('✅ Перевод успешно добавлен!');
        return 0;
    }
}