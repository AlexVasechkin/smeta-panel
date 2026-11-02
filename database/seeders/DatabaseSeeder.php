<?php

namespace Database\Seeders;

use App\Models\Organization;
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

        $user = User::query()->firstWhere('email', 'test@example.com')
            ?? User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        // У каждого пользователя есть своя организация — так же, как при
        // регистрации (см. RegisteredUserController). Без неё разделы,
        // привязанные к организации, отдают 404.
        if ($user->currentOrganization() === null) {
            Organization::factory()
                ->empty()
                ->create()
                ->members()
                ->create(['user_id' => $user->id, 'is_owner' => true]);
        }

        $this->call([
            UserAttributeSeeder::class,
            OrganizationAttributeSeeder::class,
            CitySeeder::class,
        ]);
    }
}
