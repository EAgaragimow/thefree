<?php

namespace App\Actions\Applications;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Profiles\ProfileContent;
use App\Enums\ProfileStatus;
use App\Enums\UserRole;
use App\Models\FreelancerApplication;
use App\Models\FreelancerProfile;
use App\Models\ProfessionalRole;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ActivateFreelancerAccount
{
    public function __construct(private RecordAuditLog $audit) {}

    /**
     * Turn an approved application into a Freelancer account with an unpublished live profile
     * and a draft revision prepopulated from the application. The invitation token is single-use.
     *
     * @throws ValidationException
     */
    public function __invoke(string $token, string $password): User
    {
        return DB::transaction(function () use ($token, $password): User {
            $application = FreelancerApplication::query()
                ->where('invitation_token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if (! $application || ! $application->invitationIsUsable()) {
                throw ValidationException::withMessages([
                    'token' => 'This invitation link is invalid or has expired.',
                ]);
            }

            if (User::withTrashed()->where('email', $application->email)->exists()) {
                throw ValidationException::withMessages([
                    'token' => 'An account with this email address already exists.',
                ]);
            }

            $user = new User([
                'name' => $application->full_name,
                'email' => $application->email,
                'password' => $password,
            ]);
            $user->role = UserRole::Freelancer;
            $user->email_verified_at = now();
            $user->save();

            $profile = new FreelancerProfile;
            $profile->is_newbie = true;
            $profile->user()->associate($user);
            $profile->application()->associate($application);
            $profile->save();

            // The application lists skills as free text. Only those that match the skill catalog
            // are carried over; the catalog is curated, so unknown names are never added to it.
            $skillIds = Skill::query()
                ->whereIn('slug', collect($application->skills ?? [])
                    ->map(fn ($name): string => Str::slug(trim((string) $name)))
                    ->filter()
                    ->unique()
                    ->all())
                ->limit(ProfileContent::MAX_SKILLS)
                ->pluck('id')
                ->all();

            // The application keeps the picked roles by name, in pick order. They go into the draft in
            // that order, so the profile's main, secondary and third role follow when it is approved.
            $roleIds = collect($application->roles ?? [])
                ->map(fn ($name): ?int => ProfessionalRole::query()->where('slug', Str::slug(trim((string) $name)))->value('id'))
                ->filter()
                ->unique()
                ->take(ProfileContent::MAX_ROLES)
                ->values()
                ->all();

            $profile->revisions()->create([
                'status' => ProfileStatus::Draft,
                'payload' => ProfileContent::fromArray([
                    'professional_title' => $application->specialty,
                    'about' => $application->about ?: $application->experience_summary,
                    'location' => $application->location,
                    'timezone' => 'UTC',
                    'website' => $application->portfolio_links[0] ?? null,
                    'social_links' => $application->social_links ?? [],
                    'role_ids' => $roleIds,
                    'skill_ids' => $skillIds,
                ])->toArray(),
            ]);

            $application->forceFill([
                'activated_at' => now(),
                'user_id' => $user->id,
                'invitation_token_hash' => null,
            ])->save();

            ($this->audit)('application.activated', $application, actor: $user);

            return $user;
        });
    }
}
