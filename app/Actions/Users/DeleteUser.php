<?php

namespace App\Actions\Users;

use App\Actions\Audit\RecordAuditLog;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteUser
{
    public function __construct(private RecordAuditLog $audit) {}

    /**
     * Soft-delete a user account, e.g. to fulfil a personal-data deletion request.
     *
     * @throws DomainException
     * @throws ValidationException
     */
    public function __invoke(User $user, User $admin, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }

        if ($user->is($admin)) {
            throw new DomainException('You cannot delete your own account.');
        }

        DB::transaction(function () use ($user, $admin, $reason): void {
            $locked = User::withTrashed()->lockForUpdate()->findOrFail($user->id);

            if ($locked->trashed()) {
                throw new DomainException('This account has already been deleted.');
            }

            $wasRequestedByUser = $locked->deletion_requested_at !== null;

            $locked->delete();

            ($this->audit)('user.deleted', $locked, $reason, [
                'role' => $locked->role->value,
                'deletion_requested_by_user' => $wasRequestedByUser,
            ], $admin);
        });
    }
}
