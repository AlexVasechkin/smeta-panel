<?php

namespace App\Http\Requests;

use App\Models\City;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreCityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->currentOrganization() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Запрет дублей: город не должен совпадать с уже доступным
     * (системным либо принадлежащим организации пользователя).
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $name = trim((string) $this->input('name'));

                if ($name === '') {
                    return;
                }

                $organizationId = $this->user()?->currentOrganization()?->id;

                // Регистронезависимое сравнение на стороне PHP: Postgres lower()
                // не приводит кириллицу к нижнему регистру при C-локали БД.
                $exists = City::query()
                    ->availableFor($organizationId)
                    ->pluck('name')
                    ->contains(fn (string $existing) => mb_strtolower(trim($existing)) === mb_strtolower($name));

                if ($exists) {
                    $validator->errors()->add('name', 'Такой город уже есть в списке.');
                }
            },
        ];
    }
}
