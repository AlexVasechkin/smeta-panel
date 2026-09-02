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
 * Сбор данных договора подряда (плоский payload для подстановки в шаблон .docx).
 *
 * Подрядчик — организация пользователя (ФИО директора + его паспорт из EAV),
 * Заказчик — клиент объекта (ФИО + паспорт из EAV). Суммы берутся из сметы
 * объекта (документ типа «Смета»).
 */
class ContractData
{
    private const MONTHS = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
        5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
        9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function forProject(Project $project, ?Organization $organization): array
    {
        $project->loadMissing(['client', 'city']);

        $date = Carbon::now();
        $totals = self::estimateTotals($project);

        return [
            'number' => (string) ($project->order_number ?? ''),
            'city' => $project->city?->name ?? '',
            'day' => sprintf('%02d', $date->day),
            'month' => self::MONTHS[$date->month],
            'year' => (string) $date->year,

            // Подрядчик (организация).
            'contractor_surname' => $organization?->director_surname ?? '',
            'contractor_name' => $organization?->director_name ?? '',
            'contractor_father_name' => $organization?->director_father_name ?? '',
            ...self::passport('contractor_', $organization),
            // Реквизиты подрядчика (раздел 16).
            'org_bank_name' => $organization?->bank_name ?? '',
            'org_card_number' => '',
            'org_phone' => $organization?->phone ?? '',
            'org_email' => $organization?->email ?? '',

            // Заказчик (клиент).
            'customer_surname' => $project->client?->surname ?? '',
            'customer_name' => $project->client?->name ?? '',
            'customer_father_name' => $project->client?->father_name ?? '',
            ...self::passport('customer_', $project->client),
            'customer_phone' => $project->client?->phone ?? '',
            'customer_email' => $project->client?->email ?? '',

            'area' => $project->area !== null ? rtrim(rtrim((string) $project->area, '0'), '.') : '',
            'address' => $project->address ?? '',

            // Суммы из сметы объекта.
            'works_cost' => $totals['works'],
            'materials_cost' => $totals['materials'],
            'total_cost' => $totals['grand'],
            'extras_cost' => $totals['extras'],
        ];
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

    /**
     * Итоги последней сметы объекта (или нули, если сметы ещё нет).
     *
     * @return array<string, float>
     */
    private static function estimateTotals(Project $project): array
    {
        $estimate = $project->documents()
            ->where('type', DocumentType::Estimate->value)
            ->latest('id')
            ->first();

        if ($estimate === null || ! is_array($estimate->payload)) {
            return ['works' => 0.0, 'materials' => 0.0, 'extras' => 0.0, 'grand' => 0.0];
        }

        return (new FinalEstimateWriter)->computeTotals($estimate->payload);
    }
}
