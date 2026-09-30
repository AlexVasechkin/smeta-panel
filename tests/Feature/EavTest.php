<?php

namespace Tests\Feature;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EavTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_and_casts_values_by_type(): void
    {
        $rating = Attribute::factory()->create([
            'code' => 'rating',
            'type' => AttributeType::Decimal,
            'unit' => 'балл',
        ]);
        $verified = Attribute::factory()->create([
            'code' => 'is_verified',
            'type' => AttributeType::Boolean,
        ]);
        $startedAt = Attribute::factory()->create([
            'code' => 'started_at',
            'type' => AttributeType::Date,
        ]);

        $user = User::factory()->create();

        $user->setEav('rating', 64.5);
        $user->setEav('is_verified', true);
        $user->setEav('started_at', '2026-09-01');

        // Хранится строкой.
        $this->assertDatabaseHas('attribute_values', [
            'attribute_id' => $rating->id,
            'entity_type' => $user->getMorphClass(),
            'entity_id' => $user->id,
            'value' => '64.5',
        ]);
        $this->assertDatabaseHas('attribute_values', [
            'attribute_id' => $verified->id,
            'value' => '1',
        ]);
        $this->assertDatabaseHas('attribute_values', [
            'attribute_id' => $startedAt->id,
            'value' => '2026-09-01',
        ]);

        // Читается уже типизированным.
        $fresh = $user->fresh();
        $this->assertSame(64.5, $fresh->getEav('rating'));
        $this->assertTrue($fresh->getEav('is_verified'));
        $this->assertInstanceOf(Carbon::class, $fresh->getEav('started_at'));
        $this->assertSame('2026-09-01', $fresh->getEav('started_at')->format('Y-m-d'));
        $this->assertNull($fresh->getEav('unknown_code'));
    }

    public function test_setting_the_same_attribute_updates_in_place(): void
    {
        Attribute::factory()->forEntityType(new Organization)->create([
            'code' => 'color',
            'type' => AttributeType::String,
        ]);
        $organization = Organization::factory()->create();

        $organization->setEav('color', 'белый');
        $organization->setEav('color', 'серый');

        $this->assertSame(1, $organization->attributeValues()->count());
        $this->assertSame('серый', $organization->fresh()->getEav('color'));
    }

    public function test_the_same_code_is_scoped_per_entity_type(): void
    {
        // Один и тот же код объявлен отдельно для каждого типа сущности.
        Attribute::factory()->forEntityType(new User)->create(['code' => 'note', 'type' => AttributeType::String]);
        Attribute::factory()->forEntityType(new Organization)->create(['code' => 'note', 'type' => AttributeType::String]);

        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $user->setEav('note', 'заметка пользователя');
        $organization->setEav('note', 'заметка организации');

        $this->assertSame(['note' => 'заметка пользователя'], $user->fresh()->eav());
        $this->assertSame(['note' => 'заметка организации'], $organization->fresh()->eav());
    }

    public function test_it_rejects_an_attribute_not_declared_for_the_entity_type(): void
    {
        // Свойство объявлено только для User.
        Attribute::factory()->forEntityType(new User)->create(['code' => 'rating', 'type' => AttributeType::Decimal]);
        $organization = Organization::factory()->create();

        $this->expectException(ModelNotFoundException::class);
        $organization->setEav('rating', 50);
    }

    public function test_available_attributes_are_scoped_to_the_entity_type(): void
    {
        Attribute::factory()->forEntityType(new User)->count(2)->create();
        Attribute::factory()->forEntityType(new Organization)->create();

        $this->assertCount(2, User::factory()->create()->availableAttributes());
        $this->assertCount(1, Organization::factory()->create()->availableAttributes());
    }

    public function test_values_are_removed_when_attribute_is_deleted(): void
    {
        $attr = Attribute::factory()->create(['code' => 'temp', 'type' => AttributeType::String]);
        $user = User::factory()->create();
        $user->setEav('temp', 'x');

        $attr->delete();

        $this->assertDatabaseCount('attribute_values', 0);
    }
}
