<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => '1245640@gmail.com'],
            [
                'name'     => 'Admin',
                'password' => Hash::make('admin'),
                // любые другие поля
            ]
        );
        /*User::factory()->create([
            'name'     => 'Test User 2',
            'email'    => 'test2@example.com',
            'password' => Hash::make('password2'),
        ]);*/
    }
}
