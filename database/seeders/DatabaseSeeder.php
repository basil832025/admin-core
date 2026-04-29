<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $this->call([
            UsersTableSeeder::class,
            RolePermissionSeeder::class,
            ClientGroupsSeeder::class,
            GeneratePermissionsFromResourcesSeeder::class,
            GenerateShieldPermissionsSeeder::class, // Генерируем пермишены Shield после загрузки всех данных
            ProfileOrdersTranslationsSeeder::class,
            ProfileBonusesTranslationsSeeder::class,
            BlogCommentCaptchaTranslationsSeeder::class,
            ReviewsCaptchaTranslationsSeeder::class,
            MenuFavoritesTranslationsSeeder::class,
            SearchTranslationsSeeder::class,
            LogisticReceiptExtendedTemplateSeeder::class,
            ReceiptTemplatesThreePiesSeeder::class,
            // ... другие сидеры
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
