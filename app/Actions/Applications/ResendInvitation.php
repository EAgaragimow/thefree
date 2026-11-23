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

class ResendInvitation
{
    public function __construct(private RecordAuditLog $audit) {}

    /**
     * Issue a fresh invitation link; any previous link stops working.
     *
     * @throws DomainException
     */
    public function __invoke(FreelancerApplication $application, User $admin): void
    {
        $token = DB::transaction(function () use ($application, $admin): string {
            $locked = FreelancerApplication::query()->lockForUpdate()->findOrFail($application->id);

            if ($locked->status !== ApplicationStatus::Approved || $locked->activated_at !== null) {
                throw new DomainException('An invitation can only be resent for approved, not yet activated applications.');
            }

            $token = Str::random(64);

            $locked->forceFill([
                'invitation_token_hash' => hash('sha256', $token),
                'invitation_expires_at' => now()->addDays(ApproveApplication::INVITATION_VALID_DAYS),
            ])->save();

            ($this->audit)('application.invitation_resent', $locked, metadata: FreelancerInvitation::localTestingMetadata($token), actor: $admin);

            return $token;
        });

        $application->refresh()->notify(new FreelancerInvitation($token, ApproveApplication::INVITATION_VALID_DAYS));
    }
}
