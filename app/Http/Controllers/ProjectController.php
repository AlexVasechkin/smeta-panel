<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\City;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();

        $projects = Project::query()
            ->with('client:id,name')
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('address', 'ilike', "%{$search}%");
            }))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'order_number' => $project->order_number,
                'address' => $project->address,
                'area' => $project->area,
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
                'status_color' => $project->status->color(),
                'client' => $project->client ? [
                    'id' => $project->client->id,
                    'name' => $project->client->name,
                ] : null,
            ]);

        return Inertia::render('projects/index', [
            'projects' => $projects,
            'filters' => ['search' => $search, 'status' => $status],
            'statuses' => ProjectStatus::options(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('projects/create', [
            'clients' => $this->clientOptions(),
            'cities' => $this->cityOptions(),
            'statuses' => ProjectStatus::options(),
            'selectedClientId' => $request->integer('client_id') ?: null,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = Project::create($request->validated());

        return to_route('projects.show', $project)->with('success', 'Объект добавлен.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project): Response
    {
        $project->load(['client:id,name,phone,email', 'city:id,name', 'documents']);

        return Inertia::render('projects/show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'order_number' => $project->order_number,
                'city' => $project->city?->name,
                'address' => $project->address,
                'area' => $project->area,
                'rooms' => $project->rooms,
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
                'status_color' => $project->status->color(),
                'start_date' => $project->start_date?->format('Y-m-d'),
                'notes' => $project->notes,
                'client' => $project->client ? [
                    'id' => $project->client->id,
                    'name' => $project->client->name,
                    'phone' => $project->client->phone,
                    'email' => $project->client->email,
                ] : null,
                'documents' => $project->documents->map(fn ($document) => [
                    'id' => $document->id,
                    'type_label' => $document->type->label(),
                    'title' => $document->title,
                    'created_at' => $document->created_at?->format('d.m.Y H:i'),
                ]),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project): Response
    {
        return Inertia::render('projects/edit', [
            'project' => [
                'id' => $project->id,
                'client_id' => $project->client_id,
                'city_id' => $project->city_id,
                'name' => $project->name,
                'order_number' => $project->order_number,
                'address' => $project->address,
                'area' => $project->area,
                'rooms' => $project->rooms,
                'status' => $project->status->value,
                'start_date' => $project->start_date?->format('Y-m-d'),
                'notes' => $project->notes,
            ],
            'clients' => $this->clientOptions(),
            'cities' => $this->cityOptions(),
            'statuses' => ProjectStatus::options(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        return to_route('projects.show', $project)->with('success', 'Объект обновлён.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return to_route('projects.index')->with('success', 'Объект удалён.');
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function clientOptions(): array
    {
        return Client::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $client) => ['value' => $client->id, 'label' => $client->name])
            ->all();
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function cityOptions(): array
    {
        $organizationId = auth()->user()?->currentOrganization()?->id;

        return City::query()
            ->active()
            ->availableFor($organizationId)
            ->ordered()
            ->get(['id', 'name'])
            ->map(fn (City $city) => ['value' => $city->id, 'label' => $city->name])
            ->all();
    }
}
