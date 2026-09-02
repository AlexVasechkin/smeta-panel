<?php

namespace App\Support\Documents;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Общая генерация xlsx сметы на основе образца-шаблона.
 *
 * Шапка и стили берутся из шаблона, тело (работы/материалы/итоги)
 * перестраивается динамически по данным payload; суммы считаются в PHP.
 * Наследники задают привязку к конкретному шаблону: строки тела, эталонные
 * ячейки стилей и раскладку шапки.
 */
abstract class AbstractEstimateWriter
{
    /** Первая строка тела (заголовок первого раздела работ). */
    abstract protected function firstDataRow(): int;

    /** Сколько строк тела шаблона удалить (тело до подписей включительно). */
    abstract protected function bodyRowsToRemove(): int;

    /**
     * Эталонные ячейки шаблона для стилей: ключ => адрес ячейки.
     *
     * @return array<string, string>
     */
    abstract protected function styleCells(): array;

    /**
     * Заполнить шапку документа (раскладка зависит от шаблона).
     *
     * @param  array<string, mixed>  $header
     */
    abstract protected function fillHeader(Worksheet $sheet, array $header): void;

    /**
     * Сгенерировать документ: прочитать шаблон, заполнить, сохранить в $outAbsPath.
     *
     * @param  array<string, mixed>  $payload
     */
    public function write(array $payload, string $templateAbsPath, string $outAbsPath): void
    {
        $spreadsheet = IOFactory::load($templateAbsPath);
        $sheet = $spreadsheet->getActiveSheet();

        // Стили эталонных ячеек шаблона (до удаления тела).
        $styles = [];
        foreach ($this->styleCells() as $key => $cell) {
            $styles[$key] = $sheet->getStyle($cell)->exportArray();
        }

        $header = $payload['header'] ?? [];
        $this->fillHeader($sheet, $header);

        // Удалить тело шаблона, сохранив шапку и заголовки столбцов.
        $sheet->removeRow($this->firstDataRow(), $this->bodyRowsToRemove());

        $worksBottom = $this->writeWorks($sheet, $payload['work_groups'] ?? [], $styles);
        $materialsBottom = $this->writeMaterials($sheet, $payload['materials'] ?? [], $styles);

        $totals = $this->computeTotals($payload);

        $row = max($worksBottom, $materialsBottom) + 2;
        $row = $this->writeTotals($sheet, $row, $payload, $totals, $styles);
        $this->writeSignatures($sheet, $row + 2, $header, $styles);

        $writer = new Xlsx($spreadsheet);
        $writer->save($outAbsPath);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * @param  array<int, array{title?: string, items?: array<int, array<string, mixed>>}>  $groups
     * @param  array<string, array<mixed>>  $styles
     */
    private function writeWorks(Worksheet $sheet, array $groups, array $styles): int
    {
        $row = $this->firstDataRow();

        foreach ($groups as $group) {
            $sheet->setCellValue("B{$row}", (string) ($group['title'] ?? ''));
            $sheet->getStyle("B{$row}")->applyFromArray($styles['section']);
            $row++;

            $number = 1;
            $groupTotal = 0.0;

            foreach ($group['items'] ?? [] as $item) {
                $qty = (float) ($item['quantity'] ?? 0);
                $price = (float) ($item['price'] ?? 0);
                $sum = $qty * $price;
                $groupTotal += $sum;

                $sheet->setCellValue("A{$row}", $number);
                $sheet->setCellValue("B{$row}", (string) ($item['name'] ?? ''));
                $sheet->setCellValue("C{$row}", (string) ($item['unit'] ?? ''));
                $sheet->setCellValue("D{$row}", $qty);
                $sheet->setCellValue("E{$row}", $price);
                $sheet->setCellValue("F{$row}", $sum);

                $sheet->getStyle("A{$row}")->applyFromArray($styles['num']);
                $sheet->getStyle("B{$row}")->applyFromArray($styles['text']);
                $sheet->getStyle("C{$row}")->applyFromArray($styles['unit']);
                $sheet->getStyle("D{$row}")->applyFromArray($styles['qty']);
                $sheet->getStyle("E{$row}")->applyFromArray($styles['price']);
                $sheet->getStyle("F{$row}")->applyFromArray($styles['sum']);

                $number++;
                $row++;
            }

            $sheet->setCellValue("B{$row}", 'Итог');
            $sheet->setCellValue("F{$row}", $groupTotal);
            $sheet->getStyle("B{$row}")->applyFromArray($styles['section']);
            $sheet->getStyle("F{$row}")->applyFromArray($styles['sum']);
            $sheet->getStyle("B{$row}:F{$row}")->getFont()->setBold(true);
            $row++;
        }

        return $row - 1;
    }

    /**
     * @param  array<int, array<string, mixed>>  $materials
     * @param  array<string, array<mixed>>  $styles
     */
    private function writeMaterials(Worksheet $sheet, array $materials, array $styles): int
    {
        $row = $this->firstDataRow();
        $total = 0.0;

        foreach ($materials as $item) {
            $qty = (float) ($item['quantity'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            $sum = $qty * $price;
            $total += $sum;

            $sheet->setCellValue("G{$row}", (string) ($item['name'] ?? ''));
            $sheet->setCellValue("H{$row}", (string) ($item['unit'] ?? ''));
            $sheet->setCellValue("I{$row}", $qty);
            $sheet->setCellValue("J{$row}", $price);
            $sheet->setCellValue("K{$row}", $sum);

            $sheet->getStyle("G{$row}")->applyFromArray($styles['m_text']);
            $sheet->getStyle("H{$row}")->applyFromArray($styles['m_unit']);
            $sheet->getStyle("I{$row}")->applyFromArray($styles['m_qty']);
            $sheet->getStyle("J{$row}")->applyFromArray($styles['m_price']);
            $sheet->getStyle("K{$row}")->applyFromArray($styles['m_sum']);

            $row++;
        }

        $sheet->setCellValue("G{$row}", 'Итог');
        $sheet->setCellValue("K{$row}", $total);
        $sheet->getStyle("G{$row}")->applyFromArray($styles['section']);
        $sheet->getStyle("K{$row}")->applyFromArray($styles['m_sum']);
        $sheet->getStyle("G{$row}:K{$row}")->getFont()->setBold(true);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, float>  $totals
     * @param  array<string, array<mixed>>  $styles
     */
    private function writeTotals(Worksheet $sheet, int $row, array $payload, array $totals, array $styles): int
    {
        $discount = (float) ($payload['discount_percent'] ?? 0);

        $lines = [
            ['Итого по смете всех работ:', $totals['works']],
            [sprintf('Итого по смете всех работ с учетом скидки %s%%:', rtrim(rtrim(number_format($discount, 2, '.', ''), '0'), '.')), $totals['works_discounted']],
            ['Итого по смете подготовительных материалов:', $totals['materials']],
        ];

        foreach ($payload['extras'] ?? [] as $extra) {
            $lines[] = [(string) ($extra['label'] ?? ''), (float) ($extra['amount'] ?? 0)];
        }

        $lines[] = ['Всего:', $totals['grand']];

        foreach ($lines as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->setCellValue("K{$row}", $value);
            $sheet->getStyle("A{$row}")->applyFromArray($styles['total_label']);
            $sheet->getStyle("K{$row}")->applyFromArray($styles['total_value']);
            $row++;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array<string, array<mixed>>  $styles
     */
    private function writeSignatures(Worksheet $sheet, int $row, array $header, array $styles): void
    {
        $sheet->setCellValue("A{$row}", 'Подрядчик:');
        $sheet->setCellValue("G{$row}", 'Заказчик:');
        $sheet->getStyle("A{$row}")->applyFromArray($styles['sig']);
        $sheet->getStyle("G{$row}")->applyFromArray($styles['sig']);
        $row++;

        $sheet->setCellValue("A{$row}", (string) ($header['contractor_signatory'] ?? ''));
        $sheet->setCellValue("G{$row}", (string) ($header['customer_signatory'] ?? ''));
        $sheet->getStyle("A{$row}")->applyFromArray($styles['sig_name']);
        $sheet->getStyle("G{$row}")->applyFromArray($styles['sig_name']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, float>
     */
    public function computeTotals(array $payload): array
    {
        $works = 0.0;
        foreach ($payload['work_groups'] ?? [] as $group) {
            foreach ($group['items'] ?? [] as $item) {
                $works += (float) ($item['quantity'] ?? 0) * (float) ($item['price'] ?? 0);
            }
        }

        $materials = 0.0;
        foreach ($payload['materials'] ?? [] as $item) {
            $materials += (float) ($item['quantity'] ?? 0) * (float) ($item['price'] ?? 0);
        }

        $discount = (float) ($payload['discount_percent'] ?? 0);
        $worksDiscounted = $works * (1 - $discount / 100);

        $extras = 0.0;
        foreach ($payload['extras'] ?? [] as $extra) {
            $extras += (float) ($extra['amount'] ?? 0);
        }

        return [
            'works' => $works,
            'works_discounted' => $worksDiscounted,
            'materials' => $materials,
            'extras' => $extras,
            'grand' => $worksDiscounted + $materials + $extras,
        ];
    }
}
