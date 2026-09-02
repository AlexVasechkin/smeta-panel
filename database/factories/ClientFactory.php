<?php

namespace Database\Factories;

use App\Enums\ClientType;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(ClientType::cases());

        return [
            'type' => $type,
            'name' => $type === ClientType::Company
                ? 'ООО «'.fake()->lastName().'»'
                : fake()->name(),
            'phone' => fake()->numerify('+7 9## ###-##-##'),
            'email' => fake()->optional()->safeEmail(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function individual(): static
    {
        return $this->state(fn () => [
            'type' => ClientType::Individual,
            'name' => fake()->name(),
        ]);
    }

    public function company(): static
    {
        return $this->state(fn () => [
            'type' => ClientType::Company,
            'name' => 'ООО «'.fake()->lastName().'»',
        ]);
    }
}
