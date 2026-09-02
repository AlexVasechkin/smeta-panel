<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\OrganizationUpdateRequest;
use App\Models\Organization;
use App\Support\Passport;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    /**
     * Show the organization settings page.
     */
    public function edit(Request $request): Response
    {
        $organization = $this->resolveOrganization($request);

        return Inertia::render('settings/organization', [
            'organization' => $organization,
            'directorPassport' => $this->passportPayload($organization),
            'passportTypes' => Passport::typeOptions(),
        ]);
    }

    /**
     * Update the current user's organization.
     */
    public function update(OrganizationUpdateRequest $request): RedirectResponse
    {
        $organization = $this->resolveOrganization($request);

        $organization->update($request->safe()->except('passport'));

        $this->syncPassport($organization, $request);

        return to_route('organization.edit');
    }

    /**
     * Организация текущего пользователя (создаётся при регистрации).
     */
    protected function resolveOrganization(Request $request): Organization
    {
        return $request->user()->currentOrganization()
            ?? abort(404);
    }

    /**
     * Сохранить паспортные данные руководителя в EAV-свойства.
     */
    private function syncPassport(Organization $organization, OrganizationUpdateRequest $request): void
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
}
