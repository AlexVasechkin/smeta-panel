<?php

namespace App\Support\Documents;

use App\Enums\DocumentType;
use App\Models\Organization;
use App\Models\Project;

/**
 * Данные сметы по умолчанию (для этапа предпросмотра).
 *
 * Смета создаётся на основании предварительной сметы объекта: берётся payload
 * последней сохранённой предварительной сметы. Если её ещё нет — данные
 * собираются из объекта так же, как для предварительной сметы.
 */
class FinalEstimateData
{
    /**
     * @return array<string, mixed>
     */
    public static function forProject(Project $project, ?Organization $organization, string $templateAbsPath): array
    {
        $preliminary = $project->documents()
            ->where('type', DocumentType::PreliminaryEstimate->value)
            ->latest('id')
            ->first();

        if ($preliminary !== null && is_array($preliminary->payload)) {
            return $preliminary->payload;
        }

        return PreliminaryEstimateData::forProject($project, $organization, $templateAbsPath);
    }
}
