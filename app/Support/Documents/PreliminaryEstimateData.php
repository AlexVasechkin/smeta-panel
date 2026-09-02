<?php

namespace App\Support\Documents;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Сборка данных предварительной сметы: значения по умолчанию из объекта
 * (и его сметы), либо разбор образца-шаблона как запасной вариант.
 *
 * Структура payload:
 * [
 *   'header' => ['date','title','object','customer','address','contractor_signatory','customer_signatory'],
 *   'work_groups' => [['title' => string, 'items' => [['name','unit','quantity','price'], ...]], ...],
 *   'materials' => [['name','unit','quantity','price'], ...],
 *   'extras' => [['label' => string, 'amount' => float], ...],
 *   'discount_percent' => float,
 * ]
 */
class PreliminaryEstimateData
{
    private const MONTHS = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
        5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
        9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
    ];

    /** Фиксированные дополнительные статьи расходов (значения по умолчанию). */
    public const DEFAULT_EXTRAS = [
        ['label' => 'Расходные и крепежные материалы', 'amount' => 50000],
        ['label' => 'Транспортные расходы и подбор материалов', 'amount' => 45000],
        ['label' => 'Погрузочно-разгрузочные работы (материалов по смете)', 'amount' => 35000],
        ['label' => 'Вынос строительного мусора', 'amount' => 20000],
    ];

    public const DEFAULT_TITLE = 'Смета на ремонтно-отделочные работы и подготовительные материалы.';

    public const DEFAULT_DISCOUNT = 5;

    /**
     * Построить payload по умолчанию для объекта.
     *
     * @return array<string, mixed>
     */
    public static function forProject(Project $project, ?Organization $organization, string $templateAbsPath): array
    {
        $project->loadMissing(['client', 'city', 'estimate.workItems', 'estimate.materialItems']);

        [$workGroups, $materials] = self::positions($project, $templateAbsPath);

        return [
            'header' => [
                'date' => self::formatDate(Carbon::now()),
                'title' => self::DEFAULT_TITLE,
                'object' => self::objectDescription($project),
                'customer' => self::clientShortName($project->client),
                'address' => self::fullAddress($project),
                'contractor_signatory' => self::directorShortName($organization) ?: ($organization?->name ?? ''),
                'customer_signatory' => self::clientShortName($project->client),
            ],
            'work_groups' => $workGroups,
            'materials' => $materials,
            'extras' => self::DEFAULT_EXTRAS,
            'discount_percent' => self::DEFAULT_DISCOUNT,
        ];
    }

    /**
     * Позиции: из сметы объекта, либо из образца-шаблона, если смета пуста.
     *
     * @return array{0: array<int, array{title: string, items: array<int, array<string, mixed>>}>, 1: array<int, array<string, mixed>>}
     */
    private static function positions(Project $project, string $templateAbsPath): array
    {
        $estimate = $project->estimate;

        $hasItems = $estimate
            && ($estimate->workItems->isNotEmpty() || $estimate->materialItems->isNotEmpty());

        if (! $hasItems) {
            return self::parseTemplate($templateAbsPath);
        }

        $workGroups = $estimate->workItems
            ->groupBy(fn ($item) => $item->category ?: 'Работы')
            ->map(fn ($items, $title) => [
                'title' => (string) $title,
                'items' => $items->map(fn ($item) => [
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'quantity' => (float) $item->quantity,
                    'price' => (float) $item->price,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        $materials = $estimate->materialItems->map(fn ($item) => [
            'name' => $item->name,
            'unit' => $item->unit,
            'quantity' => (float) $item->quantity,
            'price' => (float) $item->price,
        ])->values()->all();

        return [$workGroups, $materials];
    }

    /**
     * Разобрать позиции из образца-шаблона (xlsx).
     *
     * @return array{0: array<int, array{title: string, items: array<int, array<string, mixed>>}>, 1: array<int, array<string, mixed>>}
     */
    public static function parseTemplate(string $absPath): array
    {
        $sheet = IOFactory::load($absPath)->getActiveSheet();

        $workGroups = [];
        $current = null;

        // Работы: столбцы A(№) B(наименование) C(ед.изм) D(объём) E(цена). Строки 10..57.
        for ($row = 10; $row <= 57; $row++) {
            $name = trim((string) $sheet->getCell("B{$row}")->getValue());
            if ($name === '' || $name === 'Итог') {
                continue;
            }

            $number = $sheet->getCell("A{$row}")->getValue();
            $unit = trim((string) $sheet->getCell("C{$row}")->getValue());

            // Заголовок раздела: есть название, но нет номера и единицы измерения.
            if (($number === null || $number === '') && $unit === '') {
                $current = ['title' => $name, 'items' => []];
                $workGroups[] = &$current;

                continue;
            }

            if ($current === null) {
                $current = ['title' => 'Работы', 'items' => []];
                $workGroups[] = &$current;
            }

            $current['items'][] = [
                'name' => $name,
                'unit' => $unit,
                'quantity' => (float) $sheet->getCell("D{$row}")->getValue(),
                'price' => (float) $sheet->getCell("E{$row}")->getValue(),
            ];
        }
        unset($current);

        $materials = [];
        for ($row = 11; $row <= 57; $row++) {
            $name = trim((string) $sheet->getCell("G{$row}")->getValue());
            if ($name === '' || $name === 'Итог') {
                continue;
            }

            $materials[] = [
                'name' => $name,
                'unit' => trim((string) $sheet->getCell("H{$row}")->getValue()),
                'quantity' => (float) $sheet->getCell("I{$row}")->getValue(),
                'price' => (float) $sheet->getCell("J{$row}")->getValue(),
            ];
        }

        return [$workGroups, $materials];
    }

    private static function objectDescription(Project $project): string
    {
        $parts = [];

        if ($project->area !== null) {
            $parts[] = rtrim(rtrim((string) $project->area, '0'), '.').' кв.м';
        }

        return trim(implode(', ', array_filter($parts)));
    }

    /**
     * Подписант-подрядчик: руководитель организации в формате «Фамилия И. О.».
     */
    private static function directorShortName(?Organization $organization): string
    {
        return self::shortName(
            $organization?->director_surname,
            $organization?->director_name,
            $organization?->director_father_name,
        );
    }

    /**
     * Подписант-заказчик: клиент в формате «Фамилия И. О.».
     */
    private static function clientShortName(?Client $client): string
    {
        return self::shortName(
            $client?->surname,
            $client?->name,
            $client?->father_name,
        );
    }

    /**
     * ФИО в формате «Фамилия И. О.» (имя и отчество — инициалами).
     * Отсутствующие части опускаются.
     */
    private static function shortName(?string $surname, ?string $name, ?string $fatherName): string
    {
        $surname = trim((string) $surname);

        $initials = [];
        foreach ([$name, $fatherName] as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $initials[] = mb_strtoupper(mb_substr($part, 0, 1)).'.';
            }
        }

        return trim($surname.' '.implode(' ', $initials));
    }

    /**
     * Полный адрес объекта: город (из Project.city) + адрес.
     */
    private static function fullAddress(Project $project): string
    {
        $address = trim((string) ($project->address ?? ''));
        $city = $project->city?->name;

        if ($city === null || $city === '') {
            return $address;
        }

        return trim('г. '.$city.($address !== '' ? ', '.$address : ''));
    }

    public static function formatDate(Carbon $date): string
    {
        return sprintf('«%02d» %s %d', $date->day, self::MONTHS[$date->month], $date->year);
    }
}
