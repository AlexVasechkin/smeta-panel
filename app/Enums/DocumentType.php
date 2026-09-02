<?php

namespace App\Enums;

/**
 * Типы документов раздела «Документы».
 */
enum DocumentType: string
{
    case PreliminaryEstimate = 'preliminary_estimate'; // Предварительная смета
    case Contract = 'contract';                        // Договор
    case Estimate = 'estimate';                        // Смета
    case CashAcceptanceAct = 'cash_acceptance_act';    // Акт приёма-передачи денежных средств
    case WorkCompletionAct = 'work_completion_act';    // Акт выполненных работ
    case KeysHandoverAct = 'keys_handover_act';        // Акт приёма-передачи ключей

    public function label(): string
    {
        return match ($this) {
            self::PreliminaryEstimate => 'Предварительная смета',
            self::Contract => 'Договор',
            self::Estimate => 'Смета',
            self::CashAcceptanceAct => 'Акт приёма-передачи денежных средств',
            self::WorkCompletionAct => 'Акт выполненных работ',
            self::KeysHandoverAct => 'Акт приёма-передачи ключей',
        };
    }

    /**
     * Реализована ли генерация для этого типа (шаблоны добавляются поэтапно).
     */
    public function isImplemented(): bool
    {
        return match ($this) {
            self::PreliminaryEstimate, self::Estimate, self::Contract, self::CashAcceptanceAct, self::WorkCompletionAct => true,
            default => false,
        };
    }

    /**
     * Путь к шаблону на диске (относительно корня диска), либо null.
     */
    public function templatePath(): ?string
    {
        return match ($this) {
            self::PreliminaryEstimate => 'templates/docs/smeta-sample.xlsx',
            self::Estimate => 'templates/docs/smeta-final.xlsx',
            self::Contract => 'templates/docs/order-sample.docx',
            self::CashAcceptanceAct => 'templates/docs/money-act.docx',
            self::WorkCompletionAct => 'templates/docs/works-act.docx',
            default => null,
        };
    }

    /**
     * Расширение генерируемого файла.
     */
    public function fileExtension(): string
    {
        return match ($this) {
            self::Contract, self::CashAcceptanceAct, self::WorkCompletionAct => 'docx',
            default => 'xlsx',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, implemented: bool}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'implemented' => $case->isImplemented(),
            ],
            self::cases(),
        );
    }
}
