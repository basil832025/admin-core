<?php

namespace App\Console\Commands;

use App\Models\SiteText;
use Illuminate\Console\Command;

class SeedCheckoutOrderTypeTranslation extends Command
{
    protected $signature = 'translations:seed-checkout-order-type';
    protected $description = 'Добавить перевод «Тип заказа» (cart.order_type) для чекаута в bs_site_texts';

    public function handle(): int
    {
        $this->info('Добавление перевода «Тип заказа»...');

        SiteText::updateOrCreate(
            ['slug' => 'cart.order_type'],
            [
                'group' => 'cart',
                'value' => [
                    'uk' => 'Тип замовлення',
                    'ru' => 'Тип заказа',
                    'en' => 'Order type',
                ],
                'description' => 'Заголовок блока выбора способа получения (доставка/самовывоз) на чекауте, только для мобильной версии (< 1024px)',
            ]
        );

        $this->line('✓ Добавлен/обновлен: cart.order_type');
        $this->info('✅ Перевод успешно добавлен!');

        return 0;
    }
}
