<?php

namespace App\Enums;

enum UserRole: string
{
    case Client = 'client';
    case Freelancer = 'freelancer';
    case Admin = 'admin';

    public function dashboardRouteName(): string
    {
        return match ($this) {
            self::Client => 'client.dashboard',
            self::Freelancer => 'freelancer.dashboard',
            self::Admin => 'filament.admin.pages.dashboard',
        };
    }
}
