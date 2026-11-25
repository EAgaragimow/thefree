<?php

namespace App\Models;

use Database\Factories\ProfessionalFunctionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The single area a freelancer works in (shown to users as "Function"). The class is not
 * called `Function` because that is a reserved word in PHP.
 */
#[Fillable(['name', 'slug', 'is_active', 'sort_order'])]
class ProfessionalFunction extends Model
{
    /** @use HasFactory<ProfessionalFunctionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function roles(): HasMany
    {
        return $this->hasMany(ProfessionalRole::class)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Freelancer profiles that work in this function.
     */
    public function freelancerProfiles(): HasMany
    {
        return $this->hasMany(FreelancerProfile::class);
    }
}
