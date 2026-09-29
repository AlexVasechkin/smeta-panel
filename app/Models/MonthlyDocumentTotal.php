<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;

/**
 * Помесячный агрегат суммы по документам одного типа (акты выполненных работ,
 * акты приёма-передачи денег и т.п.). Строка на пару (type, period), где period —
 * первый день месяца. Значение денормализовано и пересчитывается при создании/
 * удалении документа (см. App\Observers\DocumentObserver).
 */
class MonthlyDocumentTotal extends Model
{
    protected $fillable = [
        'type',
        'period',
        'total',
    ];

    protected function casts(): array
    {
        // period намеренно без date-cast: храним/ищем как «Y-m-d» (первый день
        // месяца), чтобы ключ updateOrCreate совпадал со значением в столбце.
        return [
            'type' => DocumentType::class,
            'total' => 'decimal:2',
        ];
    }
}
