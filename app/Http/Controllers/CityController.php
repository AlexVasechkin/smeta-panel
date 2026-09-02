<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCityRequest;
use App\Models\City;
use Illuminate\Http\RedirectResponse;

class CityController extends Controller
{
    /**
     * Создать пользовательский город для организации текущего пользователя.
     */
    public function store(StoreCityRequest $request): RedirectResponse
    {
        $organization = $request->user()->currentOrganization();

        $city = City::create([
            'organization_id' => $organization->id,
            'name' => trim($request->validated('name')),
            'sort_order' => 500,
            'is_active' => true,
        ]);

        // created_city пробрасывается на фронт (см. HandleInertiaRequests),
        // чтобы форма могла сразу выбрать добавленный город.
        return back()->with('created_city', ['id' => $city->id, 'name' => $city->name]);
    }
}
