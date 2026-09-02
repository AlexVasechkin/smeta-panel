<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Support\Documents\CashAcceptanceActData;
use App\Support\Documents\CashAcceptanceActWriter;
use App\Support\Documents\ContractData;
use App\Support\Documents\ContractWriter;
use App\Support\Documents\FinalEstimateData;
use App\Support\Documents\FinalEstimateWriter;
use App\Support\Documents\PreliminaryEstimateData;
use App\Support\Documents\PreliminaryEstimateWriter;
use App\Support\Documents\WorkCompletionActData;
use App\Support\Documents\WorkCompletionActWriter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Оркестрация генерации документов: чтение шаблона и запись результата через
 * фасад Storage (диск по умолчанию — local сейчас, s3 позже без правок кода).
 */
class DocumentGenerator
{
    /**
     * Данные документа по умолчанию (для этапа предпросмотра).
     *
     * @return array<string, mixed>
     */
    public function defaultPayload(Project $project, DocumentType $type, ?Organization $organization): array
    {
        $template = $this->copyTemplateToTemp($type);

        try {
            return match ($type) {
                DocumentType::PreliminaryEstimate => PreliminaryEstimateData::forProject($project, $organization, $template),
                DocumentType::Estimate => FinalEstimateData::forProject($project, $organization, $template),
                DocumentType::Contract => ContractData::forProject($project, $organization),
                DocumentType::CashAcceptanceAct => CashAcceptanceActData::forProject($project, $organization),
                DocumentType::WorkCompletionAct => WorkCompletionActData::forProject($project, $organization),
                default => throw $this->notImplemented($type),
            };
        } finally {
            @unlink($template);
        }
    }

    /**
     * Сгенерировать файл документа, сохранить его и создать запись Document.
     *
     * @param  array<string, mixed>  $payload
     */
    public function generate(Project $project, DocumentType $type, array $payload, ?User $user): Document
    {
        $disk = config('filesystems.default');
        $extension = $type->fileExtension();
        $template = $this->copyTemplateToTemp($type);
        $output = tempnam(sys_get_temp_dir(), 'doc_').'.'.$extension;

        try {
            match ($type) {
                DocumentType::PreliminaryEstimate => (new PreliminaryEstimateWriter)->write($payload, $template, $output),
                DocumentType::Estimate => (new FinalEstimateWriter)->write($payload, $template, $output),
                DocumentType::Contract => (new ContractWriter)->write($payload, $template, $output),
                DocumentType::CashAcceptanceAct => (new CashAcceptanceActWriter)->write($payload, $template, $output),
                DocumentType::WorkCompletionAct => (new WorkCompletionActWriter)->write($payload, $template, $output),
                default => throw $this->notImplemented($type),
            };

            $path = sprintf('documents/%d/%s.%s', $project->id, (string) Str::uuid(), $extension);
            Storage::disk($disk)->put($path, file_get_contents($output));

            return Document::create([
                'project_id' => $project->id,
                'created_by' => $user?->id,
                'type' => $type->value,
                'title' => $type->label().' — '.$project->name,
                'disk' => $disk,
                'path' => $path,
                'payload' => $payload,
            ]);
        } finally {
            @unlink($template);
            @unlink($output);
        }
    }

    /**
     * Скопировать шаблон с диска во временный файл (нужен реальный путь для PhpSpreadsheet).
     */
    private function copyTemplateToTemp(DocumentType $type): string
    {
        $templatePath = $type->templatePath();

        if ($templatePath === null) {
            throw $this->notImplemented($type);
        }

        $disk = Storage::disk(config('filesystems.default'));

        if (! $disk->exists($templatePath)) {
            throw new RuntimeException("Шаблон документа не найден: {$templatePath}");
        }

        $tmp = tempnam(sys_get_temp_dir(), 'tpl_').'.'.$type->fileExtension();
        file_put_contents($tmp, $disk->get($templatePath));

        return $tmp;
    }

    private function notImplemented(DocumentType $type): RuntimeException
    {
        return new RuntimeException("Генерация документа «{$type->label()}» ещё не реализована.");
    }
}
