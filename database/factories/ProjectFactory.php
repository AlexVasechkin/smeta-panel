<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->randomElement(['Квартира', 'Студия', 'Апартаменты', 'Дом']).', '.fake()->numberBetween(1, 200).' м²',
            'address' => fake()->address(),
            'area' => fake()->randomFloat(2, 20, 200),
            'rooms' => fake()->numberBetween(1, 5),
            'status' => fake()->randomElement(ProjectStatus::cases()),
            'start_date' => fake()->optional()->dateTimeBetween('-6 months', '+1 month')?->format('Y-m-d'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
