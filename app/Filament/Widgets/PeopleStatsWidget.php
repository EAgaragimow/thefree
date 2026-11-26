<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * How many freelancers and brands there are, and how many of them are active: accounts in good
 * standing, that is not restricted, suspended or deactivated. Deleted accounts are not counted.
 */
class PeopleStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Freelancers and brands';

    protected int|array|null $columns = 2;

    protected function getStats(): array
    {
        return [
            $this->stat('Freelancers', UserRole::Freelancer),
            $this->stat('Brands', UserRole::Client),
        ];
    }

    private function stat(string $label, UserRole $role): Stat
    {
        $accounts = fn () => User::query()->where('role', $role);

        $active = $accounts()->where('status', UserStatus::Active)->whereNull('deactivated_at')->count();

        return Stat::make($label, $accounts()->count())->description("{$active} active");
    }
}
