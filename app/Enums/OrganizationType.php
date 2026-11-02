<?php

namespace App\Enums;

/**
 * Организационно-правовая форма организации.
 */
enum OrganizationType: string
{
    case Ip = 'ip';
    case Ooo = 'ooo';

    public function label(): string
    {
        return match ($this) {
            self::Ip => 'ИП',
            self::Ooo => 'ООО',
        };
    }

    /**
     * Полное название формы — для карточки организации.
     */
    public function fullLabel(): string
    {
        return match ($this) {
            self::Ip => 'Индивидуальный предприниматель',
            self::Ooo => 'Общество с ограниченной ответственностью',
        };
    }

    /**
     * Цвет бейджа, см. resources/js/components/status-badge.tsx.
     */
    public function color(): string
    {
        return match ($this) {
            self::Ip => 'amber',
            self::Ooo => 'blue',
        };
    }

    /**
     * Число цифр в ИНН: у ИП это ИНН физлица (12), у ООО — ИНН юрлица (10).
     */
    public function innLength(): int
    {
        return match ($this) {
            self::Ip => 12,
            self::Ooo => 10,
        };
    }

    /**
     * Число цифр в основном регистрационном номере.
     */
    public function registrationNumberLength(): int
    {
        return match ($this) {
            self::Ip => 15,
            self::Ooo => 13,
        };
    }

    /**
     * Название основного регистрационного номера.
     */
    public function registrationNumberLabel(): string
    {
        return match ($this) {
            self::Ip => 'ОГРНИП',
            self::Ooo => 'ОГРН',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
