<?php

namespace App\Actions\Profiles;

use App\Models\FreelancerProfile;
use App\Models\FreelancerProfileRevision;
use DomainException;

class SaveFreelancerProfile
{
    public function __construct(private UpdateProfileContent $update) {}

    /**
     * Save the profile form (basic details, categories, skills, work history) into the open
     * revision. Highlights, services and consultation offers are left as they are.
     *
     * @param  array<string, mixed>  $attributes  Scalar profile fields.
     * @param  list<int|string>  $categoryIds
     * @param  list<int|string>  $skillIds
     * @param  list<array<string, mixed>>  $experiences
     *
     * @throws DomainException
     */
    public function __invoke(
        FreelancerProfile $profile,
        array $attributes,
        array $categoryIds,
        array $skillIds,
        array $experiences,
    ): ?FreelancerProfileRevision {
        return ($this->update)($profile, [
            ...$attributes,
            'category_ids' => $categoryIds,
            'skill_ids' => $skillIds,
            'experiences' => $experiences,
        ]);
    }
}
