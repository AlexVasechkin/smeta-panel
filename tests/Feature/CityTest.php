<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Пользователь-владелец новой организации.
     */
    private function userWithOrganization(): array
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'Орг']);
        $organization->members()->create(['user_id' => $user->id, 'is_owner' => true]);

        return [$user, $organization];
    }

    public function test_it_creates_a_custom_city_for_the_users_organization(): void
    {
        [$user, $organization] = $this->userWithOrganization();

        $this->actingAs($user)
            ->post(route('cities.store'), ['name' => '  Тула  '])
            ->assertRedirect()
            ->assertSessionHas('created_city');

        $this->assertDatabaseHas('cities', [
            'name' => 'Тула',
            'organization_id' => $organization->id,
            'sort_order' => 500,
            'is_active' => true,
        ]);
    }

    public function test_available_cities_include_global_and_own_org_only(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        [, $otherOrg] = $this->userWithOrganization();

        City::create(['name' => 'Москва', 'sort_order' => 10]);
        City::create(['name' => 'Наш город', 'organization_id' => $organization->id]);
        City::create(['name' => 'Чужой город', 'organization_id' => $otherOrg->id]);

        $this->actingAs($user)
            ->get(route('projects.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/create')
                ->has('cities', 2)
                // системный город идёт первым, затем город организации
                ->where('cities.0.label', 'Москва')
                ->where('cities.1.label', 'Наш город'));
    }

    public function test_inactive_cities_are_excluded(): void
    {
        [$user] = $this->userWithOrganization();
        City::create(['name' => 'Скрытый', 'is_active' => false]);

        $this->actingAs($user)
            ->get(route('projects.create'))
            ->assertInertia(fn (Assert $page) => $page->has('cities', 0));
    }

    public function test_it_rejects_a_duplicate_city_name(): void
    {
        [$user] = $this->userWithOrganization();
        City::create(['name' => 'Казань']);

        $this->actingAs($user)
            ->post(route('cities.store'), ['name' => 'казань'])
            ->assertSessionHasErrors('name');
    }

    public function test_a_user_without_organization_cannot_create_a_city(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('cities.store'), ['name' => 'Омск'])
            ->assertForbidden();

        $this->assertDatabaseMissing('cities', ['name' => 'Омск']);
    }

    public function test_it_blocks_guests(): void
    {
        $this->post(route('cities.store'), ['name' => 'Пермь'])
            ->assertRedirect(route('login'));
    }
}
