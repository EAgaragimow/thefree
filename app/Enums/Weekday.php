<?php

namespace App\Enums;

/**
 * Days of the week in display order, Monday first.
 */
enum Weekday: string
{
    case Monday = 'mon';
    case Tuesday = 'tue';
    case Wednesday = 'wed';
    case Thursday = 'thu';
    case Friday = 'fri';
    case Saturday = 'sat';
    case Sunday = 'sun';

    public function label(): string
    {
        return $this->name;
    }

    public function short(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Position in the week, 0 for Monday.
     */
    public function position(): int
    {
        return array_search($this, self::cases(), true);
    }
}
