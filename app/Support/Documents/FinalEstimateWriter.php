<?php

namespace App\Support\Documents;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Генерация xlsx сметы (шаблон smeta-final.xlsx).
 *
 * Отличается от предварительной сметы смещением тела вниз (у шаблона есть
 * строки «Приложение к договору» и «Договор №…») и раскладкой шапки.
 */
class FinalEstimateWriter extends AbstractEstimateWriter
{
    protected function firstDataRow(): int
    {
        return 12;
    }

    protected function bodyRowsToRemove(): int
    {
        return 61;
    }

    /**
     * @return array<string, string>
     */
    protected function styleCells(): array
    {
        return [
            'section' => 'B12',
            'num' => 'A13', 'text' => 'B13', 'unit' => 'C13', 'qty' => 'D13', 'price' => 'E13', 'sum' => 'F13',
            'm_text' => 'G13', 'm_unit' => 'H13', 'm_qty' => 'I13', 'm_price' => 'J13', 'm_sum' => 'K13',
            'total_label' => 'A62', 'total_value' => 'K62',
            'sig' => 'A71', 'sig_name' => 'A72',
        ];
    }

    /**
     * @param  array<string, mixed>  $header
     */
    protected function fillHeader(Worksheet $sheet, array $header): void
    {
        $date = trim((string) ($header['date'] ?? ''));
        $title = trim((string) ($header['title'] ?? ''));

        // A2 (Приложение к договору) и A6 (Договор №…) остаются из шаблона.
        $sheet->setCellValue('A3', trim($date."\n".$title));
        $sheet->setCellValue('C4', (string) ($header['object'] ?? ''));
        $sheet->setCellValue('A5', 'ЗАКАЗЧИК: '.($header['customer'] ?? ''));
        $sheet->setCellValue('A7', 'АДРЕС: '.($header['address'] ?? ''));
    }
}
