<?php

namespace App\Support;

use App\Enums\AttributeType;

/**
 * Единый источник правды для паспортных данных физлица,
 * хранящихся в EAV-свойствах клиента.
 */
class Passport
{
    /**
     * Допустимые типы паспорта (хранятся в EAV строкой).
     *
     * @var array<int, string>
     */
    public const TYPES = [
        'Гражданина РФ',
        'Гражданина республики Армения',
    ];

    /**
     * Ключ формы => определение EAV-свойства.
     *
     * @var array<string, array{code: string, name: string, type: AttributeType, options?: array<int, string>}>
     */
    public const FIELDS = [
        'type' => ['code' => 'passport_type', 'name' => 'Паспорт', 'type' => AttributeType::Select, 'options' => self::TYPES],
        'series' => ['code' => 'passport_series', 'name' => 'Серия', 'type' => AttributeType::String],
        'number' => ['code' => 'passport_number', 'name' => 'Номер', 'type' => AttributeType::String],
        'issued_by' => ['code' => 'passport_issued_by', 'name' => 'Кем выдан', 'type' => AttributeType::Text],
        'issued_at' => ['code' => 'passport_issued_at', 'name' => 'Дата выдачи', 'type' => AttributeType::Date],
    ];

    /**
     * Доп. паспортные поля сверх FIELDS. Используются для клиента и
     * руководителя организации; у пользователя (User) не применяются.
     *
     * @var array<string, array{code: string, name: string, type: AttributeType, options?: array<int, string>}>
     */
    public const EXTRA_FIELDS = [
        'registration_address' => ['code' => 'passport_registration_address', 'name' => 'Адрес регистрации', 'type' => AttributeType::Text],
    ];

    /**
     * Расширенный набор паспортных полей (базовые + доп.) —
     * для клиента и руководителя организации.
     *
     * @return array<string, array{code: string, name: string, type: AttributeType, options?: array<int, string>}>
     */
    public static function extendedFields(): array
    {
        return self::FIELDS + self::EXTRA_FIELDS;
    }

    /**
     * Тип паспорта в виде опций для select ({value,label}).
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function typeOptions(): array
    {
        return array_map(fn (string $type) => ['value' => $type, 'label' => $type], self::TYPES);
    }
}
