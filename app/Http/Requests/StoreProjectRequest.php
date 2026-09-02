<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreProjectRequest extends FormRequest
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
            'client_id' => ['required', 'exists:clients,id'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'name' => ['required', 'string', 'max:255'],
            // При создании можно оставить пустым — номер присвоится автоматически.
            'order_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('projects', 'order_number')->ignore($this->route('project')),
            ],
            'address' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'rooms' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'status' => ['required', new Enum(ProjectStatus::class)],
            'start_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
