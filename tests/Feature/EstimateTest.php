<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EstimateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_it_lists_estimates(): void
    {
        $project = Project::factory()->create();
        $this->put(route('projects.estimate.update', $project), [
            'works' => [['category' => 'Кухня', 'name' => 'Работа', 'unit' => 'шт', 'quantity' => 2, 'price' => 100]],
            'materials' => [['name' => 'Плитка', 'unit' => 'м²', 'quantity' => 3, 'price' => 500]],
        ]);

        $this->get(route('estimates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('estimates/index')
                ->has('estimates.data', 1)
                ->where('estimates.data.0.project.name', $project->name)
                ->where('estimates.data.0.works_total', fn ($v) => (float) $v === 200.0)
                ->where('estimates.data.0.total', fn ($v) => (float) $v === 1700.0));
    }

    public function test_it_renders_the_estimate_editor(): void
    {
        $project = Project::factory()->create();

        $this->get(route('projects.estimate.edit', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/estimate')
                ->where('project.id', $project->id)
                ->has('works')
                ->has('materials'));
    }

    public function test_it_stores_works_and_materials_with_computed_totals(): void
    {
        $project = Project::factory()->create();

        $this->put(route('projects.estimate.update', $project), [
            'works' => [
                ['category' => 'Кухня', 'name' => 'Штукатурка стен', 'unit' => 'м²', 'quantity' => 20, 'price' => 350],
                ['category' => 'Кухня', 'name' => 'Укладка плитки', 'unit' => 'м²', 'quantity' => 10, 'price' => 800],
                ['category' => 'Ванная', 'name' => 'Гидроизоляция', 'unit' => 'м²', 'quantity' => 5, 'price' => 500],
            ],
            'materials' => [
                ['name' => 'Плитка', 'unit' => 'м²', 'quantity' => 12, 'price' => 1200],
            ],
        ])->assertRedirect(route('projects.estimate.edit', $project));

        $estimate = $project->refresh()->estimate;

        $this->assertSame(3, $estimate->workItems()->count());
        $this->assertSame(1, $estimate->materialItems()->count());

        // Итоговая стоимость строки = количество * стоимость.
        $this->assertDatabaseHas('estimate_work_items', [
            'estimate_id' => $estimate->id,
            'category' => 'Кухня',
            'name' => 'Штукатурка стен',
            'total' => '7000.00',
        ]);
        $this->assertDatabaseHas('estimate_material_items', [
            'name' => 'Плитка',
            'total' => '14400.00',
        ]);

        // Общий итог по работам: 7000 + 8000 + 2500.
        $this->assertSame('17500.00', number_format((float) $estimate->workItems()->sum('total'), 2, '.', ''));
    }

    public function test_it_groups_work_items_by_category_on_load(): void
    {
        $project = Project::factory()->create();

        $this->put(route('projects.estimate.update', $project), [
            'works' => [
                ['category' => 'Кухня', 'name' => 'Работа 1', 'unit' => 'шт', 'quantity' => 1, 'price' => 100],
                ['category' => 'Ванная', 'name' => 'Работа 2', 'unit' => 'шт', 'quantity' => 1, 'price' => 200],
            ],
            'materials' => [],
        ]);

        $this->get(route('projects.estimate.edit', $project))
            ->assertInertia(fn (Assert $page) => $page
                ->has('works', 2)
                ->where('works.0.category', 'Кухня')
                ->where('works.1.category', 'Ванная')
                ->where('totals.works', fn ($value) => (float) $value === 300.0));
    }

    public function test_it_replaces_items_on_save(): void
    {
        $project = Project::factory()->create();

        $this->put(route('projects.estimate.update', $project), [
            'works' => [['category' => 'A', 'name' => 'Старая', 'unit' => 'шт', 'quantity' => 1, 'price' => 10]],
            'materials' => [],
        ]);
        $this->put(route('projects.estimate.update', $project), [
            'works' => [['category' => 'B', 'name' => 'Новая', 'unit' => 'шт', 'quantity' => 2, 'price' => 20]],
            'materials' => [],
        ]);

        $estimate = $project->refresh()->estimate;
        $this->assertSame(1, $estimate->workItems()->count());
        $this->assertDatabaseHas('estimate_work_items', ['name' => 'Новая']);
        $this->assertDatabaseMissing('estimate_work_items', ['name' => 'Старая']);
    }

    public function test_it_requires_a_name_for_each_work_row(): void
    {
        $project = Project::factory()->create();

        $this->put(route('projects.estimate.update', $project), [
            'works' => [['category' => 'Кухня', 'name' => '', 'quantity' => 1, 'price' => 10]],
            'materials' => [],
        ])->assertSessionHasErrors('works.0.name');
    }

    public function test_it_blocks_guests(): void
    {
        auth()->logout();
        $project = Project::factory()->create();

        $this->get(route('projects.estimate.edit', $project))->assertRedirect(route('login'));
    }
}
