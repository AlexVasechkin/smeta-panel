<?php

namespace App\Http\Requests\Settings;

use App\Support\Passport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'inn' => ['nullable', 'string', 'max:12'],
            'kpp' => ['nullable', 'string', 'max:9'],
            'ogrn' => ['nullable', 'string', 'max:15'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'director_name' => ['nullable', 'string', 'max:255'],
            'director_surname' => ['nullable', 'string', 'max:255'],
            'director_father_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_bik' => ['nullable', 'string', 'max:9'],
            'bank_account' => ['nullable', 'string', 'max:20'],
            'bank_corr_account' => ['nullable', 'string', 'max:20'],

            // Паспортные данные руководителя (EAV).
            'passport' => ['nullable', 'array'],
            'passport.type' => ['nullable', Rule::in(Passport::TYPES)],
            'passport.series' => ['nullable', 'string', 'max:10'],
            'passport.number' => ['nullable', 'string', 'max:20'],
            'passport.issued_by' => ['nullable', 'string', 'max:255'],
            'passport.issued_at' => ['nullable', 'date'],
            'passport.registration_address' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
