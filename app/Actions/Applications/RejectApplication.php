<?php

namespace App\Actions\Applications;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\ApplicationStatus;
use App\Models\FreelancerApplication;
use App\Models\User;
use App\Notifications\ApplicationRejected;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectApplication
{
    public function __construct(private RecordAuditLog $audit) {}

    /**
     * @throws DomainException
     * @throws ValidationException
     */
    public function __invoke(FreelancerApplication $application, User $admin, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required to reject an application.']);
        }

        DB::transaction(function () use ($application, $admin, $reason): void {
            $locked = FreelancerApplication::query()->lockForUpdate()->findOrFail($application->id);

            if ($locked->status !== ApplicationStatus::Pending) {
                throw new DomainException('Only pending applications can be rejected.');
            }

            $locked->forceFill([
                'status' => ApplicationStatus::Rejected,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            ($this->audit)('application.rejected', $locked, $reason, actor: $admin);
        });

        $application->refresh()->notify(new ApplicationRejected($reason));
    }
}
