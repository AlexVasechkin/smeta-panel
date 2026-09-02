<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEstimateRequest;
use App\Models\Estimate;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EstimateController extends Controller
{
    /**
     * Список смет по всем объектам с итогами.
     */
    public function index(): Response
    {
        $estimates = Estimate::query()
            ->with(['project:id,name,client_id', 'project.client:id,name'])
            ->withSum('workItems', 'total')
            ->withSum('materialItems', 'total')
            ->latest('updated_at')
            ->paginate(15)
            ->through(fn (Estimate $estimate) => [
                'id' => $estimate->id,
                'project' => [
                    'id' => $estimate->project->id,
                    'name' => $estimate->project->name,
                ],
                'client' => $estimate->project->client?->name,
                'works_total' => (float) $estimate->work_items_sum_total,
                'materials_total' => (float) $estimate->material_items_sum_total,
                'total' => (float) $estimate->work_items_sum_total + (float) $estimate->material_items_sum_total,
                'updated_at' => $estimate->updated_at?->format('Y-m-d'),
            ]);

        return Inertia::render('estimates/index', [
            'estimates' => $estimates,
        ]);
    }

    /**
     * Показать редактируемую смету объекта (таблицы работ и материалов).
     */
    public function edit(Project $project): Response
    {
        $estimate = $project->estimate()->firstOrCreate([]);
        $estimate->load(['workItems', 'materialItems']);

        return Inertia::render('projects/estimate', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
            'works' => $this->groupWorkItems($estimate),
            'materials' => $estimate->materialItems->map(fn ($item) => [
                'name' => $item->name,
                'unit' => $item->unit,
                'quantity' => (float) $item->quantity,
                'price' => (float) $item->price,
                'total' => (float) $item->total,
            ])->all(),
            'totals' => [
                'works' => (float) $estimate->workItems->sum('total'),
                'materials' => (float) $estimate->materialItems->sum('total'),
            ],
        ]);
    }

    /**
     * Сохранить смету целиком: позиции пересоздаются из присланных данных.
     */
    public function update(UpdateEstimateRequest $request, Project $project): RedirectResponse
    {
        $estimate = $project->estimate()->firstOrCreate([]);

        DB::transaction(function () use ($estimate, $request) {
            $estimate->workItems()->delete();
            $estimate->materialItems()->delete();

            $position = 0;
            foreach ($request->validated('works') as $row) {
                $estimate->workItems()->create([
                    'category' => $this->nullableString($row['category'] ?? null),
                    'position' => $position++,
                    'name' => $row['name'],
                    'unit' => $this->nullableString($row['unit'] ?? null),
                    'quantity' => $row['quantity'],
                    'price' => $row['price'],
                    'total' => round((float) $row['quantity'] * (float) $row['price'], 2),
                ]);
            }

            $position = 0;
            foreach ($request->validated('materials') as $row) {
                $estimate->materialItems()->create([
                    'position' => $position++,
                    'name' => $row['name'],
                    'unit' => $this->nullableString($row['unit'] ?? null),
                    'quantity' => $row['quantity'],
                    'price' => $row['price'],
                    'total' => round((float) $row['quantity'] * (float) $row['price'], 2),
                ]);
            }
        });

        return to_route('projects.estimate.edit', $project)->with('success', 'Смета сохранена.');
    }

    /**
     * Сгруппировать позиции работ по подкатегории (в порядке появления).
     *
     * @return array<int, array{category: string, rows: array<int, array<string, mixed>>}>
     */
    private function groupWorkItems(Estimate $estimate): array
    {
        $groups = [];

        foreach ($estimate->workItems as $item) {
            $key = $item->category ?? '';
            $groups[$key] ??= ['category' => $item->category ?? '', 'rows' => []];
            $groups[$key]['rows'][] = [
                'name' => $item->name,
                'unit' => $item->unit,
                'quantity' => (float) $item->quantity,
                'price' => (float) $item->price,
                'total' => (float) $item->total,
            ];
        }

        return array_values($groups);
    }

    private function nullableString(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' ? null : $value;
    }
}
