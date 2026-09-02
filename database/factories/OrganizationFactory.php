<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'legal_name' => 'ООО «'.fake()->company().'»',
            'inn' => (string) fake()->numerify('##########'),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
        ];
    }

    /**
     * Пустая организация — такой она создаётся при регистрации.
     */
    public function empty(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => null,
            'legal_name' => null,
            'inn' => null,
            'phone' => null,
            'email' => null,
        ]);
    }
}
