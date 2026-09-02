<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributeValue>
 */
class AttributeValueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attribute_id' => Attribute::factory(),
            'entity_type' => (new Project)->getMorphClass(),
            'entity_id' => Project::factory(),
            'value' => fake()->word(),
        ];
    }

    /**
     * Привязать значение к конкретной сущности.
     */
    public function forEntity(mixed $entity): static
    {
        return $this->state(fn () => [
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => $entity->getKey(),
        ]);
    }
}
