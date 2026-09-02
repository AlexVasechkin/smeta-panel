<?php

namespace App\Support\Documents;

use App\Support\RublesInWords;

/**
 * Генерация договора (.docx) подстановкой плейсхолдеров вида {{ ... }} в шаблон
 * (order-sample.docx).
 */
class ContractWriter extends AbstractDocxWriter
{
    /**
     * Карта нормализованный токен => значение.
     *
     * @param  array<string, mixed>  $p
     * @return array<string, string>
     */
    protected function map(array $p): array
    {
        $works = (float) ($p['works_cost'] ?? 0);
        $materials = (float) ($p['materials_cost'] ?? 0);
        $total = (float) ($p['total_cost'] ?? 0);
        $extras = (float) ($p['extras_cost'] ?? 0);

        // Число в формате «135 268,00» и целая часть прописью (шаблон сам
        // добавляет «рублей 00 копеек» после скобок с прописью).
        $money = fn (float $v): string => number_format($v, 2, ',', ' ');
        $words = fn (float $v): string => RublesInWords::words($v);
        $str = fn (string $key): string => (string) ($p[$key] ?? '');
        $initial = fn (string $key): string => mb_substr((string) ($p[$key] ?? ''), 0, 1, 'UTF-8');

        return [
            'project.document.name' => $str('number'),
            'project.city.name' => $str('city'),
            'day' => $str('day'),
            'month::human' => $str('month'),
            'year' => $str('year'),

            'organization.director_surname' => $str('contractor_surname'),
            'organization.director_name' => $str('contractor_name'),
            'organization.director_father_name' => $str('contractor_father_name'),
            'organization.passport.type' => $str('contractor_passport_type'),
            'organization.passport.series' => $str('contractor_passport_series'),
            'organization.passport.number' => $str('contractor_passport_number'),
            'organization.passport.issued_by' => $str('contractor_passport_issued_by'),
            'organization.passport.issued_at' => $str('contractor_passport_issued_at'),
            'organization.passport.registration_address' => $str('contractor_passport_registration_address'),

            'project.client.surname' => $str('customer_surname'),
            'project.client.name' => $str('customer_name'),
            'project.client.father_name' => $str('customer_father_name'),
            'project.client.passport.type' => $str('customer_passport_type'),
            'project.client.passport.series' => $str('customer_passport_series'),
            'project.client.passport.number' => $str('customer_passport_number'),
            'project.client.passport.issued_by' => $str('customer_passport_issued_by'),
            'project.client.passport.issued_at' => $str('customer_passport_issued_at'),
            'project.client.passport.registration_address' => $str('customer_passport_registration_address'),

            'project.area' => $str('area'),
            'project.address' => $str('address'),

            // Суммы из сметы (число и прописью). В шаблоне встречается опечатка «meterials».
            'project.document[type="smeta"].total_cost' => $money($total),
            'project.document[type="smeta"].total_cost::human' => $words($total),
            'project.document[type="smeta"].meterials_cost' => $money($materials),
            'project.document[type="smeta"].meterials_cost::human' => $words($materials),
            'project.document[type="smeta"].works_cost * 0.2' => $money($works * 0.2),
            '(project.document[type="smeta"].works_cost * 0.2)::human' => $words($works * 0.2),
            'project.document.materials_cost * 0.5' => $money($materials * 0.5),
            '(project.document.materials_cost * 0.5)::human' => $words($materials * 0.5),

            // Доп. услуги = доп. статьи сметы.
            'сумма всех доп. услуг' => $money($extras),
            'сумма всех доп. услуг текстом' => $words($extras),

            // Раздел 16 «Реквизиты и подписи сторон». Подрядчик — та же организация.
            'user.organization.director_surname' => $str('contractor_surname'),
            'user.organization.director_name::substr(0, 1)' => $initial('contractor_name'),
            'user.organization.director_father_name::substr(0, 1)' => $initial('contractor_father_name'),
            'user.organization.passport.type' => $str('contractor_passport_type'),
            'user.organization.passport.issued_by' => $str('contractor_passport_issued_by'),
            'user.organization.passport.issued_at' => $str('contractor_passport_issued_at'),
            'user.organization.passport.registration_address' => $str('contractor_passport_registration_address'),
            'user.organization.bank_name' => $str('org_bank_name'),
            'user.organization.card_name' => $str('org_card_number'),
            'user.organization.phone' => $str('org_phone'),
            'user.organization.email' => $str('org_email'),

            // Заказчик — доп. поля реквизитов.
            'project.client.name::substr(0, 1)' => $initial('customer_name'),
            'project.client.father_name::substr(0, 1)' => $initial('customer_father_name'),
            'project.client.phone' => $str('customer_phone'),
            'project.client.email' => $str('customer_email'),
        ];
    }
}
