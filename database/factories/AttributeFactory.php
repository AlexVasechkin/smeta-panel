<?php

namespace Database\Factories;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'entity_type' => (new Project)->getMorphClass(),
            'code' => str($name)->slug('_')->value(),
            'name' => ucfirst($name),
            'type' => fake()->randomElement(AttributeType::cases()),
            'unit' => null,
            'options' => null,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function type(AttributeType $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }

    /**
     * Привязать свойство к типу сущности (по классу модели или экземпляру).
     */
    public function forEntityType(Model|string $entity): static
    {
        $type = $entity instanceof Model ? $entity->getMorphClass() : $entity;

        return $this->state(fn () => ['entity_type' => $type]);
    }

    /**
     * @param  array<int, string>  $options
     */
    public function select(array $options): static
    {
        return $this->state(fn () => [
            'type' => AttributeType::Select,
            'options' => $options,
        ]);
    }
}
