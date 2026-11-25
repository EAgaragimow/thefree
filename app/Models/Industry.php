<?php

namespace App\Models;

use Database\Factories\IndustryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug'])]
class Industry extends Model
{
    /** @use HasFactory<IndustryFactory> */
    use HasFactory;

    /**
     * Brand profiles whose industry is this entry.
     */
    public function brandProfiles(): HasMany
    {
        return $this->hasMany(BrandProfile::class);
    }

    /**
     * Freelancer profiles that currently list this entry.
     */
    public function freelancerProfiles(): BelongsToMany
    {
        return $this->belongsToMany(FreelancerProfile::class, 'freelancer_profile_industry');
    }
}
