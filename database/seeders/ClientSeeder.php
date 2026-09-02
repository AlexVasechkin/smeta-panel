<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Project;
use App\Support\Passport;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Клиенты-физлица с 1-3 объектами каждый + паспортные данные (EAV).
        Client::factory(8)
            ->individual()
            ->has(Project::factory()->count(fake()->numberBetween(1, 3)))
            ->create()
            ->each(function (Client $client) {
                $client->setEav('passport_type', fake()->randomElement(Passport::TYPES));
                $client->setEav('passport_series', fake()->numerify('####'));
                $client->setEav('passport_number', fake()->numerify('######'));
                $client->setEav('passport_issued_by', 'ОУФМС России по '.fake()->city());
                $client->setEav('passport_issued_at', fake()->dateTimeBetween('-15 years', '-1 year')->format('Y-m-d'));
            });

        // Клиенты-юрлица с 2-4 объектами.
        Client::factory(4)
            ->company()
            ->has(Project::factory()->count(fake()->numberBetween(2, 4)))
            ->create();
    }
}
