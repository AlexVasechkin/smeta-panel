<?php

namespace App\Http\Requests;

use App\Models\DocumentTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Unique;

/**
 * При редактировании файл необязателен: можно поменять только название
 * и описание, оставив прежний .docx.
 */
class UpdateDocumentTemplateRequest extends StoreDocumentTemplateRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueName()],
            'description' => ['nullable', 'string', 'max:1000'],
            'file' => ['nullable', ...$this->fileRules()],
        ];
    }

    /**
     * Текущий шаблон из проверки уникальности исключаем.
     */
    protected function uniqueName(): Unique
    {
        $template = $this->route('document_template');

        $rule = parent::uniqueName();

        return $template instanceof DocumentTemplate
            ? $rule->ignore($template->getKey())
            : $rule;
    }
}
