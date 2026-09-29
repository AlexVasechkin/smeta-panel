<?php

namespace App\Console\Commands;

use App\Enums\DocumentType;
use App\Services\MonthlyDocumentTotals;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Пересчитывает помесячный агрегат сумм по документам отслеживаемых типов
 * (акты выполненных работ, акты приёма-передачи денег) за каждый месяц года.
 */
class RecalculateMonthlyDocumentTotals extends Command
{
    protected $signature = 'document-totals:recalculate
        {--year= : Год для пересчёта (по умолчанию текущий)}
        {--type= : Значение DocumentType для пересчёта только одного типа}';

    protected $description = 'Пересчитать помесячные суммы по актам (выполненных работ и приёма-передачи денег) за год';

    private const MONTHS = [
        1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
        5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
        9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
    ];

    public function handle(MonthlyDocumentTotals $totals): int
    {
        $year = (int) ($this->option('year') ?: Carbon::now()->year);

        $types = $this->resolveTypes();
        if ($types === null) {
            return self::FAILURE;
        }

        $this->info("Пересчёт помесячных сумм за {$year} год…");

        foreach ($types as $type) {
            $byMonth = $totals->recalculateYear($type, $year);

            $this->newLine();
            $this->line("<comment>{$type->label()}</comment>");
            $this->table(
                ['Месяц', 'Сумма, ₽'],
                array_map(
                    fn (int $month, float $sum) => [self::MONTHS[$month], number_format($sum, 2, ',', ' ')],
                    array_keys($byMonth),
                    $byMonth,
                ),
            );
            $this->line('Итого за год: '.number_format(array_sum($byMonth), 2, ',', ' ').' ₽');
        }

        return self::SUCCESS;
    }

    /**
     * Типы для пересчёта: один (--type) или все отслеживаемые.
     *
     * @return array<int, DocumentType>|null null — если указан неизвестный тип
     */
    private function resolveTypes(): ?array
    {
        $option = $this->option('type');

        if ($option === null) {
            return MonthlyDocumentTotals::TRACKED_TYPES;
        }

        $type = DocumentType::tryFrom($option);

        if ($type === null || ! in_array($type, MonthlyDocumentTotals::TRACKED_TYPES, true)) {
            $this->error("Неизвестный или неотслеживаемый тип: {$option}");

            return null;
        }

        return [$type];
    }
}
