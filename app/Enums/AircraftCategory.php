<?php

namespace App\Enums;

enum AircraftCategory: string
{
    case NARROW_BODY = 'NARROW_BODY';
    case WIDE_BODY = 'WIDE_BODY';
    case TURBOPROP = 'TURBOPROP';
    case PRIVATE_JET = 'PRIVATE_JET';
    case LIGHT = 'LIGHT';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::NARROW_BODY => 'Narrow-body',
            self::WIDE_BODY => 'Wide-body',
            self::TURBOPROP => 'Turboprop',
            self::PRIVATE_JET => 'Private Jet',
            self::LIGHT => 'Light Aircraft',
            self::OTHER => 'Lainnya',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $category) => ['value' => $category->value, 'label' => $category->label()],
            self::cases()
        );
    }
}
