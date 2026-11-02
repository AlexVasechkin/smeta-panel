<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationType;
use App\Http\Requests\StorePartnerOrganizationRequest;
use App\Http\Requests\UpdatePartnerOrganizationRequest;
use App\Models\Organization;
use App\Support\Passport;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Справочник организаций, с которыми может работать организация пользователя.
 * Сами организации хранятся в таблице organizations, принадлежность справочнику —
 * в partner_organization_links.
 */
class PartnerOrganizationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $parent = $this->parentOrganization($request);

        $search = trim($request->string('search')->toString());
        $type = $request->string('type')->toString();

        $partners = $parent->partners()
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = $this->likeOperator();

                $query->where(function (Builder $inner) use ($search, $like) {
                    $inner->where('organizations.name', $like, "%{$search}%")
                        ->orWhere('organizations.legal_name', $like, "%{$search}%")
                        ->orWhere('organizations.inn', $like, "%{$search}%");
                });
            })
            ->when(
                OrganizationType::tryFrom($type) !== null,
                fn (Builder $query) => $query->where('organizations.type', $type),
            )
            ->orderBy('organizations.name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Organization $organization) => [
                'id' => $organization->id,
                'type' => $organization->type?->value,
                'type_label' => $organization->type?->label(),
                'type_color' => $organization->type?->color(),
                'name' => $organization->name,
                'inn' => $organization->inn,
                'director' => $this->directorFullName($organization),
                'phone' => $organization->phone,
            ]);

        return Inertia::render('partners/index', [
            'partners' => $partners,
            'filters' => ['search' => $search, 'type' => $type],
            'organizationTypes' => OrganizationType::options(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $this->parentOrganization($request);

        return Inertia::render('partners/create', [
            'organizationTypes' => OrganizationType::options(),
            'passportTypes' => Passport::typeOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePartnerOrganizationRequest $request): RedirectResponse
    {
        $parent = $this->parentOrganization($request);

        DB::transaction(function () use ($parent, $request) {
            $organization = Organization::create($request->safe()->except('passport'));

            // Связь строится от организации текущего пользователя:
            // User.id -> OrganizationMember.user_id -> OrganizationMember.organization_id
            // -> partner_organization_links.parent_organization_id
            $parent->partnerLinks()->create(['organization_id' => $organization->id]);

            $this->syncPassport($organization, $request);
        });

        return to_route('partners.index')->with('success', 'Организация добавлена в справочник.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Organization $organization): Response
    {
        $this->assertInDirectory($request, $organization);

        return Inertia::render('partners/show', [
            'partner' => [
                ...$this->requisites($organization),
                'type_label' => $organization->type?->label(),
                'type_full_label' => $organization->type?->fullLabel(),
                'type_color' => $organization->type?->color(),
                'registration_number_label' => $organization->type?->registrationNumberLabel() ?? 'ОГРН',
                'director' => $this->directorFullName($organization),
                'passport' => $this->passportPayload($organization),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Organization $organization): Response
    {
        $this->assertInDirectory($request, $organization);

        return Inertia::render('partners/edit', [
            'partner' => [
                ...$this->requisites($organization),
                'passport' => $this->passportPayload($organization),
            ],
            'organizationTypes' => OrganizationType::options(),
            'passportTypes' => Passport::typeOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePartnerOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $this->assertInDirectory($request, $organization);

        DB::transaction(function () use ($organization, $request) {
            $organization->update($request->safe()->except('passport'));

            $this->syncPassport($organization, $request);
        });

        return to_route('partners.index')->with('success', 'Организация обновлена.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Organization $organization): RedirectResponse
    {
        $parent = $this->parentOrganization($request);
        $this->assertInDirectory($request, $organization);

        DB::transaction(function () use ($parent, $organization) {
            $parent->partnerLinks()->where('organization_id', $organization->id)->delete();

            // Карточка партнёра существует только внутри справочников: удаляем саму
            // организацию, если она не рабочая (нет участников) и больше ни в одном
            // справочнике не числится. EAV-значения чистим вручную — у полиморфной
            // связи нет внешнего ключа с каскадом.
            if (! $organization->members()->exists() && ! $organization->partnerOf()->exists()) {
                $organization->attributeValues()->delete();
                $organization->delete();
            }
        });

        return to_route('partners.index')->with('success', 'Организация удалена из справочника.');
    }

    /**
     * Организация текущего пользователя — владелец справочника.
     * Цепочка: User.id -> OrganizationMember.user_id -> OrganizationMember.organization_id.
     */
    protected function parentOrganization(Request $request): Organization
    {
        return $request->user()->currentOrganization()
            ?? abort(404);
    }

    /**
     * Организация должна быть в справочнике текущего пользователя.
     */
    protected function assertInDirectory(Request $request, Organization $organization): void
    {
        $parent = $this->parentOrganization($request);

        abort_unless(
            $parent->partnerLinks()->where('organization_id', $organization->id)->exists(),
            404,
        );
    }

    /**
     * Реквизиты организации (колонки таблицы organizations) для фронтенда.
     *
     * @return array<string, mixed>
     */
    private function requisites(Organization $organization): array
    {
        return $organization->only([
            'id',
            'type',
            'name',
            'legal_name',
            'inn',
            'kpp',
            'ogrn',
            'address',
            'phone',
            'email',
            'director_surname',
            'director_name',
            'director_father_name',
            'bank_name',
            'bank_bik',
            'bank_account',
            'bank_corr_account',
        ]);
    }

    /**
     * ФИО руководителя одной строкой (или null, если не заполнено).
     */
    private function directorFullName(Organization $organization): ?string
    {
        $parts = array_filter([
            $organization->director_surname,
            $organization->director_name,
            $organization->director_father_name,
        ]);

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * Сохранить паспортные данные руководителя в EAV-свойства.
     */
    private function syncPassport(Organization $organization, FormRequest $request): void
    {
        $passport = $request->validated('passport') ?? [];

        foreach (Passport::extendedFields() as $key => $definition) {
            if (array_key_exists($key, $passport)) {
                $organization->setEav($definition['code'], $passport[$key]);
            }
        }
    }

    /**
     * Паспортные данные руководителя для фронтенда.
     *
     * @return array<string, string|null>
     */
    private function passportPayload(Organization $organization): array
    {
        $values = $organization->eav();
        $passport = [];

        foreach (Passport::extendedFields() as $key => $definition) {
            $value = $values[$definition['code']] ?? null;
            $passport[$key] = $value instanceof DateTimeInterface
                ? $value->format('Y-m-d')
                : $value;
        }

        return $passport;
    }

    /**
     * Оператор регистронезависимого поиска: в PostgreSQL — ilike,
     * в остальных драйверах (sqlite в тестах) — like.
     */
    private function likeOperator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
