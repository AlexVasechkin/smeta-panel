<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreDocumentTemplateRequest extends FormRequest
{
    /**
     * Шаблоны принадлежат организации, поэтому без неё раздел недоступен.
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
            'name' => ['required', 'string', 'max:255', $this->uniqueName()],
            'description' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', ...$this->fileRules()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'название',
            'description' => 'описание',
            'file' => 'файл шаблона',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Шаблон должен быть документом Word (.docx).',
        ];
    }

    /**
     * Правила для самого файла: только .docx, не больше 10 МБ.
     *
     * @return array<int, ValidationRule|string>
     */
    protected function fileRules(): array
    {
        return ['file', 'mimes:docx', 'max:10240'];
    }

    /**
     * Имя шаблона уникально в пределах организации.
     */
    protected function uniqueName(): Unique
    {
        return Rule::unique('document_templates', 'name')
            ->where('organization_id', $this->user()?->currentOrganization()?->id);
    }
}
