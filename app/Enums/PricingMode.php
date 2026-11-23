<?php

namespace App\Enums;

enum PricingMode: string
{
    case Fixed = 'fixed';
    case StartingFrom = 'starting-from';
    case CustomQuote = 'custom-quote';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed price',
            self::StartingFrom => 'Starting from',
            self::CustomQuote => 'Custom quote',
        };
    }

    /**
     * Custom quotes are agreed per request, so only the other modes carry a price.
     */
    public function requiresPrice(): bool
    {
        return $this !== self::CustomQuote;
    }
}
