<?php

namespace App\Enums;

/**
 * How many hours per week a freelancer is available for client work.
 */
enum WeeklyHours: string
{
    case UpTo10 = '1-10';
    case From11To20 = '11-20';
    case From21To40 = '21-40';
    case Over40 = '41+';

    public function label(): string
    {
        return $this->value.' hours per week';
    }
}
