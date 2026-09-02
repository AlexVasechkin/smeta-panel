<?php

namespace App\Support\Documents;

use App\Support\RublesInWords;

/**
 * Генерация акта приёма-передачи денежных средств (.docx) подстановкой
 * плейсхолдеров вида {{ ... }} в шаблон (money-act.docx).
 */
class CashAcceptanceActWriter extends AbstractDocxWriter
{
    /**
     * Карта нормализованный токен => значение.
     *
     * @param  array<string, mixed>  $p
     * @return array<string, string>
     */
    protected function map(array $p): array
    {
        $cost = (float) ($p['cost'] ?? 0);

        $money = fn (float $v): string => number_format($v, 2, ',', ' ');
        $words = fn (float $v): string => RublesInWords::words($v);
        $str = fn (string $key): string => (string) ($p[$key] ?? '');
        $initial = fn (string $key): string => mb_substr((string) ($p[$key] ?? ''), 0, 1, 'UTF-8');

        return [
            'document_number' => $str('number'),
            'project.city.name' => $str('city'),
            'current_date.day' => $str('day'),
            'current_date.month::human' => $str('month'),
            'current_date.year' => $str('year'),

            // Заказчик (клиент) — передаёт денежные средства.
            'project.client.surname' => $str('customer_surname'),
            'project.client.name' => $str('customer_name'),
            'project.client.father_name' => $str('customer_father_name'),
            'project.client.passport.type' => $str('customer_passport_type'),
            'project.client.passport.series' => $str('customer_passport_series'),
            'project.client.passport.number' => $str('customer_passport_number'),
            'project.client.passport.issued_by' => $str('customer_passport_issued_by'),
            'project.client.passport.issued_at' => $str('customer_passport_issued_at'),
            'project.client.passport.registration_address' => $str('customer_passport_registration_address'),

            // Подрядчик (организация) — принимает денежные средства.
            'organization.director_surname' => $str('contractor_surname'),
            'organization.director_name' => $str('contractor_name'),
            'organization.director_father_name' => $str('contractor_father_name'),
            'organization.passport.type' => $str('contractor_passport_type'),
            'organization.passport.series' => $str('contractor_passport_series'),
            'organization.passport.number' => $str('contractor_passport_number'),
            'organization.passport.issued_by' => $str('contractor_passport_issued_by'),
            'organization.passport.issued_at' => $str('contractor_passport_issued_at'),
            'organization.passport.registration_address' => $str('contractor_passport_registration_address'),

            'project.order_number' => $str('order_number'),
            'project.order.created_at' => $str('order_created_at'),

            // Сумма (число и прописью) и назначение платежа.
            'document.cost' => $money($cost),
            'document.cost::human' => $words($cost),
            'document.purpose' => $str('purpose'),

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
}
