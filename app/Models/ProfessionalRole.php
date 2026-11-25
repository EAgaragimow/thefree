<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\ProfessionalRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A job role a freelancer can hold (shown to users as "Role"). Named `ProfessionalRole`
 * because `Role` would be confused with the account role in {@see UserRole}.
 * Every role belongs to a function; selects list roles grouped under their function.
 */
#[Fillable(['name', 'slug', 'professional_function_id', 'sort_order'])]
class ProfessionalRole extends Model
{
    /** @use HasFactory<ProfessionalRoleFactory> */
    use HasFactory;

    /**
     * The roles as select options, each listed under its function: ordered by function, then role.
     * Roles without a function go under "Other".
     *
     * @param  list<int|string>|null  $functionIds  Only roles of these functions; null for all roles.
     * @return list<array{id: string, name: string, group: string}>
     */
    public static function selectOptions(?array $functionIds = null): array
    {
        return static::query()
            ->when($functionIds !== null, fn (Builder $roles) => $roles->whereIn('professional_function_id', array_map('intval', $functionIds)))
            ->with('professionalFunction:id,name,sort_order')
            ->get()
            ->sortBy(fn (ProfessionalRole $role): array => [$role->professionalFunction?->sort_order ?? PHP_INT_MAX, $role->sort_order, $role->name])
            ->map(fn (ProfessionalRole $role): array => [
                'id' => (string) $role->id,
                'name' => $role->name,
                'group' => $role->professionalFunction?->name ?? 'Other',
            ])
            ->values()
            ->all();
    }

    public function professionalFunction(): BelongsTo
    {
        return $this->belongsTo(ProfessionalFunction::class);
    }

    /**
     * Freelancer profiles that have this role as their main, secondary or third role.
     *
     * @return Builder<FreelancerProfile>
     */
    public function freelancerProfiles(): Builder
    {
        return FreelancerProfile::query()->where(fn (Builder $profiles) => $profiles
            ->where('main_role_id', $this->id)
            ->orWhere('secondary_role_id', $this->id)
            ->orWhere('third_role_id', $this->id));
    }
}
