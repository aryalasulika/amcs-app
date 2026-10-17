<?php

namespace App\Enums;

enum FlightType: string
{
    case SCHEDULED = 'SCHEDULED';
    case CHARTER = 'CHARTER';
    case MILITARY = 'MILITARY';
    case MEDIVAC = 'MEDIVAC';
    case CARGO = 'CARGO';
    case TRAINING = 'TRAINING';

    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Scheduled Flight',
            self::CHARTER => 'Charter Flight',
            self::MILITARY => 'Military / State / VIP',
            self::MEDIVAC => 'Medivac',
            self::CARGO => 'Cargo Flight',
            self::TRAINING => 'Training / Local',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $type) => ['value' => $type->value, 'label' => $type->label()],
            self::cases()
        );
    }
}
