<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\MonthlyDocumentTotal;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Подсчёт помесячных сумм по документам отслеживаемых типов (акты выполненных
 * работ и акты приёма-передачи денег).
 *
 * Значение хранится денормализованно (таблица monthly_document_totals) и
 * пересчитывается «с нуля» из документов месяца при каждом создании/удалении —
 * документы в БД остаются единственным источником истины, дрейфа не возникает.
 */
class MonthlyDocumentTotals
{
    /** Типы документов, по которым ведётся помесячный агрегат. */
    public const TRACKED_TYPES = [
        DocumentType::WorkCompletionAct,
        DocumentType::CashAcceptanceAct,
    ];

    /**
     * Пересчитать и сохранить сумму по типу за месяц указанной даты.
     */
    public function recalculateForMonth(DocumentType $type, CarbonInterface $date): void
    {
        $start = $date->copy()->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $total = Document::query()
            ->where('type', $type->value)
            ->whereBetween('created_at', [$start, $end])
            ->get()
            ->sum(fn (Document $document) => self::amountOf($document));

        MonthlyDocumentTotal::updateOrCreate(
            ['type' => $type->value, 'period' => $start->toDateString()],
            ['total' => round((float) $total, 2)],
        );
    }

    /**
     * Пересчитать все 12 месяцев указанного года по типу.
     *
     * @return array<int, float> суммы по месяцам (ключ — номер месяца 1..12)
     */
    public function recalculateYear(DocumentType $type, int $year): array
    {
        $totals = [];

        for ($month = 1; $month <= 12; $month++) {
            $date = Carbon::create($year, $month, 1)->startOfDay();
            $this->recalculateForMonth($type, $date);
            $totals[$month] = (float) MonthlyDocumentTotal::query()
                ->where('type', $type->value)
                ->whereDate('period', $date->toDateString())
                ->value('total');
        }

        return $totals;
    }

    /**
     * Сумма по типу за текущий месяц (0, если документов ещё не было).
     */
    public function currentMonthTotal(DocumentType $type): float
    {
        return (float) (MonthlyDocumentTotal::query()
            ->where('type', $type->value)
            ->whereDate('period', Carbon::now()->startOfMonth()->toDateString())
            ->value('total') ?? 0.0);
    }

    /**
     * Вклад одного документа в сумму — зависит от типа:
     *  - акт выполненных работ: Σ кол-во × цена по позициям;
     *  - акт приёма-передачи денег: сумма (payload.cost);
     *  - прочие типы в агрегате не участвуют.
     */
    public static function amountOf(Document $document): float
    {
        return match ($document->type) {
            DocumentType::WorkCompletionAct => self::positionsTotal($document->payload),
            DocumentType::CashAcceptanceAct => self::costAmount($document->payload),
            default => 0.0,
        };
    }

    /**
     * Стоимость закрытых позиций акта выполненных работ: Σ кол-во × цена.
     */
    public static function positionsTotal(mixed $payload): float
    {
        $positions = is_array($payload) ? ($payload['positions'] ?? null) : null;

        if (! is_array($positions)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($positions as $position) {
            $total += (float) ($position['quantity'] ?? 0) * (float) ($position['price'] ?? 0);
        }

        return $total;
    }

    /**
     * Сумма акта приёма-передачи денег (payload.cost).
     */
    private static function costAmount(mixed $payload): float
    {
        return is_array($payload) ? (float) ($payload['cost'] ?? 0) : 0.0;
    }
}
