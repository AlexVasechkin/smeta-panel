<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
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
        $implemented = array_map(
            fn (DocumentType $type) => $type->value,
            array_filter(DocumentType::cases(), fn (DocumentType $type) => $type->isImplemented()),
        );

        $base = [
            'type' => ['required', Rule::in($implemented)],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'payload' => ['required', 'array'],
        ];

        $type = DocumentType::tryFrom((string) $this->input('type'));

        // Акт выполненных работ — плоский payload + отмеченные позиции и аванс.
        if ($type === DocumentType::WorkCompletionAct) {
            return array_merge($base, [
                'payload.advance_percent' => ['nullable', 'numeric', 'between:0,100'],
                'payload.positions' => ['present', 'array'],
                'payload.positions.*.key' => ['nullable', 'string', 'max:255'],
                'payload.positions.*.group' => ['nullable', 'string', 'max:255'],
                'payload.positions.*.name' => ['nullable', 'string', 'max:500'],
                'payload.positions.*.unit' => ['nullable', 'string', 'max:50'],
                'payload.positions.*.quantity' => ['nullable', 'numeric'],
                'payload.positions.*.price' => ['nullable', 'numeric'],
                'payload.*' => ['nullable'],
            ]);
        }

        // Договор и акт денежных средств — плоский payload (набор строковых полей и сумм).
        if (in_array($type, [DocumentType::Contract, DocumentType::CashAcceptanceAct], true)) {
            return array_merge($base, [
                'payload.works_cost' => ['nullable', 'numeric'],
                'payload.materials_cost' => ['nullable', 'numeric'],
                'payload.total_cost' => ['nullable', 'numeric'],
                'payload.extras_cost' => ['nullable', 'numeric'],
                'payload.cost' => ['nullable', 'numeric'],
                'payload.*' => ['nullable'],
            ]);
        }

        return array_merge($base, [
            'payload.header' => ['required', 'array'],
            'payload.header.date' => ['nullable', 'string', 'max:255'],
            'payload.header.title' => ['nullable', 'string', 'max:500'],
            'payload.header.object' => ['nullable', 'string', 'max:500'],
            'payload.header.customer' => ['nullable', 'string', 'max:255'],
            'payload.header.address' => ['nullable', 'string', 'max:500'],
            'payload.header.contractor_signatory' => ['nullable', 'string', 'max:255'],
            'payload.header.customer_signatory' => ['nullable', 'string', 'max:255'],

            'payload.work_groups' => ['present', 'array'],
            'payload.work_groups.*.title' => ['nullable', 'string', 'max:255'],
            'payload.work_groups.*.items' => ['present', 'array'],
            'payload.work_groups.*.items.*.name' => ['nullable', 'string', 'max:500'],
            'payload.work_groups.*.items.*.unit' => ['nullable', 'string', 'max:50'],
            'payload.work_groups.*.items.*.quantity' => ['nullable', 'numeric'],
            'payload.work_groups.*.items.*.price' => ['nullable', 'numeric'],

            'payload.materials' => ['present', 'array'],
            'payload.materials.*.name' => ['nullable', 'string', 'max:500'],
            'payload.materials.*.unit' => ['nullable', 'string', 'max:50'],
            'payload.materials.*.quantity' => ['nullable', 'numeric'],
            'payload.materials.*.price' => ['nullable', 'numeric'],

            'payload.extras' => ['present', 'array'],
            'payload.extras.*.label' => ['nullable', 'string', 'max:500'],
            'payload.extras.*.amount' => ['nullable', 'numeric'],

            'payload.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
        ]);
    }
}
