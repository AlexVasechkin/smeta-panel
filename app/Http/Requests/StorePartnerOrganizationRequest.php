<?php

namespace App\Http\Requests;

use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Support\Passport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StorePartnerOrganizationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->currentOrganization() !== null;
    }

    /**
     * У ИП нет КПП — не сохраняем его, даже если поле пришло из формы.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('type') === OrganizationType::Ip->value) {
            $this->merge(['kpp' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(OrganizationType::class)],
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'inn' => ['nullable', 'digits_between:10,12'],
            'kpp' => ['nullable', 'digits:9'],
            'ogrn' => ['nullable', 'digits_between:13,15'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'director_surname' => ['nullable', 'string', 'max:255'],
            'director_name' => ['nullable', 'string', 'max:255'],
            'director_father_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_bik' => ['nullable', 'digits:9'],
            'bank_account' => ['nullable', 'digits:20'],
            'bank_corr_account' => ['nullable', 'digits:20'],

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

    /**
     * Проверки, зависящие от выбранной формы (ИП / ООО), и запрет дублей по ИНН
     * внутри справочника текущей организации.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['type', 'inn', 'ogrn'])) {
                    return;
                }

                $type = OrganizationType::tryFrom((string) $this->input('type'));

                if ($type === null) {
                    return;
                }

                $this->validateInn($validator, $type);
                $this->validateRegistrationNumber($validator, $type);
            },
        ];
    }

    /**
     * Разрядность ИНН зависит от формы: 12 цифр у ИП, 10 у ООО.
     */
    private function validateInn(Validator $validator, OrganizationType $type): void
    {
        $inn = trim((string) $this->input('inn'));

        if ($inn === '') {
            return;
        }

        if (mb_strlen($inn) !== $type->innLength()) {
            $validator->errors()->add(
                'inn',
                "ИНН {$type->label()} состоит из {$type->innLength()} цифр.",
            );

            return;
        }

        $this->validateInnIsUnique($validator, $inn);
    }

    /**
     * ОГРНИП — 15 цифр, ОГРН — 13.
     */
    private function validateRegistrationNumber(Validator $validator, OrganizationType $type): void
    {
        $ogrn = trim((string) $this->input('ogrn'));

        if ($ogrn === '' || mb_strlen($ogrn) === $type->registrationNumberLength()) {
            return;
        }

        $validator->errors()->add(
            'ogrn',
            "{$type->registrationNumberLabel()} состоит из {$type->registrationNumberLength()} цифр.",
        );
    }

    /**
     * Одна и та же организация не должна попасть в справочник дважды.
     */
    private function validateInnIsUnique(Validator $validator, string $inn): void
    {
        $parent = $this->user()?->currentOrganization();

        if ($parent === null) {
            return;
        }

        $current = $this->route('organization');

        $exists = $parent->partners()
            ->where('organizations.inn', $inn)
            ->when(
                $current instanceof Organization,
                fn ($query) => $query->whereKeyNot($current->getKey()),
            )
            ->exists();

        if ($exists) {
            $validator->errors()->add('inn', 'Организация с таким ИНН уже есть в справочнике.');
        }
    }
}
