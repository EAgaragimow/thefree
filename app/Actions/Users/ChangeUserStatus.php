<?php

namespace App\Actions\Users;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserStatus;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeUserStatus
{
    public function __construct(private RecordAuditLog $audit) {}

    /**
     * Restrict, suspend or restore a user account. A reason is mandatory and audited.
     *
     * @throws DomainException
     * @throws ValidationException
     */
    public function __invoke(User $user, UserStatus $status, User $admin, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }

        if ($user->is($admin)) {
            throw new DomainException('You cannot change the status of your own account.');
        }

        DB::transaction(function () use ($user, $status, $admin, $reason): void {
            $locked = User::withTrashed()->lockForUpdate()->findOrFail($user->id);
            $previous = $locked->status;

            if ($previous === $status) {
                throw new DomainException("The account is already {$status->value}.");
            }

            $locked->forceFill(['status' => $status])->save();

            ($this->audit)('user.status_changed', $locked, $reason, [
                'from' => $previous->value,
                'to' => $status->value,
            ], $admin);
        });
    }
}
