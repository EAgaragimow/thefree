<?php

namespace App\Actions\Users;

use App\Actions\Audit\RecordAuditLog;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestoreUser
{
    public function __construct(private RecordAuditLog $audit) {}

    /**
     * @throws DomainException
     * @throws ValidationException
     */
    public function __invoke(User $user, User $admin, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }

        DB::transaction(function () use ($user, $admin, $reason): void {
            $locked = User::withTrashed()->lockForUpdate()->findOrFail($user->id);

            if (! $locked->trashed()) {
                throw new DomainException('This account is not deleted.');
            }

            $locked->restore();

            ($this->audit)('user.restored', $locked, $reason, actor: $admin);
        });
    }
}
