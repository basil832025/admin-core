<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use Illuminate\Database\Seeder;

class DeliveryZoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Группы зон с общими параметрами для каждой цветовой группы
        $zones = [
            [
                'name' => 'Green',
                'description' => 'Зеленая зона доставки - ближайшие районы',
                'color' => '#097138',
                'delivery_price' => 119,
                'delivery_time_min' => 30,
                'delivery_time_max' => 60,
                'free_delivery_from' => 749,
                'sort_order' => 1,
            ],
            [
                'name' => 'Blue',
                'description' => 'Синяя зона доставки - средние районы',
                'color' => '#01579B',
                'delivery_price' => 189,
                'delivery_time_min' => 30,
                'delivery_time_max' => 60,
                'free_delivery_from' => 1399,
                'sort_order' => 2,
            ],
            [
                'name' => 'Red',
                'description' => 'Красная зона доставки - дальние районы',
                'color' => '#FF0000',
                'delivery_price' => 249,
                'delivery_time_min' => 60,
                'delivery_time_max' => 90,
                'free_delivery_from' => 1899,
                'sort_order' => 3,
            ],
            [
                'name' => 'Brown',
                'description' => 'Коричневая зона доставки - самые дальние районы',
                'color' => '#A52714',
                'delivery_price' => 349,
                'delivery_time_min' => 60,
                'delivery_time_max' => 120,
                'free_delivery_from' => 3299,
                'sort_order' => 4,
            ],
        ];

        foreach ($zones as $zone) {
            DeliveryZone::updateOrCreate(
                ['name' => $zone['name']],
                array_merge($zone, ['is_active' => true])
            );
        }

        $this->command->info('Создано ' . count($zones) . ' зон доставки по цветам!');
    }
}
