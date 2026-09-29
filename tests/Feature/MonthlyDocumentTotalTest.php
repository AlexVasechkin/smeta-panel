<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\MonthlyDocumentTotal;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MonthlyDocumentTotalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    /**
     * @param  array<int, array{price: float|int, quantity: float|int}>  $positions
     */
    private function makeWorkAct(Project $project, array $positions): Document
    {
        return $this->makeDocument($project, DocumentType::WorkCompletionAct, ['positions' => $positions], 'docx');
    }

    private function makeCashAct(Project $project, float $cost): Document
    {
        return $this->makeDocument($project, DocumentType::CashAcceptanceAct, ['cost' => $cost], 'docx');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function makeDocument(Project $project, DocumentType $type, array $payload, string $ext): Document
    {
        return Document::create([
            'project_id' => $project->id,
            'type' => $type->value,
            'title' => $type->label(),
            'disk' => 'local',
            'path' => 'documents/'.$project->id.'/'.Str::uuid().'.'.$ext,
            'payload' => $payload,
        ]);
    }

    private function currentTotal(DocumentType $type): float
    {
        return (float) (MonthlyDocumentTotal::query()
            ->where('type', $type->value)
            ->whereDate('period', Carbon::now()->startOfMonth()->toDateString())
            ->value('total') ?? 0.0);
    }

    public function test_work_act_accumulates_and_decreases_monthly_total(): void
    {
        $project = Project::factory()->create();

        // 20×80 + 8×300 = 4000
        $first = $this->makeWorkAct($project, [
            ['key' => 'g0-i0', 'price' => 80, 'quantity' => 20],
        ]);
        $this->makeWorkAct($project, [
            ['key' => 'g0-i1', 'price' => 300, 'quantity' => 8],
        ]);

        $this->assertEqualsWithDelta(4000.0, $this->currentTotal(DocumentType::WorkCompletionAct), 0.01);

        $first->delete();
        $this->assertEqualsWithDelta(2400.0, $this->currentTotal(DocumentType::WorkCompletionAct), 0.01);
    }

    public function test_cash_act_accumulates_and_decreases_monthly_total(): void
    {
        $project = Project::factory()->create();

        $first = $this->makeCashAct($project, 150000);
        $this->makeCashAct($project, 90000);

        $this->assertEqualsWithDelta(240000.0, $this->currentTotal(DocumentType::CashAcceptanceAct), 0.01);

        $first->delete();
        $this->assertEqualsWithDelta(90000.0, $this->currentTotal(DocumentType::CashAcceptanceAct), 0.01);
    }

    public function test_totals_are_tracked_per_type_independently(): void
    {
        $project = Project::factory()->create();

        $this->makeWorkAct($project, [['key' => 'g0-i0', 'price' => 100, 'quantity' => 10]]); // 1000
        $this->makeCashAct($project, 5000);

        $this->assertEqualsWithDelta(1000.0, $this->currentTotal(DocumentType::WorkCompletionAct), 0.01);
        $this->assertEqualsWithDelta(5000.0, $this->currentTotal(DocumentType::CashAcceptanceAct), 0.01);
    }

    public function test_non_tracked_documents_do_not_affect_totals(): void
    {
        $project = Project::factory()->create();

        $this->makeDocument($project, DocumentType::Estimate, ['work_groups' => []], 'xlsx');

        $this->assertSame(0, MonthlyDocumentTotal::query()->count());
    }

    public function test_command_recalculates_every_month_for_all_tracked_types(): void
    {
        $project = Project::factory()->create();

        $this->makeWorkAct($project, [['key' => 'g0-i0', 'price' => 80, 'quantity' => 20]]); // 1600
        $this->makeCashAct($project, 5000);

        // Сносим агрегат, чтобы проверить восстановление командой «с нуля».
        MonthlyDocumentTotal::query()->delete();

        $this->artisan('document-totals:recalculate')->assertSuccessful();

        // 12 месяцев × 2 типа = 24 строки.
        $this->assertSame(24, MonthlyDocumentTotal::query()->count());
        $this->assertEqualsWithDelta(1600.0, $this->currentTotal(DocumentType::WorkCompletionAct), 0.01);
        $this->assertEqualsWithDelta(5000.0, $this->currentTotal(DocumentType::CashAcceptanceAct), 0.01);
    }

    public function test_command_rejects_unknown_type(): void
    {
        $this->artisan('document-totals:recalculate', ['--type' => 'estimate'])->assertFailed();
    }

    public function test_dashboard_exposes_both_current_month_totals(): void
    {
        $project = Project::factory()->create();
        $this->makeWorkAct($project, [['key' => 'g0-i0', 'price' => 80, 'quantity' => 20]]); // 1600
        $this->makeCashAct($project, 5000);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('monthlyTotals.month_label')
                ->has('monthlyTotals.items', 2)
                ->where('monthlyTotals.items', fn ($items) => (float) $items->firstWhere('key', DocumentType::WorkCompletionAct->value)['amount'] === 1600.0
                    && (float) $items->firstWhere('key', DocumentType::CashAcceptanceAct->value)['amount'] === 5000.0));
    }
}
