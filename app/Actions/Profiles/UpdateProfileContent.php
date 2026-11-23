<?php

namespace App\Actions\Profiles;

use App\Enums\ProfileStatus;
use App\Models\FreelancerProfile;
use App\Models\FreelancerProfileRevision;
use DomainException;
use Illuminate\Support\Facades\DB;

class UpdateProfileContent
{
    public function __construct(
        private OpenProfileRevision $openRevision,
        private ProfileMedia $media,
    ) {}

    /**
     * Merge the given content keys into the profile's open revision, opening one if needed.
     * The live profile is never touched; it changes only when a revision is approved.
     *
     * Returns null when the edits bring a published profile back to exactly what is live,
     * in which case the revision is dropped.
     *
     * @param  array<string, mixed>  $changes  Payload keys to replace (see ProfileContent).
     *
     * @throws DomainException
     */
    public function __invoke(FreelancerProfile $profile, array $changes): ?FreelancerProfileRevision
    {
        return DB::transaction(function () use ($profile, $changes): ?FreelancerProfileRevision {
            $revision = FreelancerProfileRevision::query()
                ->lockForUpdate()
                ->findOrFail(($this->openRevision)($profile)->id);

            if (! $revision->status->isEditable()) {
                throw new DomainException('This profile cannot be edited while it is under review.');
            }

            $liveProfile = FreelancerProfile::query()->findOrFail($profile->id);
            $live = $liveProfile->liveContent();
            $before = $revision->content();
            $after = $before->with($changes);

            if ($liveProfile->isPublished() && $revision->status === ProfileStatus::Draft && $after->equals($live)) {
                $this->media->deleteUnpublished($before, $live);
                $revision->delete();

                return null;
            }

            $revision->forceFill(['payload' => $after->toArray()])->save();

            $this->media->deleteDropped($before, $after, $live);

            return $revision;
        });
    }
}
