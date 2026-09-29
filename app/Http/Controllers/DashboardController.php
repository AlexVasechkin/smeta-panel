<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Services\MonthlyDocumentTotals;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const MONTHS = [
        1 => 'январь', 2 => 'февраль', 3 => 'март', 4 => 'апрель',
        5 => 'май', 6 => 'июнь', 7 => 'июль', 8 => 'август',
        9 => 'сентябрь', 10 => 'октябрь', 11 => 'ноябрь', 12 => 'декабрь',
    ];

    /** Панели помесячных показателей на дашборде: тип → заголовок и подпись. */
    private const PANELS = [
        [DocumentType::WorkCompletionAct, 'Закрыто работ', 'По актам выполненных работ'],
        [DocumentType::CashAcceptanceAct, 'Принято денежных средств', 'По актам приёма-передачи денег'],
    ];

    public function __invoke(MonthlyDocumentTotals $totals): Response
    {
        $now = Carbon::now();

        $items = array_map(fn (array $panel) => [
            'key' => $panel[0]->value,
            'title' => $panel[1],
            'hint' => $panel[2],
            'amount' => $totals->currentMonthTotal($panel[0]),
        ], self::PANELS);

        return Inertia::render('dashboard', [
            'monthlyTotals' => [
                'month_label' => self::MONTHS[$now->month].' '.$now->year,
                'items' => $items,
            ],
        ]);
    }
}
