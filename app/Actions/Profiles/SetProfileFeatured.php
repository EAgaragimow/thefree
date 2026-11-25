<?php

namespace App\Actions\Profiles;

use App\Actions\Audit\RecordAuditLog;
use App\Models\FreelancerProfile;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetProfileFeatured
{
    public function __construct(private RecordAuditLog $audit) {}

    /**
     * Put a published profile into the directory's Featured block, or take it out again.
     * `$position` orders the block (lower first); without one the profile goes to the end.
     *
     * @throws DomainException
     * @throws ValidationException
     */
    public function __invoke(FreelancerProfile $profile, bool $featured, User $admin, string $reason, ?int $position = null): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }

        DB::transaction(function () use ($profile, $featured, $admin, $reason, $position): void {
            $locked = FreelancerProfile::query()->lockForUpdate()->findOrFail($profile->id);

            if ($featured && ! $locked->isPublished()) {
                throw new DomainException('Only published profiles can be featured.');
            }

            if ($featured === $locked->isFeatured()) {
                throw new DomainException($featured ? 'The profile is already featured.' : 'The profile is not featured.');
            }

            if ($featured) {
                $position ??= ((int) FreelancerProfile::query()->whereNotNull('featured_at')->max('featured_sort')) + 1;
                $locked->forceFill(['featured_at' => now(), 'featured_sort' => $position])->save();
            } else {
                $locked->forceFill(['featured_at' => null, 'featured_sort' => null])->save();
            }

            ($this->audit)($featured ? 'profile.featured' : 'profile.unfeatured', $locked, $reason, [
                'position' => $locked->featured_sort,
            ], $admin);
        });
    }
}
