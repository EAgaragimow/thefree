<?php

namespace App\Actions\Profiles;

use App\Enums\ProfileStatus;
use App\Models\FreelancerProfile;
use App\Models\FreelancerProfileRevision;
use Illuminate\Support\Facades\DB;

class OpenProfileRevision
{
    /**
     * Return the profile's open revision, starting a Draft copy of the live content when
     * there is none. The profile row is locked so concurrent edits cannot open two.
     */
    public function __invoke(FreelancerProfile $profile): FreelancerProfileRevision
    {
        return DB::transaction(function () use ($profile): FreelancerProfileRevision {
            $locked = FreelancerProfile::query()->lockForUpdate()->findOrFail($profile->id);

            return $locked->openRevision()->first()
                ?? $locked->revisions()->create([
                    'status' => ProfileStatus::Draft,
                    'payload' => $locked->liveContent()->toArray(),
                ]);
        });
    }
}
