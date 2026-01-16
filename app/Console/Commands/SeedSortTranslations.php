<?php

namespace App\Console\Commands;

use App\Models\SiteText;
use Illuminate\Console\Command;

class SeedSortTranslations extends Command
{
    protected $signature = 'site-text:seed-sort';
    protected $description = 'Добавить переводы для сортировки в bs_site_texts';

    public function handle()
    {
        $translations = [
            [
                'group' => 'catalog',
                'slug' => 'catalog.sort.default',
                'value' => [
                    'uk' => 'Сортувати',
                    'ru' => 'Сортировать',
                    'en' => 'Sort'
                ],
                'description' => 'Пункт сортировки по умолчанию (сброс сортировки)',
            ],
            [
                'group' => 'product',
                'slug' => 'product.sku_label',
                'value' => [
                    'uk' => 'Артикул',
                    'ru' => 'Артикул',
                    'en' => 'SKU'
                ],
                'description' => 'Название поля артикула в карточке товара',
            ],
        ];

        $this->info('Добавление переводов для сортировки...');

        foreach ($translations as $data) {
            SiteText::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'group' => $data['group'],
                    'value' => $data['value'],
                    'description' => $data['description'],
                ]
            );
            $this->line("✓ Добавлен/обновлен: {$data['slug']}");
        }

        $this->info('✅ Все переводы успешно добавлены!');
        return 0;
    }
}

