<?php

namespace App\Enums;

enum MovementState: string
{
    case VACANT = 'VACANT';
    case ENTERING = 'ENTERING';
    case ON = 'ON';
    case DELAY = 'DELAY';
    case RON = 'RON';
    case OFF = 'OFF';

    /**
     * Human readable label for the APRON (AMC) dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::VACANT => 'VACANT',
            self::ENTERING => 'ENTERING',
            self::ON => 'BLOCK ON',
            self::DELAY => 'DELAYED',
            self::RON => 'RON',
            self::OFF => 'BLOCK OFF',
        };
    }

    /**
     * Label used by the ATC tower monitor (FR: ATC view wording).
     */
    public function atcLabel(): string
    {
        return match ($this) {
            self::VACANT => 'READY',
            self::ENTERING => 'APPROACHING',
            default => $this->label(),
        };
    }

    /**
     * A movement is considered "active" while the aircraft still occupies the stand.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::ENTERING, self::ON, self::DELAY, self::RON], true);
    }

    /**
     * States that may be transitioned into from this state (FR-08).
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::VACANT => [self::ENTERING],
            self::ENTERING => [self::ON, self::OFF],
            self::ON => [self::DELAY, self::RON, self::OFF],
            self::DELAY => [self::ON, self::RON, self::OFF],
            self::RON => [self::ON, self::DELAY, self::OFF],
            self::OFF => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return array_map(
            static fn (self $state) => $state->value,
            array_filter(self::cases(), static fn (self $state) => $state->isActive())
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $state) => ['value' => $state->value, 'label' => $state->label()],
            self::cases()
        );
    }
}
