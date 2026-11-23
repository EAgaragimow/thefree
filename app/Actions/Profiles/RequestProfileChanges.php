<?php

namespace App\Actions\Profiles;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\ProfileStatus;
use App\Enums\ReviewDecision;
use App\Models\FreelancerProfileRevision;
use App\Models\User;
use App\Notifications\ProfileChangesRequested;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestProfileChanges
{
    public function __construct(private RecordAuditLog $audit) {}

    /**
     * @throws DomainException
     * @throws ValidationException
     */
    public function __invoke(FreelancerProfileRevision $revision, User $admin, string $comment): void
    {
        $comment = trim($comment);

        if ($comment === '') {
            throw ValidationException::withMessages(['comment' => 'Please describe the changes you are requesting.']);
        }

        $user = DB::transaction(function () use ($revision, $admin, $comment): User {
            $locked = FreelancerProfileRevision::query()->lockForUpdate()->findOrFail($revision->id);

            if ($locked->status !== ProfileStatus::PendingReview) {
                throw new DomainException('Changes can only be requested on profiles pending review.');
            }

            $locked->forceFill([
                'status' => ProfileStatus::ChangesRequested,
                'last_change_request' => $comment,
            ])->save();

            $profile = $locked->profile;

            $locked->reviews()->create([
                'freelancer_profile_id' => $profile->id,
                'reviewer_id' => $admin->id,
                'decision' => ReviewDecision::ChangesRequested,
                'comment' => $comment,
            ]);

            ($this->audit)('profile.changes_requested', $profile, $comment, ['revision_id' => $locked->id], $admin);

            return $profile->user;
        });

        $user->notify(new ProfileChangesRequested($comment));
    }
}
