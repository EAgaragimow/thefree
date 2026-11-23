<?php

namespace App\Actions\Profiles;

use App\Enums\ProfileStatus;
use App\Models\Category;
use App\Models\FreelancerProfile;
use App\Models\FreelancerProfileRevision;
use App\Models\Skill;
use Illuminate\Support\Facades\DB;

class ApplyProfileRevision
{
    public function __construct(private ProfileMedia $media) {}

    /**
     * Write an approved revision's content to the live profile and close the revision.
     *
     * Categories or skills that were deleted while the revision was open are dropped silently.
     */
    public function __invoke(FreelancerProfileRevision $revision): FreelancerProfile
    {
        return DB::transaction(function () use ($revision): FreelancerProfile {
            $profile = FreelancerProfile::query()->lockForUpdate()->findOrFail($revision->freelancer_profile_id);

            $before = $profile->liveContent();
            $after = $revision->content();

            $profile->fill($after->scalarAttributes());
            $profile->published_at ??= now();
            $profile->content_updated_at = now();
            $profile->save();

            $profile->categories()->sync(Category::query()->whereIn('id', $after->categoryIds())->pluck('id')->all());
            $profile->skills()->sync(Skill::query()->whereIn('id', $after->skillIds())->pluck('id')->all());

            $profile->experiences()->delete();

            foreach ($after->experiences() as $position => $experience) {
                $profile->experiences()->create([...$experience, 'sort_order' => $position]);
            }

            $revision->forceFill([
                'status' => ProfileStatus::Published,
                'applied_at' => now(),
                'last_change_request' => null,
            ])->save();

            $this->media->deleteDropped($before, $after, $after);

            return $profile;
        });
    }
}
