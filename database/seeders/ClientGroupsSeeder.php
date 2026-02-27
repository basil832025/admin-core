<?php

namespace Database\Seeders;

use App\Models\Shop\ClientGroup;
use Illuminate\Database\Seeder;

class ClientGroupsSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'name' => [
                    'uk' => 'Чорний список',
                    'ru' => 'Черный список',
                    'en' => 'Blacklist',
                ],
                'is_active' => true,
                'is_blacklist' => true,
            ],
            [
                'name' => [
                    'uk' => 'VIP клієнти',
                    'ru' => 'VIP клиенты',
                    'en' => 'VIP clients',
                ],
                'is_active' => true,
                'is_blacklist' => false,
            ],
            [
                'name' => [
                    'uk' => 'Співробітники',
                    'ru' => 'Сотрудники',
                    'en' => 'Employees',
                ],
                'is_active' => true,
                'is_blacklist' => false,
            ],
            [
                'name' => [
                    'uk' => 'Дирекція',
                    'ru' => 'Дирекция',
                    'en' => 'Management',
                ],
                'is_active' => true,
                'is_blacklist' => false,
            ],
            [
                'name' => [
                    'uk' => 'Постійні клієнти',
                    'ru' => 'Постоянные клиенты',
                    'en' => 'Regular clients',
                ],
                'is_active' => true,
                'is_blacklist' => false,
            ],
        ];

        foreach ($groups as $group) {
            $existing = ClientGroup::query()
                ->where('name->ru', $group['name']['ru'])
                ->first();

            if ($existing) {
                $existing->update($group);
                continue;
            }

            ClientGroup::create($group);
        }
    }
}
