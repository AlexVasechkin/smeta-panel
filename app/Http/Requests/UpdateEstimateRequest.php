<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'works' => ['present', 'array'],
            'works.*.category' => ['nullable', 'string', 'max:255'],
            'works.*.name' => ['required', 'string', 'max:255'],
            'works.*.unit' => ['nullable', 'string', 'max:50'],
            'works.*.quantity' => ['required', 'numeric', 'min:0'],
            'works.*.price' => ['required', 'numeric', 'min:0'],

            'materials' => ['present', 'array'],
            'materials.*.name' => ['required', 'string', 'max:255'],
            'materials.*.unit' => ['nullable', 'string', 'max:50'],
            'materials.*.quantity' => ['required', 'numeric', 'min:0'],
            'materials.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
