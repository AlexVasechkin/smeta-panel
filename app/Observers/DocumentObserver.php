<?php

namespace App\Observers;

use App\Models\Document;
use App\Services\MonthlyDocumentTotals;

/**
 * Поддерживает актуальность помесячного агрегата сумм по документам: при создании
 * или удалении документа отслеживаемого типа (акт выполненных работ, акт приёма-
 * передачи денег) пересчитывает сумму этого типа за его месяц.
 */
class DocumentObserver
{
    public function __construct(private readonly MonthlyDocumentTotals $totals) {}

    public function created(Document $document): void
    {
        $this->sync($document);
    }

    public function deleted(Document $document): void
    {
        $this->sync($document);
    }

    private function sync(Document $document): void
    {
        if (! in_array($document->type, MonthlyDocumentTotals::TRACKED_TYPES, true)) {
            return;
        }

        $this->totals->recalculateForMonth($document->type, $document->created_at ?? now());
    }
}
