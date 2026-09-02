<?php

namespace App\Support\Documents;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Генерация xlsx предварительной сметы (шаблон smeta-sample.xlsx).
 */
class PreliminaryEstimateWriter extends AbstractEstimateWriter
{
    protected function firstDataRow(): int
    {
        return 10;
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
            'section' => 'B10',
            'num' => 'A11', 'text' => 'B11', 'unit' => 'C11', 'qty' => 'D11', 'price' => 'E11', 'sum' => 'F11',
            'm_text' => 'G11', 'm_unit' => 'H11', 'm_qty' => 'I11', 'm_price' => 'J11', 'm_sum' => 'K11',
            'total_label' => 'A60', 'total_value' => 'K60',
            'sig' => 'A69', 'sig_name' => 'A70',
        ];
    }

    /**
     * @param  array<string, mixed>  $header
     */
    protected function fillHeader(Worksheet $sheet, array $header): void
    {
        $date = trim((string) ($header['date'] ?? ''));
        $title = trim((string) ($header['title'] ?? ''));

        $sheet->setCellValue('A2', trim($date."\n".$title));
        $sheet->setCellValue('A3', (string) ($header['object'] ?? ''));
        $sheet->setCellValue('A4', 'ЗАКАЗЧИК: '.($header['customer'] ?? ''));
        $sheet->setCellValue('A5', 'АДРЕС: '.($header['address'] ?? ''));
    }
}
