<?php

namespace Tests\Feature;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EavTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_and_casts_values_by_type(): void
    {
        $area = Attribute::factory()->create([
            'code' => 'floor_area',
            'type' => AttributeType::Decimal,
            'unit' => 'м²',
        ]);
        $ceiling = Attribute::factory()->create([
            'code' => 'has_high_ceilings',
            'type' => AttributeType::Boolean,
        ]);
        $deadline = Attribute::factory()->create([
            'code' => 'deadline',
            'type' => AttributeType::Date,
        ]);

        $project = Project::factory()->create();

        $project->setEav('floor_area', 64.5);
        $project->setEav('has_high_ceilings', true);
        $project->setEav('deadline', '2026-09-01');

        // Хранится строкой.
        $this->assertDatabaseHas('attribute_values', [
            'attribute_id' => $area->id,
            'entity_type' => $project->getMorphClass(),
            'entity_id' => $project->id,
            'value' => '64.5',
        ]);
        $this->assertDatabaseHas('attribute_values', [
            'attribute_id' => $ceiling->id,
            'value' => '1',
        ]);
        $this->assertDatabaseHas('attribute_values', [
            'attribute_id' => $deadline->id,
            'value' => '2026-09-01',
        ]);

        // Читается уже типизированным.
        $fresh = $project->fresh();
        $this->assertSame(64.5, $fresh->getEav('floor_area'));
        $this->assertTrue($fresh->getEav('has_high_ceilings'));
        $this->assertInstanceOf(Carbon::class, $fresh->getEav('deadline'));
        $this->assertSame('2026-09-01', $fresh->getEav('deadline')->format('Y-m-d'));
        $this->assertNull($fresh->getEav('unknown_code'));
    }

    public function test_setting_the_same_attribute_updates_in_place(): void
    {
        Attribute::factory()->forEntityType(new Client)->create([
            'code' => 'color',
            'type' => AttributeType::String,
        ]);
        $client = Client::factory()->create();

        $client->setEav('color', 'белый');
        $client->setEav('color', 'серый');

        $this->assertSame(1, $client->attributeValues()->count());
        $this->assertSame('серый', $client->fresh()->getEav('color'));
    }

    public function test_the_same_code_is_scoped_per_entity_type(): void
    {
        // Один и тот же код объявлен отдельно для каждого типа сущности.
        Attribute::factory()->forEntityType(new Project)->create(['code' => 'note', 'type' => AttributeType::String]);
        Attribute::factory()->forEntityType(new Client)->create(['code' => 'note', 'type' => AttributeType::String]);

        $project = Project::factory()->create();
        $client = Client::factory()->create();

        $project->setEav('note', 'проектная заметка');
        $client->setEav('note', 'клиентская заметка');

        $this->assertSame(['note' => 'проектная заметка'], $project->fresh()->eav());
        $this->assertSame(['note' => 'клиентская заметка'], $client->fresh()->eav());
    }

    public function test_it_rejects_an_attribute_not_declared_for_the_entity_type(): void
    {
        // Свойство объявлено только для Project.
        Attribute::factory()->forEntityType(new Project)->create(['code' => 'floor_area', 'type' => AttributeType::Decimal]);
        $client = Client::factory()->create();

        $this->expectException(ModelNotFoundException::class);
        $client->setEav('floor_area', 50);
    }

    public function test_available_attributes_are_scoped_to_the_entity_type(): void
    {
        Attribute::factory()->forEntityType(new Project)->count(2)->create();
        Attribute::factory()->forEntityType(new Client)->create();

        $this->assertCount(2, Project::factory()->create()->availableAttributes());
        $this->assertCount(1, Client::factory()->create()->availableAttributes());
    }

    public function test_values_are_removed_when_attribute_is_deleted(): void
    {
        $attr = Attribute::factory()->create(['code' => 'temp', 'type' => AttributeType::String]);
        $project = Project::factory()->create();
        $project->setEav('temp', 'x');

        $attr->delete();

        $this->assertDatabaseCount('attribute_values', 0);
    }
}
