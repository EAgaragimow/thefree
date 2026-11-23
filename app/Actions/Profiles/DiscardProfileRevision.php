<?php

namespace App\Actions\Profiles;

use App\Models\FreelancerProfile;
use DomainException;
use Illuminate\Support\Facades\DB;

class DiscardProfileRevision
{
    public function __construct(private ProfileMedia $media) {}

    /**
     * Throw away pending edits and fall back to the live profile.
     *
     * @throws DomainException
     */
    public function __invoke(FreelancerProfile $profile): void
    {
        DB::transaction(function () use ($profile): void {
            $locked = FreelancerProfile::query()->lockForUpdate()->findOrFail($profile->id);
            $revision = $locked->openRevision()->first();

            if ($revision === null) {
                throw new DomainException('There are no changes to discard.');
            }

            if (! $revision->status->isEditable()) {
                throw new DomainException('Changes cannot be discarded while the profile is under review.');
            }

            if (! $locked->isPublished()) {
                throw new DomainException('Your profile has not been published yet, so there is nothing to revert to.');
            }

            $this->media->deleteUnpublished($revision->content(), $locked->liveContent());

            $revision->delete();
        });
    }
}
