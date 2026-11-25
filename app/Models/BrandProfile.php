<?php

namespace App\Models;

use Database\Factories\BrandProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'company_name',
    'logo_path',
    'description',
    'industry',
    'website',
    'location',
    'social_links',
    'contact_name',
    'contact_email',
    'contact_phone',
])]
class BrandProfile extends Model
{
    /** @use HasFactory<BrandProfileFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'social_links' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function professionalFunctions(): BelongsToMany
    {
        return $this->belongsToMany(ProfessionalFunction::class, 'brand_profile_professional_function');
    }

    public function professionalRoles(): BelongsToMany
    {
        return $this->belongsToMany(ProfessionalRole::class, 'brand_profile_professional_role');
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'brand_profile_skill');
    }
}
