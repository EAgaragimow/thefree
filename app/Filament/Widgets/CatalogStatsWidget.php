<?php

namespace App\Filament\Widgets;

use App\Models\FreelancerProfile;
use App\Models\Industry;
use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use App\Models\Skill;
use App\Models\Tool;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * The size of each catalog list and how many of its entries are in use: picked by at least one
 * freelancer. (Only functions can be switched off, but that is not what is counted here.)
 */
class CatalogStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Catalog';

    protected int|array|null $columns = 3;

    protected function getStats(): array
    {
        return [
            Stat::make('Functions', ProfessionalFunction::query()->count())
                ->description(ProfessionalFunction::query()->has('freelancerProfiles')->count().' in use'),
            Stat::make('Roles', ProfessionalRole::query()->count())
                ->description($this->rolesInUse().' in use'),
            Stat::make('Skills', Skill::query()->count())
                ->description(Skill::query()->has('freelancerProfiles')->count().' in use'),
            Stat::make('Tools', Tool::query()->count())
                ->description(Tool::query()->has('freelancerProfiles')->count().' in use'),
            Stat::make('Industries', Industry::query()->count())
                ->description(Industry::query()->has('freelancerProfiles')->count().' in use'),
        ];
    }

    /**
     * Roles that a freelancer holds as their main, secondary or third role.
     */
    private function rolesInUse(): int
    {
        return ProfessionalRole::query()
            ->where(fn (Builder $roles) => $roles
                ->whereIn('id', FreelancerProfile::query()->select('main_role_id'))
                ->orWhereIn('id', FreelancerProfile::query()->select('secondary_role_id'))
                ->orWhereIn('id', FreelancerProfile::query()->select('third_role_id')))
            ->count();
    }
}
