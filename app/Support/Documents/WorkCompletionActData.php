<?php

namespace App\Support\Documents;

use App\Enums\DocumentType;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Support\Passport;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Сбор данных акта выполненных работ (шаблон .docx works-act.docx).
 *
 * Подрядчик — организация пользователя (ФИО директора + паспорт из EAV) —
 * сдаёт работы. Заказчик — клиент объекта (ФИО + паспорт из EAV) — принимает.
 *
 * Позиции берутся из последней сохранённой Сметы объекта; каждая позиция
 * идентифицируется по её месту в смете (индекс раздела/строки). В мастер
 * попадают только позиции с ненулевым остатком: остаток = кол-во в смете
 * минус суммарно закрытый объём во всех ранее созданных актах этого объекта.
 */
class WorkCompletionActData
{
    private const MONTHS = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
        5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
        9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
    ];

    /** Аванс по умолчанию, % (шаблон: «за вычетом аванса 20%»). */
    public const DEFAULT_ADVANCE_PERCENT = 20.0;

    /** Порог, ниже которого остаток считается закрытым. */
    private const EPS = 1e-6;

    /**
     * @return array<string, mixed>
     */
    public static function forProject(Project $project, ?Organization $organization): array
    {
        $project->loadMissing(['client', 'city']);

        $date = Carbon::now();
        $orderNumber = (string) ($project->order_number ?? '');

        return [
            'number' => $orderNumber,
            'city' => $project->city?->name ?? '',
            'day' => sprintf('%02d', $date->day),
            'month' => self::MONTHS[$date->month],
            'year' => (string) $date->year,

            // Подрядчик (организация) — сдаёт работы.
            'contractor_surname' => $organization?->director_surname ?? '',
            'contractor_name' => $organization?->director_name ?? '',
            'contractor_father_name' => $organization?->director_father_name ?? '',
            ...self::passport('contractor_', $organization),
            'org_bank_name' => $organization?->bank_name ?? '',
            'org_card_number' => '',
            'org_phone' => $organization?->phone ?? '',
            'org_email' => $organization?->email ?? '',

            // Заказчик (клиент) — принимает работы.
            'customer_surname' => $project->client?->surname ?? '',
            'customer_name' => $project->client?->name ?? '',
            'customer_father_name' => $project->client?->father_name ?? '',
            ...self::passport('customer_', $project->client),
            'customer_phone' => $project->client?->phone ?? '',
            'customer_email' => $project->client?->email ?? '',

            // Объект (заказ).
            'order_number' => $orderNumber,
            'order_created_at' => $project->created_at?->format('d.m.Y') ?? '',
            'square' => $project->area !== null ? rtrim(rtrim((string) $project->area, '0'), '.') : '',
            'address' => $project->address ?? '',

            'advance_percent' => self::DEFAULT_ADVANCE_PERCENT,

            // Позиции сметы с остатком (для выбора и указания объёма в мастере).
            'available_positions' => self::availablePositions($project),
        ];
    }

    /**
     * Позиции последней сметы объекта с непокрытым остатком.
     *
     * @return array<int, array{key: string, number: int, group: string, name: string, unit: string, price: float, estimate_quantity: float, remaining: float}>
     */
    private static function availablePositions(Project $project): array
    {
        $estimate = $project->documents()
            ->where('type', DocumentType::Estimate->value)
            ->latest('id')
            ->first();

        if ($estimate === null || ! is_array($estimate->payload)) {
            return [];
        }

        $closed = self::closedQuantities($project);
        $positions = [];

        foreach ($estimate->payload['work_groups'] ?? [] as $gi => $group) {
            foreach ($group['items'] ?? [] as $ii => $item) {
                $key = self::positionKey((int) $gi, (int) $ii);
                $quantity = (float) ($item['quantity'] ?? 0);
                $remaining = $quantity - ($closed[$key] ?? 0.0);

                if ($remaining <= self::EPS) {
                    continue;
                }

                $positions[] = [
                    'key' => $key,
                    'number' => (int) $ii + 1,
                    'group' => (string) ($group['title'] ?? ''),
                    'name' => (string) ($item['name'] ?? ''),
                    'unit' => (string) ($item['unit'] ?? ''),
                    'price' => (float) ($item['price'] ?? 0),
                    'estimate_quantity' => $quantity,
                    'remaining' => round($remaining, 6),
                ];
            }
        }

        return $positions;
    }

    /**
     * Суммарно закрытый объём по каждой позиции во всех актах выполненных работ объекта.
     *
     * @return array<string, float>
     */
    private static function closedQuantities(Project $project): array
    {
        $closed = [];

        $acts = $project->documents()
            ->where('type', DocumentType::WorkCompletionAct->value)
            ->get();

        foreach ($acts as $act) {
            if (! is_array($act->payload)) {
                continue;
            }

            foreach ($act->payload['positions'] ?? [] as $position) {
                $key = (string) ($position['key'] ?? '');
                if ($key === '') {
                    continue;
                }

                $closed[$key] = ($closed[$key] ?? 0.0) + (float) ($position['quantity'] ?? 0);
            }
        }

        return $closed;
    }

    /**
     * Стабильный ключ позиции сметы по её месту (раздел/строка).
     */
    public static function positionKey(int $groupIndex, int $itemIndex): string
    {
        return "g{$groupIndex}-i{$itemIndex}";
    }

    /**
     * Паспортные данные сущности (организации/клиента) из EAV с префиксом ключей.
     *
     * @return array<string, string>
     */
    private static function passport(string $prefix, Organization|Client|null $entity): array
    {
        $get = fn (string $code) => $entity?->getEav($code);
        $issuedAt = $get(Passport::FIELDS['issued_at']['code']);

        return [
            $prefix.'passport_type' => (string) ($get(Passport::FIELDS['type']['code']) ?? ''),
            $prefix.'passport_series' => (string) ($get(Passport::FIELDS['series']['code']) ?? ''),
            $prefix.'passport_number' => (string) ($get(Passport::FIELDS['number']['code']) ?? ''),
            $prefix.'passport_issued_by' => (string) ($get(Passport::FIELDS['issued_by']['code']) ?? ''),
            $prefix.'passport_issued_at' => $issuedAt instanceof DateTimeInterface ? $issuedAt->format('d.m.Y') : (string) ($issuedAt ?? ''),
            $prefix.'passport_registration_address' => (string) ($get(Passport::EXTRA_FIELDS['registration_address']['code']) ?? ''),
        ];
    }
}
