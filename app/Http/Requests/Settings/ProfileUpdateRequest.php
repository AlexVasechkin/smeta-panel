<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use App\Support\Passport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],

            // Паспортные данные пользователя (EAV).
            'passport' => ['nullable', 'array'],
            'passport.type' => ['nullable', Rule::in(Passport::TYPES)],
            'passport.series' => ['nullable', 'string', 'max:10'],
            'passport.number' => ['nullable', 'string', 'max:20'],
            'passport.issued_by' => ['nullable', 'string', 'max:255'],
            'passport.issued_at' => ['nullable', 'date'],
        ];
    }
}
