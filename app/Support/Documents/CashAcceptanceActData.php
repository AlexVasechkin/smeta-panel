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
 * Сбор данных акта приёма-передачи денежных средств (плоский payload для
 * подстановки в шаблон .docx money-act.docx).
 *
 * Заказчик — клиент объекта (ФИО + паспорт из EAV) — передаёт средства.
 * Подрядчик — организация пользователя (ФИО директора + паспорт из EAV) —
 * принимает средства. Сумма по умолчанию берётся из сметы объекта.
 */
class CashAcceptanceActData
{
    private const MONTHS = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
        5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
        9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
    ];

    public const DEFAULT_PURPOSE = 'Оплата за ремонтно-отделочные работы';

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

            // Подрядчик (организация) — принимает средства.
            'contractor_surname' => $organization?->director_surname ?? '',
            'contractor_name' => $organization?->director_name ?? '',
            'contractor_father_name' => $organization?->director_father_name ?? '',
            ...self::passport('contractor_', $organization),
            'org_bank_name' => $organization?->bank_name ?? '',
            'org_card_number' => '',
            'org_phone' => $organization?->phone ?? '',
            'org_email' => $organization?->email ?? '',

            // Заказчик (клиент) — передаёт средства.
            'customer_surname' => $project->client?->surname ?? '',
            'customer_name' => $project->client?->name ?? '',
            'customer_father_name' => $project->client?->father_name ?? '',
            ...self::passport('customer_', $project->client),
            'customer_phone' => $project->client?->phone ?? '',
            'customer_email' => $project->client?->email ?? '',

            // Заказ (объект) и сумма.
            'order_number' => $orderNumber,
            'order_created_at' => $project->created_at?->format('d.m.Y') ?? '',
            'cost' => self::estimateTotal($project),
            'purpose' => self::DEFAULT_PURPOSE,
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
     * Итоговая сумма последней сметы объекта (или 0, если сметы ещё нет).
     */
    private static function estimateTotal(Project $project): float
    {
        $estimate = $project->documents()
            ->where('type', DocumentType::Estimate->value)
            ->latest('id')
            ->first();

        if ($estimate === null || ! is_array($estimate->payload)) {
            return 0.0;
        }

        return (new FinalEstimateWriter)->computeTotals($estimate->payload)['grand'];
    }
}
