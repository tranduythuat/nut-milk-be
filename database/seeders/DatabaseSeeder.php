<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            ProductSeeder::class,
            ComboSeeder::class,
            DeliverySlotSeeder::class,
        ]);
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => UserRole::CUSTOMER,
        ]);

        User::factory()->create([
            'name' => 'Staff Vận Hành',
            'email' => 'staff@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::STAFF,
        ]);
    }
}
