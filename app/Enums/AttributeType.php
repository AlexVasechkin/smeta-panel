<?php

namespace App\Enums;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Тип данных EAV-свойства. Значения хранятся в БД строкой,
 * а этот enum отвечает за приведение к типу и обратно.
 */
enum AttributeType: string
{
    case String = 'string';
    case Text = 'text';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Date = 'date';
    case Select = 'select';

    public function label(): string
    {
        return match ($this) {
            self::String => 'Строка',
            self::Text => 'Текст',
            self::Integer => 'Целое число',
            self::Decimal => 'Дробное число',
            self::Boolean => 'Да/Нет',
            self::Date => 'Дата',
            self::Select => 'Выбор из списка',
        };
    }

    /**
     * Привести хранимое строковое значение к типу свойства.
     */
    public function cast(?string $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return match ($this) {
            self::Integer => (int) $raw,
            self::Decimal => (float) $raw,
            self::Boolean => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            self::Date => Carbon::parse($raw),
            self::String, self::Text, self::Select => $raw,
        };
    }

    /**
     * Сериализовать типизированное значение в строку для хранения.
     */
    public function serialize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($this) {
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            self::Date => $value instanceof DateTimeInterface
                ? $value->format('Y-m-d')
                : (string) Carbon::parse($value)->format('Y-m-d'),
            default => (string) $value,
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
