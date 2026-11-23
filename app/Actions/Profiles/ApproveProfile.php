<?php

namespace App\Actions\Profiles;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\ProfileStatus;
use App\Enums\ReviewDecision;
use App\Models\FreelancerProfileRevision;
use App\Models\User;
use App\Notifications\ProfilePublished;
use DomainException;
use Illuminate\Support\Facades\DB;

class ApproveProfile
{
    public function __construct(
        private ApplyProfileRevision $apply,
        private RecordAuditLog $audit,
    ) {}

    /**
     * Approve a submitted revision and publish its content to the live profile.
     *
     * @throws DomainException
     */
    public function __invoke(FreelancerProfileRevision $revision, User $admin, ?string $comment = null): void
    {
        $user = DB::transaction(function () use ($revision, $admin, $comment): User {
            $locked = FreelancerProfileRevision::query()->lockForUpdate()->findOrFail($revision->id);

            if ($locked->status !== ProfileStatus::PendingReview) {
                throw new DomainException('Only profiles pending review can be approved.');
            }

            $profile = ($this->apply)($locked);

            $locked->reviews()->create([
                'freelancer_profile_id' => $profile->id,
                'reviewer_id' => $admin->id,
                'decision' => ReviewDecision::Approved,
                'comment' => $comment,
            ]);

            ($this->audit)('profile.approved', $profile, $comment, ['revision_id' => $locked->id], $admin);

            return $profile->user;
        });

        $user->notify(new ProfilePublished);
    }
}
