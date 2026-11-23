<?php

namespace App\Enums;

/**
 * Every freelancer has exactly these two consultation types. They are configured, never added or removed.
 */
enum ConsultationType: string
{
    case Free = 'free';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free consultation',
            self::Paid => 'Paid consultation',
        };
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }
}
