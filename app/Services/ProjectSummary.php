<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Project;
use App\Support\Documents\FinalEstimateWriter;
use App\Support\Documents\WorkCompletionActData;

/**
 * Сводные показатели по объекту для карточки: получено денег, сумма по договору
 * (без и с учётом скидок) и прогресс закрытия позиций сметы актами работ.
 *
 * Источники: последняя «Смета» объекта (суммы и полный список позиций) и акты
 * приёма-передачи денег / выполненных работ. Логика закрытия позиций
 * переиспользует WorkCompletionActData, чтобы совпадать с мастером актов.
 */
class ProjectSummary
{
    /** Порог, ниже которого остаток позиции считается закрытым. */
    private const EPS = 1e-6;

    /**
     * @return array{
     *     cash_received: float,
     *     contract_total: float,
     *     contract_total_discounted: float,
     *     positions_total: int,
     *     positions_closed: int
     * }
     */
    public function for(Project $project): array
    {
        $documents = $project->relationLoaded('documents')
            ? $project->documents
            : $project->documents()->get();

        $cashReceived = $documents
            ->where('type', DocumentType::CashAcceptanceAct)
            ->sum(fn (Document $document) => MonthlyDocumentTotals::amountOf($document));

        $estimate = $documents
            ->where('type', DocumentType::Estimate)
            ->sortByDesc('id')
            ->first();

        $totals = $this->contractTotals($estimate);
        $positions = $this->positionProgress($project, $estimate);

        return [
            'cash_received' => round((float) $cashReceived, 2),
            'contract_total' => $totals['total'],
            'contract_total_discounted' => $totals['discounted'],
            'positions_total' => $positions['total'],
            'positions_closed' => $positions['closed'],
        ];
    }

    /**
     * Сумма по договору без скидки (работы + материалы + доп.) и с учётом
     * скидки (grand: работы со скидкой + материалы + доп.).
     *
     * @return array{total: float, discounted: float}
     */
    private function contractTotals(?Document $estimate): array
    {
        if ($estimate === null || ! is_array($estimate->payload)) {
            return ['total' => 0.0, 'discounted' => 0.0];
        }

        $t = (new FinalEstimateWriter)->computeTotals($estimate->payload);

        return [
            'total' => round($t['works'] + $t['materials'] + $t['extras'], 2),
            'discounted' => round($t['grand'], 2),
        ];
    }

    /**
     * Всего позиций работ в смете и сколько из них закрыто актами выполненных работ.
     *
     * Закрытой считается только полностью закрытая позиция (суммарный закрытый
     * объём по всем актам ≥ объёма по смете); частично закрытая идёт как 0.
     *
     * @return array{total: int, closed: int}
     */
    private function positionProgress(Project $project, ?Document $estimate): array
    {
        if ($estimate === null || ! is_array($estimate->payload)) {
            return ['total' => 0, 'closed' => 0];
        }

        $closed = WorkCompletionActData::closedQuantities($project);
        $total = 0;
        $closedCount = 0;

        foreach ($estimate->payload['work_groups'] ?? [] as $gi => $group) {
            foreach ($group['items'] ?? [] as $ii => $item) {
                $total++;
                $key = WorkCompletionActData::positionKey((int) $gi, (int) $ii);
                $remaining = (float) ($item['quantity'] ?? 0) - ($closed[$key] ?? 0.0);

                if ($remaining <= self::EPS) {
                    $closedCount++;
                }
            }
        }

        return ['total' => $total, 'closed' => $closedCount];
    }
}
