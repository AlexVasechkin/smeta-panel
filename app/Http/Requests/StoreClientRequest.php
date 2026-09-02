<?php

namespace App\Http\Requests;

use App\Enums\ClientType;
use App\Support\Passport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(ClientType::class)],
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],

            // Паспортные данные физлица (EAV). Сохраняются только при type=individual.
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
