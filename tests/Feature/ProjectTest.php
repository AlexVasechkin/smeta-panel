<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\Client;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_show_reports_total_cash_received(): void
    {
        $project = Project::factory()->create();

        $makeCashAct = fn (float $cost) => Document::create([
            'project_id' => $project->id,
            'type' => DocumentType::CashAcceptanceAct->value,
            'title' => 'Акт',
            'disk' => 'local',
            'path' => 'documents/'.$project->id.'/'.Str::uuid().'.docx',
            'payload' => ['cost' => $cost],
        ]);

        $makeCashAct(150000);
        $makeCashAct(90000);

        // Документ другого типа не должен влиять на сумму полученного.
        Document::create([
            'project_id' => $project->id,
            'type' => DocumentType::Estimate->value,
            'title' => 'Смета',
            'disk' => 'local',
            'path' => 'documents/'.$project->id.'/'.Str::uuid().'.xlsx',
            'payload' => ['work_groups' => []],
        ]);

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/show')
                ->where('project.summary.cash_received', fn ($v) => (float) $v === 240000.0));
    }

    public function test_show_reports_contract_totals_and_position_progress(): void
    {
        $project = Project::factory()->create();

        // Смета: 2 позиции работ (10×500=5000, 4×250=1000 → работы 6000),
        // скидка 10% на работы, материалы 2000, доп. 3000.
        // Сумма без скидки = 6000 + 2000 + 3000 = 11000.
        // Со скидкой = 6000×0.9 + 2000 + 3000 = 10400.
        Document::create([
            'project_id' => $project->id,
            'type' => DocumentType::Estimate->value,
            'title' => 'Смета',
            'disk' => 'local',
            'path' => 'documents/'.$project->id.'/'.Str::uuid().'.xlsx',
            'payload' => [
                'work_groups' => [
                    ['title' => 'Стены', 'items' => [
                        ['name' => 'A', 'unit' => 'м²', 'quantity' => 10, 'price' => 500],
                        ['name' => 'B', 'unit' => 'м²', 'quantity' => 4, 'price' => 250],
                    ]],
                ],
                'materials' => [['name' => 'M', 'unit' => 'шт', 'quantity' => 2, 'price' => 1000]],
                'extras' => [['label' => 'Транспорт', 'amount' => 3000]],
                'discount_percent' => 10,
            ],
        ]);

        // Акт: g0-i0 закрыта полностью (10 из 10), g0-i1 частично (1 из 4) → как 0.
        // Итого полностью закрыта 1 позиция из 2.
        Document::create([
            'project_id' => $project->id,
            'type' => DocumentType::WorkCompletionAct->value,
            'title' => 'Акт',
            'disk' => 'local',
            'path' => 'documents/'.$project->id.'/'.Str::uuid().'.docx',
            'payload' => ['positions' => [
                ['key' => 'g0-i0', 'price' => 500, 'quantity' => 10],
                ['key' => 'g0-i1', 'price' => 250, 'quantity' => 1],
            ]],
        ]);

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/show')
                ->where('project.summary.contract_total', fn ($v) => (float) $v === 11000.0)
                ->where('project.summary.contract_total_discounted', fn ($v) => (float) $v === 10400.0)
                ->where('project.summary.positions_total', 2)
                ->where('project.summary.positions_closed', 1));
    }

    public function test_partially_closed_position_counts_as_not_closed(): void
    {
        $project = Project::factory()->create();

        // Одна позиция: 20 ед.
        Document::create([
            'project_id' => $project->id,
            'type' => DocumentType::Estimate->value,
            'title' => 'Смета',
            'disk' => 'local',
            'path' => 'documents/'.$project->id.'/'.Str::uuid().'.xlsx',
            'payload' => ['work_groups' => [
                ['title' => 'Стены', 'items' => [['name' => 'A', 'unit' => 'м²', 'quantity' => 20, 'price' => 100]]],
            ]],
        ]);

        // Два акта закрывают её частями: 5 + 10 = 15 из 20 — не полностью → 0.
        foreach ([5, 10] as $qty) {
            Document::create([
                'project_id' => $project->id,
                'type' => DocumentType::WorkCompletionAct->value,
                'title' => 'Акт',
                'disk' => 'local',
                'path' => 'documents/'.$project->id.'/'.Str::uuid().'.docx',
                'payload' => ['positions' => [['key' => 'g0-i0', 'price' => 100, 'quantity' => $qty]]],
            ]);
        }

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('project.summary.positions_total', 1)
                ->where('project.summary.positions_closed', 0));

        // Добиваем остаток (ещё 5) — позиция становится полностью закрытой.
        Document::create([
            'project_id' => $project->id,
            'type' => DocumentType::WorkCompletionAct->value,
            'title' => 'Акт',
            'disk' => 'local',
            'path' => 'documents/'.$project->id.'/'.Str::uuid().'.docx',
            'payload' => ['positions' => [['key' => 'g0-i0', 'price' => 100, 'quantity' => 5]]],
        ]);

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('project.summary.positions_closed', 1));
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
