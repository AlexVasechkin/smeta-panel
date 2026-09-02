<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Новый',
            self::InProgress => 'В работе',
            self::OnHold => 'Приостановлен',
            self::Done => 'Завершён',
            self::Cancelled => 'Отменён',
        };
    }

    /**
     * Цвет бейджа на фронте (соответствует вариантам Badge/утилитам Tailwind).
     */
    public function color(): string
    {
        return match ($this) {
            self::New => 'blue',
            self::InProgress => 'amber',
            self::OnHold => 'zinc',
            self::Done => 'green',
            self::Cancelled => 'red',
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
