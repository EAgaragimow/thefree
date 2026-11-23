<?php

namespace App\Actions\Profiles;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\ProfileStatus;
use App\Models\FreelancerProfile;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitProfileForReview
{
    public function __construct(private RecordAuditLog $audit) {}

    /**
     * @throws DomainException
     * @throws ValidationException
     */
    public function __invoke(FreelancerProfile $profile): void
    {
        DB::transaction(function () use ($profile): void {
            $locked = FreelancerProfile::query()->lockForUpdate()->findOrFail($profile->id);
            $revision = $locked->openRevision()->lockForUpdate()->first();

            if ($revision === null || ! $revision->status->canBeSubmitted()) {
                throw new DomainException('This profile cannot be submitted for review in its current state.');
            }

            $this->ensureComplete($revision->content());

            $revision->forceFill([
                'status' => ProfileStatus::PendingReview,
                'submitted_at' => now(),
            ])->save();

            ($this->audit)('profile.submitted', $locked, metadata: ['revision_id' => $revision->id], actor: $locked->user);
        });
    }

    /**
     * @throws ValidationException
     */
    private function ensureComplete(ProfileContent $content): void
    {
        $errors = [];

        if (blank($content->get('professional_title'))) {
            $errors['professional_title'] = 'A professional title is required before submitting.';
        }

        if (blank($content->get('about')) || mb_strlen((string) $content->get('about')) < 50) {
            $errors['about'] = 'Please write at least 50 characters about yourself before submitting.';
        }

        if (blank($content->get('location'))) {
            $errors['location'] = 'A location is required before submitting.';
        }

        if ($content->categoryIds() === []) {
            $errors['category_ids'] = 'Select at least one category before submitting.';
        }

        if ($content->skillIds() === []) {
            $errors['skill_ids'] = 'Select at least one skill before submitting.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
