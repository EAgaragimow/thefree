<?php

namespace App\Actions\Profiles;

use App\Models\FreelancerProfile;
use App\Models\FreelancerProfileRevision;
use DomainException;

class SaveFreelancerProfile
{
    public function __construct(private UpdateProfileContent $update) {}

    /**
     * Save the profile form (basic details, function, roles, skills, tools, industries, rates and
     * availability, best-fit lines, work history) into the open revision. Highlights, services and
     * consultation offers are left as they are.
     *
     * `role_ids` is ordered: the first role is the main role, the second the secondary role and
     * the third the third role. Those three technical fields are derived from it when the
     * revision is approved.
     *
     * @param  array<string, mixed>  $attributes  Profile content keys other than work history (see ProfileContent).
     * @param  list<array<string, mixed>>  $experiences
     *
     * @throws DomainException
     */
    public function __invoke(
        FreelancerProfile $profile,
        array $attributes,
        array $experiences,
    ): ?FreelancerProfileRevision {
        return ($this->update)($profile, [
            ...$attributes,
            'experiences' => $experiences,
        ]);
    }
}
