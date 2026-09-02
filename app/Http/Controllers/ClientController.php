<?php

namespace App\Http\Controllers;

use App\Enums\ClientType;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Support\Passport;
use DateTimeInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $clients = Client::query()
            ->withCount('projects')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                        ->orWhere('phone', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Client $client) => [
                'id' => $client->id,
                'type' => $client->type->value,
                'type_label' => $client->type->label(),
                'name' => $client->name,
                'phone' => $client->phone,
                'email' => $client->email,
                'projects_count' => $client->projects_count,
            ]);

        return Inertia::render('clients/index', [
            'clients' => $clients,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('clients/create', [
            'clientTypes' => ClientType::options(),
            'passportTypes' => Passport::typeOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreClientRequest $request): RedirectResponse
    {
        $client = Client::create($request->safe()->except('passport'));

        $this->syncPassport($client, $request);

        return to_route('clients.index')->with('success', 'Клиент добавлен.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Client $client): Response
    {
        $client->load(['projects' => fn ($q) => $q->latest()]);

        return Inertia::render('clients/show', [
            'client' => [
                'id' => $client->id,
                'type' => $client->type->value,
                'type_label' => $client->type->label(),
                'name' => $client->name,
                'surname' => $client->surname,
                'father_name' => $client->father_name,
                'phone' => $client->phone,
                'email' => $client->email,
                'notes' => $client->notes,
                'passport' => $this->passportPayload($client),
                'projects' => $client->projects->map(fn ($project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'address' => $project->address,
                    'status' => $project->status->value,
                    'status_label' => $project->status->label(),
                    'status_color' => $project->status->color(),
                ]),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Client $client): Response
    {
        return Inertia::render('clients/edit', [
            'client' => array_merge(
                $client->only(['id', 'type', 'name', 'surname', 'father_name', 'phone', 'email', 'notes']),
                ['passport' => $this->passportPayload($client)],
            ),
            'clientTypes' => ClientType::options(),
            'passportTypes' => Passport::typeOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->safe()->except('passport'));

        $this->syncPassport($client, $request);

        return to_route('clients.index')->with('success', 'Клиент обновлён.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return to_route('clients.index')->with('success', 'Клиент удалён.');
    }

    /**
     * Сохранить паспортные данные физлица в EAV-свойства.
     * Для юрлиц паспорт не применяется.
     */
    private function syncPassport(Client $client, FormRequest $request): void
    {
        if ($client->type !== ClientType::Individual) {
            return;
        }

        $passport = $request->validated('passport') ?? [];

        foreach (Passport::extendedFields() as $key => $definition) {
            if (array_key_exists($key, $passport)) {
                $client->setEav($definition['code'], $passport[$key]);
            }
        }
    }

    /**
     * Паспортные данные физлица для фронтенда (или null для юрлица).
     *
     * @return array<string, string|null>|null
     */
    private function passportPayload(Client $client): ?array
    {
        if ($client->type !== ClientType::Individual) {
            return null;
        }

        $values = $client->eav();
        $passport = [];

        foreach (Passport::extendedFields() as $key => $definition) {
            $value = $values[$definition['code']] ?? null;
            $passport[$key] = $value instanceof DateTimeInterface
                ? $value->format('Y-m-d')
                : $value;
        }

        return $passport;
    }
}
