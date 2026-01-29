<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class PaymentInvoiceTranslationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $translations = [
            [
                'group' => 'cart',
                'slug' => 'cart.payment.invoice',
                'value' => [
                    'uk' => 'Рахунок-фактура (для юридичних осіб)',
                    'ru' => 'Счет-фактура (для юридических лиц)',
                    'en' => 'Invoice (for legal entities)',
                ],
                'description' => 'Метод оплаты: Рахунок-фактура для юридических лиц',
            ],
        ];

        $this->command->info('Добавление переводов для метода оплаты "Рахунок-фактура"...');

        foreach ($translations as $data) {
            SiteText::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'group' => $data['group'],
                    'value' => $data['value'],
                    'description' => $data['description'],
                ]
            );
            $this->command->line("✓ Добавлен/обновлен: {$data['slug']}");
        }

        $this->command->info('✅ Все переводы успешно добавлены!');
    }
}
