<?php

namespace App\Actions\Applications;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\ApplicationStatus;
use App\Models\FreelancerApplication;
use App\Models\User;
use App\Notifications\FreelancerInvitation;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApproveApplication
{
    public const INVITATION_VALID_DAYS = 7;

    public function __construct(private RecordAuditLog $audit) {}

    /**
     * Approve a pending application and email the applicant an activation invitation.
     * Approving an application does not publish any profile.
     *
     * @throws DomainException
     */
    public function __invoke(FreelancerApplication $application, User $admin, ?string $note = null): void
    {
        $token = DB::transaction(function () use ($application, $admin, $note): string {
            $locked = FreelancerApplication::query()->lockForUpdate()->findOrFail($application->id);

            if ($locked->status !== ApplicationStatus::Pending) {
                throw new DomainException('Only pending applications can be approved.');
            }

            $token = Str::random(64);

            $locked->forceFill([
                'status' => ApplicationStatus::Approved,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
                'invitation_token_hash' => hash('sha256', $token),
                'invitation_expires_at' => now()->addDays(self::INVITATION_VALID_DAYS),
            ])->save();

            ($this->audit)('application.approved', $locked, $note, FreelancerInvitation::localTestingMetadata($token), $admin);

            return $token;
        });

        $application->refresh()->notify(new FreelancerInvitation($token, self::INVITATION_VALID_DAYS));
    }
}
