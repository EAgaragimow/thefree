<?php

namespace App\Actions\Fortify;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Models\User;
use DateTimeZone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private RecordAuditLog $audit) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'timezone' => ['nullable', 'string', 'max:64'],
        ])->validate();

        $user = new User([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'timezone' => $this->knownTimezone($input['timezone'] ?? null),
        ]);

        // Public registration always creates a Client; other roles are provisioned separately.
        $user->role = UserRole::Client;
        $user->save();

        ($this->audit)('user.registered', $user, metadata: [
            'role' => $user->role->value,
            ...$this->localTestingMetadata($user),
        ], actor: $user);

        return $user;
    }

    /**
     * Audit-log metadata that exposes the email verification link so a new account can be
     * activated while testing, before emails are actually sent.
     *
     * The link signs the account in as a verified user, so it is recorded in the `local`
     * environment only and never in production.
     *
     * @return array<string, string>
     */
    private function localTestingMetadata(User $user): array
    {
        if (! app()->environment('local')) {
            return [];
        }

        return ['activation_url' => URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
        )];
    }

    /**
     * The browser reports its own time zone, and some browsers still use retired names (for
     * example "Europe/Kiev"). A name we do not know must not block signing up, so it falls back
     * to UTC; the user can pick their time zone in the account settings.
     */
    private function knownTimezone(?string $timezone): string
    {
        return in_array($timezone, DateTimeZone::listIdentifiers(), true) ? $timezone : 'UTC';
    }
}
