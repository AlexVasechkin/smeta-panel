<?php

namespace App\Support\Documents;

use App\Support\RublesInWords;

/**
 * Генерация акта выполненных работ (.docx) подстановкой плейсхолдеров {{ ... }}
 * в шаблон works-act.docx.
 *
 * Ведомость работ ({{ document.positions }}) — таблица, поэтому строится как
 * «сырой» OOXML-блок (см. rawBlocks). Сумма {{ document.cost }} печатается
 * прописью: работы по акту за вычетом аванса (advance_percent).
 */
class WorkCompletionActWriter extends AbstractDocxWriter
{
    /** Ширины колонок таблицы позиций (в dxa). */
    private const COLS = [
        'num' => 640,     // №
        'name' => 4300,   // Наименование работ
        'unit' => 900,    // Ед. изм.
        'qty' => 1050,    // Кол-во
        'price' => 1300,  // Цена
        'sum' => 1470,    // Сумма
    ];

    /**
     * Карта нормализованный токен => значение.
     *
     * @param  array<string, mixed>  $p
     * @return array<string, string>
     */
    protected function map(array $p): array
    {
        $str = fn (string $key): string => (string) ($p[$key] ?? '');
        $initial = fn (string $key): string => mb_substr((string) ($p[$key] ?? ''), 0, 1, 'UTF-8');

        $cost = $this->costAfterAdvance($p);

        return [
            'document_number' => $str('number'),
            'project.city.name' => $str('city'),
            'current_date.day' => $str('day'),
            'current_date.month::human' => $str('month'),
            'current_date.year' => $str('year'),

            // Заказчик (клиент) — принимает работы.
            'project.client.surname' => $str('customer_surname'),
            'project.client.name' => $str('customer_name'),
            'project.client.father_name' => $str('customer_father_name'),
            'project.client.passport.type' => $str('customer_passport_type'),
            'project.client.passport.series' => $str('customer_passport_series'),
            'project.client.passport.number' => $str('customer_passport_number'),
            'project.client.passport.issued_by' => $str('customer_passport_issued_by'),
            'project.client.passport.issued_at' => $str('customer_passport_issued_at'),
            'project.client.passport.registration_address' => $str('customer_passport_registration_address'),

            // Подрядчик (организация) — сдаёт работы.
            'organization.director_surname' => $str('contractor_surname'),
            'organization.director_name' => $str('contractor_name'),
            'organization.director_father_name' => $str('contractor_father_name'),
            'organization.passport.type' => $str('contractor_passport_type'),
            'organization.passport.series' => $str('contractor_passport_series'),
            'organization.passport.number' => $str('contractor_passport_number'),
            'organization.passport.issued_by' => $str('contractor_passport_issued_by'),
            'organization.passport.issued_at' => $str('contractor_passport_issued_at'),
            'organization.passport.registration_address' => $str('contractor_passport_registration_address'),

            // Объект (заказ).
            'project.order_number' => $str('order_number'),
            'project.order.created_at' => $str('order_created_at'),
            'project.square' => $str('square'),
            'project.address' => $str('address'),

            // Сумма по акту (работы за вычетом аванса): цифрами (без ,00 и знака
            // рубля) и — в шаблоне с новой строки, в скобках — прописью.
            'document.cost' => preg_replace('/,00$/', '', $this->money($cost)),
            'document.cost_words' => $this->ucFirst(RublesInWords::format($cost)),

            // Реквизиты и подписи сторон. Подрядчик — та же организация.
            'user.organization.passport.type' => $str('contractor_passport_type'),
            'user.organization.passport.series' => $str('contractor_passport_series'),
            'user.organization.passport.number' => $str('contractor_passport_number'),
            'user.organization.passport.issued_by' => $str('contractor_passport_issued_by'),
            'user.organization.passport.issued_at' => $str('contractor_passport_issued_at'),
            'user.organization.passport.registration_address' => $str('contractor_passport_registration_address'),
            'user.organization.bank_name' => $str('org_bank_name'),
            'user.organization.card_name' => $str('org_card_number'),
            'user.organization.phone' => $str('org_phone'),
            'user.organization.email' => $str('org_email'),
            'user.organization.director_surname' => $str('contractor_surname'),
            'user.organization.director_name::substr(0, 1)' => $initial('contractor_name'),
            'user.organization.director_father_name::substr(0, 1)' => $initial('contractor_father_name'),

            // Заказчик — доп. поля реквизитов.
            'project.client.phone' => $str('customer_phone'),
            'project.client.email' => $str('customer_email'),
            'project.client.name::substr(0, 1)' => $initial('customer_name'),
            'project.client.father_name::substr(0, 1)' => $initial('customer_father_name'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    protected function rawBlocks(array $payload): array
    {
        return [
            'document.positions' => $this->positionsTable($this->positions($payload)),
        ];
    }

    /**
     * Итог по акту за вычетом аванса.
     *
     * @param  array<string, mixed>  $payload
     */
    public function costAfterAdvance(array $payload): float
    {
        $works = $this->worksTotal($this->positions($payload));
        $advance = (float) ($payload['advance_percent'] ?? 0);

        return round($works * (1 - $advance / 100), 2);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function positions(array $payload): array
    {
        $positions = $payload['positions'] ?? [];

        return is_array($positions) ? array_values($positions) : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $positions
     */
    private function worksTotal(array $positions): float
    {
        $total = 0.0;

        foreach ($positions as $position) {
            $total += (float) ($position['quantity'] ?? 0) * (float) ($position['price'] ?? 0);
        }

        return $total;
    }

    /**
     * Построить OOXML-таблицу ведомости выполненных работ.
     *
     * @param  array<int, array<string, mixed>>  $positions
     */
    private function positionsTable(array $positions): string
    {
        $rows = $this->headerRow();

        // Группировка по разделу с сохранением порядка появления.
        $grouped = [];
        foreach ($positions as $position) {
            $grouped[(string) ($position['group'] ?? '')][] = $position;
        }

        $grandTotal = 0.0;

        foreach ($grouped as $title => $items) {
            if ($title !== '') {
                $rows .= $this->sectionRow($title);
            }

            $fallback = 1;
            $sectionTotal = 0.0;

            foreach ($items as $item) {
                $qty = (float) ($item['quantity'] ?? 0);
                $price = (float) ($item['price'] ?? 0);
                $sum = $qty * $price;
                $sectionTotal += $sum;

                $rows .= $this->itemRow(
                    $this->positionNumber($item, $fallback),
                    (string) ($item['name'] ?? ''),
                    (string) ($item['unit'] ?? ''),
                    $this->number($qty),
                    $this->money($price),
                    $this->money($sum),
                );

                $fallback++;
            }

            $grandTotal += $sectionTotal;
            $rows .= $this->totalRow('Итого по разделу:', $this->money($sectionTotal));
        }

        $rows .= $this->totalRow('Всего:', $this->money($grandTotal));

        return '<w:tbl>'.$this->tableProperties().$rows.'</w:tbl>';
    }

    private function tableProperties(): string
    {
        $border = '<w:top w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
            .'<w:left w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
            .'<w:bottom w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
            .'<w:right w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
            .'<w:insideH w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
            .'<w:insideV w:val="single" w:sz="4" w:space="0" w:color="000000"/>';

        $grid = '';
        foreach (self::COLS as $width) {
            $grid .= '<w:gridCol w:w="'.$width.'"/>';
        }

        return '<w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'.$border.'</w:tblBorders>'
            .'<w:tblLook w:val="04A0" w:firstRow="1" w:lastRow="0" w:firstColumn="1" w:lastColumn="0" w:noHBand="0" w:noVBand="1"/>'
            .'</w:tblPr><w:tblGrid>'.$grid.'</w:tblGrid>';
    }

    private function headerRow(): string
    {
        $cells = $this->cell(self::COLS['num'], '№', bold: true, align: 'center')
            .$this->cell(self::COLS['name'], 'Наименование работ', bold: true, align: 'center')
            .$this->cell(self::COLS['unit'], 'Ед. изм.', bold: true, align: 'center')
            .$this->cell(self::COLS['qty'], 'Кол-во', bold: true, align: 'center')
            .$this->cell(self::COLS['price'], 'Цена', bold: true, align: 'center')
            .$this->cell(self::COLS['sum'], 'Сумма', bold: true, align: 'center');

        return '<w:tr>'.$cells.'</w:tr>';
    }

    private function sectionRow(string $title): string
    {
        $width = array_sum(self::COLS);

        return '<w:tr>'.$this->cell($width, $title, bold: true, align: 'left', span: count(self::COLS)).'</w:tr>';
    }

    private function itemRow(string $num, string $name, string $unit, string $qty, string $price, string $sum): string
    {
        $cells = $this->cell(self::COLS['num'], $num, align: 'center')
            .$this->cell(self::COLS['name'], $name, align: 'left')
            .$this->cell(self::COLS['unit'], $unit, align: 'center')
            .$this->cell(self::COLS['qty'], $qty, align: 'right')
            .$this->cell(self::COLS['price'], $price, align: 'right')
            .$this->cell(self::COLS['sum'], $sum, align: 'right');

        return '<w:tr>'.$cells.'</w:tr>';
    }

    private function totalRow(string $label, string $sum): string
    {
        $labelWidth = self::COLS['num'] + self::COLS['name'] + self::COLS['unit'] + self::COLS['qty'] + self::COLS['price'];

        $cells = $this->cell($labelWidth, $label, bold: true, align: 'right', span: 5)
            .$this->cell(self::COLS['sum'], $sum, bold: true, align: 'right');

        return '<w:tr>'.$cells.'</w:tr>';
    }

    /**
     * Ячейка таблицы (с опциональным объединением по горизонтали).
     */
    private function cell(int $width, string $text, bool $bold = false, string $align = 'left', int $span = 1): string
    {
        $tcPr = '<w:tcW w:w="'.$width.'" w:type="dxa"/>';
        if ($span > 1) {
            $tcPr .= '<w:gridSpan w:val="'.$span.'"/>';
        }
        $tcPr .= '<w:vAlign w:val="center"/>';

        $rPr = '<w:rPr><w:sz w:val="20"/><w:szCs w:val="20"/>'.($bold ? '<w:b/><w:bCs/>' : '').'</w:rPr>';
        // Сброс отступов из стиля по умолчанию (в шаблоне у Normal — левый/висячий
        // отступ), иначе текст в ячейках уезжает и некрасиво переносится.
        $pPr = '<w:pPr><w:spacing w:before="0" w:after="0" w:line="240" w:lineRule="auto"/>'
            .'<w:ind w:left="0" w:right="0" w:firstLine="0" w:hanging="0"/>'
            .'<w:jc w:val="'.$align.'"/>'.$rPr.'</w:pPr>';
        $run = '<w:r>'.$rPr.'<w:t xml:space="preserve">'.$this->escape($text).'</w:t></w:r>';

        return '<w:tc><w:tcPr>'.$tcPr.'</w:tcPr><w:p>'.$pPr.$run.'</w:p></w:tc>';
    }

    /**
     * Номер позиции = её порядковый номер в смете (из ключа g{раздел}-i{строка}).
     * При отсутствии/нераспознанном ключе — последовательный номер в акте.
     *
     * @param  array<string, mixed>  $item
     */
    private function positionNumber(array $item, int $fallback): string
    {
        if (preg_match('/i(\d+)$/', (string) ($item['key'] ?? ''), $m)) {
            return (string) ((int) $m[1] + 1);
        }

        return (string) $fallback;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, ',', ' ');
    }

    private function ucFirst(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($text, 1, null, 'UTF-8');
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ' '), '0'), '.');
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
