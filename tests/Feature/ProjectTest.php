<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_it_lists_projects(): void
    {
        Project::factory()->count(3)->create();

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/index')
                ->has('projects.data', 3)
                ->has('statuses', 5));
    }

    public function test_it_filters_projects_by_status(): void
    {
        Project::factory()->create(['status' => 'new']);
        Project::factory()->create(['status' => 'done']);

        $this->get(route('projects.index', ['status' => 'done']))
            ->assertInertia(fn (Assert $page) => $page->has('projects.data', 1));
    }

    public function test_it_creates_a_project_for_a_client(): void
    {
        $client = Client::factory()->create();

        $this->post(route('projects.store'), [
            'client_id' => $client->id,
            'name' => 'Квартира на Ленина',
            'address' => 'ул. Ленина, 1',
            'area' => 54.5,
            'rooms' => 2,
            'status' => 'new',
        ])->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'client_id' => $client->id,
            'name' => 'Квартира на Ленина',
        ]);
    }

    public function test_it_requires_an_existing_client(): void
    {
        $this->post(route('projects.store'), [
            'client_id' => 999,
            'name' => 'Объект',
            'address' => 'Адрес',
            'status' => 'new',
        ])->assertSessionHasErrors('client_id');
    }

    public function test_it_validates_required_fields(): void
    {
        $this->post(route('projects.store'), [])
            ->assertSessionHasErrors(['client_id', 'name', 'address', 'status']);
    }

    public function test_it_updates_a_project(): void
    {
        $project = Project::factory()->create(['status' => 'new']);

        $this->put(route('projects.update', $project), [
            'client_id' => $project->client_id,
            'name' => $project->name,
            'address' => $project->address,
            'status' => 'done',
        ])->assertRedirect(route('projects.show', $project));

        $this->assertSame('done', $project->fresh()->status->value);
    }

    public function test_it_auto_generates_an_order_number_on_creation(): void
    {
        $client = Client::factory()->create();

        $this->post(route('projects.store'), [
            'client_id' => $client->id,
            'name' => 'Объект',
            'address' => 'Адрес',
            'status' => 'new',
        ])->assertRedirect();

        $this->assertSame(now()->year.'-0001', Project::firstWhere('name', 'Объект')->order_number);
    }

    public function test_order_numbers_increment_within_the_year(): void
    {
        $first = Project::factory()->create();
        $second = Project::factory()->create();

        $this->assertSame(now()->year.'-0001', $first->order_number);
        $this->assertSame(now()->year.'-0002', $second->order_number);
    }

    public function test_it_updates_the_order_number(): void
    {
        $project = Project::factory()->create();

        $this->put(route('projects.update', $project), [
            'client_id' => $project->client_id,
            'name' => 'Изменено',
            'address' => $project->address,
            'status' => 'done',
            'order_number' => 'Д-2026/17',
        ])->assertRedirect();

        $this->assertSame('Д-2026/17', $project->fresh()->order_number);
    }

    public function test_it_keeps_the_order_number_when_not_submitted(): void
    {
        $project = Project::factory()->create();
        $original = $project->order_number;

        $this->put(route('projects.update', $project), [
            'client_id' => $project->client_id,
            'name' => 'Изменено',
            'address' => $project->address,
            'status' => 'done',
        ])->assertRedirect();

        $this->assertSame($original, $project->fresh()->order_number);
    }

    public function test_it_rejects_a_duplicate_order_number(): void
    {
        $existing = Project::factory()->create();
        $project = Project::factory()->create();

        $this->put(route('projects.update', $project), [
            'client_id' => $project->client_id,
            'name' => $project->name,
            'address' => $project->address,
            'status' => $project->status->value,
            'order_number' => $existing->order_number,
        ])->assertSessionHasErrors('order_number');
    }

    public function test_it_deletes_a_project(): void
    {
        $project = Project::factory()->create();

        $this->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.index'));

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }
}
