<?php

use App\Actions\Profiles\ApproveProfile;
use App\Actions\Profiles\DiscardProfileRevision;
use App\Actions\Profiles\RequestProfileChanges;
use App\Actions\Profiles\SaveFreelancerProfile;
use App\Actions\Profiles\SubmitProfileForReview;
use App\Enums\ProfileStatus;
use App\Enums\ReviewDecision;
use App\Enums\WeeklyHours;
use App\Models\AuditLog;
use App\Models\FreelancerProfile;
use App\Models\FreelancerProfileRevision;
use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use App\Models\Skill;
use App\Models\Tool;
use App\Models\User;
use App\Notifications\ProfileChangesRequested;
use App\Notifications\ProfilePublished;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * A profile with an open, complete revision in the given state ('draft', 'pendingReview' or 'changesRequested').
 */
function profileWithRevision(string $state = 'pendingReview'): FreelancerProfile
{
    $profile = FreelancerProfile::factory()->create();
    FreelancerProfileRevision::factory()->{$state}()->create(['freelancer_profile_id' => $profile->id]);

    return $profile->load('openRevision');
}

function saveProfile(FreelancerProfile $profile, array $attributes = []): ?FreelancerProfileRevision
{
    return app(SaveFreelancerProfile::class)(
        $profile,
        $attributes,
        $profile->workingContent()->experiences(),
    );
}

it('publishes the submitted revision to the live profile on approval', function () {
    Notification::fake();
    $this->freezeTime();
    $admin = User::factory()->admin()->create();
    $profile = profileWithRevision();
    $revision = $profile->openRevision;

    app(ApproveProfile::class)($revision, $admin, 'Welcome aboard');

    $profile->refresh();
    $revision->refresh();

    expect($profile->isPublished())->toBeTrue()
        ->and($profile->published_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($profile->content_updated_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($profile->professional_title)->toBe($revision->content()->get('professional_title'))
        ->and($profile->about)->toBe($revision->content()->get('about'))
        ->and($profile->professional_function_id)->toBe($revision->content()->professionalFunctionId())
        ->and($profile->main_role_id)->toBe($revision->content()->roleIds()[0])
        ->and($profile->secondary_role_id)->toBeNull()
        ->and($profile->third_role_id)->toBeNull()
        ->and($profile->skills->pluck('id')->all())->toEqualCanonicalizing($revision->content()->skillIds())
        ->and($profile->tools->pluck('id')->all())->toEqualCanonicalizing($revision->content()->toolIds())
        ->and($profile->hourly_rate_cents)->toBe($revision->content()->get('hourly_rate_cents'))
        ->and($profile->offers_monthly_retainers)->toBeFalse()
        ->and($profile->weekly_hours)->toBe(WeeklyHours::From21To40)
        ->and($profile->open_to_full_time)->toBeFalse()
        ->and($profile->liveContent()->equals($revision->content()))->toBeTrue()
        ->and($profile->openRevision)->toBeNull()
        ->and($profile->displayStatus())->toBe(ProfileStatus::Published)
        ->and($revision->status)->toBe(ProfileStatus::Published)
        ->and($revision->applied_at)->not->toBeNull()
        ->and($revision->reviews->first()->decision)->toBe(ReviewDecision::Approved)
        ->and($revision->reviews->first()->reviewer_id)->toBe($admin->id);

    Notification::assertSentTo($profile->user, ProfilePublished::class);
    expect(AuditLog::where('action', 'profile.approved')->where('actor_id', $admin->id)->exists())->toBeTrue();
});

it('refuses to approve a revision that is not pending review', function (string $state) {
    Notification::fake();
    $revision = FreelancerProfileRevision::factory()->{$state}()->create();

    expect(fn () => app(ApproveProfile::class)($revision, User::factory()->admin()->create()))
        ->toThrow(DomainException::class);

    Notification::assertNothingSent();
})->with(['draft', 'changesRequested', 'applied']);

it('returns the revision to the freelancer with the requested changes', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $profile = profileWithRevision();
    $revision = $profile->openRevision;

    app(RequestProfileChanges::class)($revision, $admin, 'Please add your portfolio.');

    $revision->refresh();

    expect($revision->status)->toBe(ProfileStatus::ChangesRequested)
        ->and($revision->last_change_request)->toBe('Please add your portfolio.')
        ->and($profile->refresh()->isPublished())->toBeFalse()
        ->and($revision->reviews->first()->decision)->toBe(ReviewDecision::ChangesRequested);

    Notification::assertSentTo($profile->user, ProfileChangesRequested::class, fn (ProfileChangesRequested $notification) => $notification->comment === 'Please add your portfolio.');
    expect(AuditLog::where('action', 'profile.changes_requested')->exists())->toBeTrue();
});

it('requires a comment when requesting changes', function () {
    Notification::fake();
    $revision = profileWithRevision()->openRevision;

    expect(fn () => app(RequestProfileChanges::class)($revision, User::factory()->admin()->create(), ' '))
        ->toThrow(ValidationException::class);

    expect($revision->refresh()->status)->toBe(ProfileStatus::PendingReview);
});

it('refuses to request changes on a revision that is not pending review', function () {
    $revision = FreelancerProfileRevision::factory()->draft()->create();

    expect(fn () => app(RequestProfileChanges::class)($revision, User::factory()->admin()->create(), 'Fix it'))
        ->toThrow(DomainException::class);
});

it('does not allow a profile under review to be edited', function () {
    $profile = profileWithRevision();

    expect(fn () => saveProfile($profile, ['professional_title' => 'Sneaky']))->toThrow(DomainException::class);

    expect($profile->openRevision->refresh()->content()->get('professional_title'))->not->toBe('Sneaky');
});

it('does not allow a profile that is already under review to be submitted again', function () {
    $profile = profileWithRevision();

    expect(fn () => app(SubmitProfileForReview::class)($profile))->toThrow(DomainException::class);
});

it('does not submit a profile that has no open revision', function () {
    $profile = FreelancerProfile::factory()->published()->create();

    expect(fn () => app(SubmitProfileForReview::class)($profile))->toThrow(DomainException::class);
});

it('walks a revision through changes requested, resubmission and publication', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $profile = profileWithRevision('draft');
    $revision = $profile->openRevision;

    app(SubmitProfileForReview::class)($profile);
    expect($revision->refresh()->status)->toBe(ProfileStatus::PendingReview);

    app(RequestProfileChanges::class)($revision, $admin, 'Add more detail.');
    expect($revision->refresh()->status)->toBe(ProfileStatus::ChangesRequested);

    saveProfile($profile->refresh(), ['about' => str_repeat('A much more detailed section. ', 4)]);
    expect($revision->refresh()->status)->toBe(ProfileStatus::ChangesRequested);

    app(SubmitProfileForReview::class)($profile);
    app(ApproveProfile::class)($revision->refresh(), $admin);

    $revision->refresh();

    expect($revision->status)->toBe(ProfileStatus::Published)
        ->and($revision->last_change_request)->toBeNull()
        ->and($revision->reviews)->toHaveCount(2)
        ->and($profile->refresh()->about)->toBe(str_repeat('A much more detailed section. ', 4));
});

it('rejects submitting an incomplete revision and says what is missing', function () {
    $profile = FreelancerProfile::factory()->create();
    FreelancerProfileRevision::factory()->draft()->create([
        'freelancer_profile_id' => $profile->id,
        'payload' => ['about' => 'short'],
    ]);

    try {
        app(SubmitProfileForReview::class)($profile);
        $this->fail('Expected a validation exception.');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toEqualCanonicalizing([
            'professional_title', 'about', 'location', 'professional_function_id', 'role_ids', 'skill_ids', 'tool_ids',
            'hourly_rate', 'offers_monthly_retainers', 'weekly_hours', 'open_to_full_time',
        ]);
    }
});

it('keeps serving the approved version until a later edit is approved', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $profile = profileWithRevision();
    app(ApproveProfile::class)($profile->openRevision, $admin);
    $profile->refresh();
    $approvedAbout = $profile->about;

    $edit = saveProfile($profile, ['about' => str_repeat('Edited about section. ', 4)]);

    $profile->refresh();

    expect($profile->about)->toBe($approvedAbout)
        ->and($profile->displayStatus())->toBe(ProfileStatus::Draft)
        ->and($edit->content()->get('about'))->toBe(str_repeat('Edited about section. ', 4))
        ->and($profile->revisions)->toHaveCount(2);

    app(SubmitProfileForReview::class)($profile);

    expect($profile->refresh()->about)->toBe($approvedAbout);

    app(ApproveProfile::class)($edit->refresh(), $admin);

    expect($profile->refresh()->about)->toBe(str_repeat('Edited about section. ', 4))
        ->and($profile->displayStatus())->toBe(ProfileStatus::Published);
});

it('drops the revision when edits bring a published profile back to what is live', function () {
    Notification::fake();
    $profile = profileWithRevision();
    app(ApproveProfile::class)($profile->openRevision, User::factory()->admin()->create());
    $profile->refresh();
    $original = $profile->professional_title;

    expect(saveProfile($profile, ['professional_title' => 'Temporary']))->not->toBeNull()
        ->and($profile->refresh()->displayStatus())->toBe(ProfileStatus::Draft);

    expect(saveProfile($profile->refresh(), ['professional_title' => $original]))->toBeNull()
        ->and($profile->refresh()->openRevision)->toBeNull()
        ->and($profile->displayStatus())->toBe(ProfileStatus::Published);
});

it('allows only one open revision per profile', function () {
    $profile = profileWithRevision('draft');

    expect(fn () => FreelancerProfileRevision::factory()->pendingReview()->create(['freelancer_profile_id' => $profile->id]))
        ->toThrow(QueryException::class);

    FreelancerProfileRevision::factory()->applied()->create(['freelancer_profile_id' => $profile->id]);
    FreelancerProfileRevision::factory()->applied()->create(['freelancer_profile_id' => $profile->id]);

    expect($profile->revisions()->count())->toBe(3);
});

it('keeps the first publication date and moves the content update date on later approvals', function () {
    Notification::fake();
    $this->freezeTime();
    $admin = User::factory()->admin()->create();
    $profile = profileWithRevision();
    app(ApproveProfile::class)($profile->openRevision, $admin);
    $profile->refresh();
    $firstPublishedAt = $profile->published_at->toDateTimeString();

    $this->travel(3)->days();

    // Only skills and tools change, so no column of the profile row itself does.
    $edit = saveProfile($profile, ['skill_ids' => Skill::factory()->count(3)->create()->modelKeys(), 'tool_ids' => Tool::factory()->count(2)->create()->modelKeys()]);
    app(SubmitProfileForReview::class)($profile);
    app(ApproveProfile::class)($edit->refresh(), $admin);

    $profile->refresh();

    expect($profile->published_at->toDateTimeString())->toBe($firstPublishedAt)
        ->and($profile->content_updated_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($profile->content_updated_at->isAfter($profile->published_at))->toBeTrue()
        ->and($profile->created_at->toDateTimeString())->toBe($firstPublishedAt);
});

it('keeps approved revisions as history and opens a fresh one for the next edit', function () {
    Notification::fake();
    $profile = profileWithRevision();
    $first = $profile->openRevision;
    app(ApproveProfile::class)($first, User::factory()->admin()->create());

    $second = saveProfile($profile->refresh(), ['location' => 'Lviv, Ukraine']);

    expect($second->id)->not->toBe($first->id)
        ->and($first->refresh()->status)->toBe(ProfileStatus::Published)
        ->and($first->content()->get('location'))->not->toBe('Lviv, Ukraine')
        ->and($profile->revisions()->count())->toBe(2);
});

it('leaves admin-controlled fields untouched when a revision is approved', function () {
    Notification::fake();
    $profile = FreelancerProfile::factory()->create(['score' => 7000]);
    FreelancerProfileRevision::factory()->pendingReview()->create(['freelancer_profile_id' => $profile->id]);

    app(ApproveProfile::class)($profile->refresh()->openRevision, User::factory()->admin()->create());

    $profile->refresh();

    expect($profile->score)->toBe(7000);
});

it('ignores catalog entries that were deleted while the revision was open', function () {
    Notification::fake();
    $profile = profileWithRevision();
    $revision = $profile->openRevision;
    $content = $revision->content();
    $keptSkill = Skill::factory()->create();
    $keptRole = ProfessionalRole::factory()->create();
    $revision->forceFill(['payload' => $content->with([
        'skill_ids' => [...$content->skillIds(), $keptSkill->id],
        'role_ids' => [...$content->roleIds(), $keptRole->id],
    ])->toArray()])->save();

    Skill::query()->whereIn('id', $content->skillIds())->delete();
    ProfessionalFunction::query()->whereKey($content->professionalFunctionId())->delete();
    ProfessionalRole::query()->whereKey($content->roleIds()[0])->delete();

    app(ApproveProfile::class)($revision->refresh(), User::factory()->admin()->create());

    $profile->refresh();

    expect($profile->skills->pluck('id')->all())->toBe([$keptSkill->id])
        ->and($profile->professional_function_id)->toBeNull()
        ->and($profile->main_role_id)->toBe($keptRole->id)
        ->and($profile->secondary_role_id)->toBeNull();
});

it('fills the main, secondary and third role in the order the roles were picked', function () {
    Notification::fake();
    $profile = profileWithRevision('draft');
    [$first, $second, $third] = ProfessionalRole::factory()->count(3)->create();
    saveProfile($profile, ['role_ids' => [$third->id, $first->id, $second->id]]);
    app(SubmitProfileForReview::class)($profile);

    app(ApproveProfile::class)($profile->refresh()->openRevision, User::factory()->admin()->create());

    $profile->refresh();

    expect($profile->main_role_id)->toBe($third->id)
        ->and($profile->secondary_role_id)->toBe($first->id)
        ->and($profile->third_role_id)->toBe($second->id)
        ->and($profile->liveContent()->roleIds())->toBe([$third->id, $first->id, $second->id]);
});

it('keeps the live photo until a replacement has been approved', function () {
    Notification::fake();
    Storage::fake('public');
    $profile = profileWithRevision();
    Storage::disk('public')->put('freelancer-photos/live.jpg', 'live');
    Storage::disk('public')->put('freelancer-photos/new.jpg', 'new');
    $profile->openRevision->forceFill(['payload' => $profile->openRevision->content()->with(['photo_path' => 'freelancer-photos/live.jpg'])->toArray()])->save();
    app(ApproveProfile::class)($profile->openRevision, User::factory()->admin()->create());
    $profile->refresh();

    $edit = saveProfile($profile, ['photo_path' => 'freelancer-photos/new.jpg']);

    Storage::disk('public')->assertExists(['freelancer-photos/live.jpg', 'freelancer-photos/new.jpg']);
    expect($profile->refresh()->photo_path)->toBe('freelancer-photos/live.jpg');

    app(SubmitProfileForReview::class)($profile);
    app(ApproveProfile::class)($edit->refresh(), User::factory()->admin()->create());

    Storage::disk('public')->assertMissing('freelancer-photos/live.jpg');
    Storage::disk('public')->assertExists('freelancer-photos/new.jpg');
    expect($profile->refresh()->photo_path)->toBe('freelancer-photos/new.jpg');
});

it('deletes a replaced photo that was only ever in the draft', function () {
    Storage::fake('public');
    $profile = profileWithRevision('draft');
    Storage::disk('public')->put('freelancer-photos/first.jpg', 'first');
    Storage::disk('public')->put('freelancer-photos/second.jpg', 'second');

    saveProfile($profile, ['photo_path' => 'freelancer-photos/first.jpg']);
    saveProfile($profile->refresh(), ['photo_path' => 'freelancer-photos/second.jpg']);

    Storage::disk('public')->assertMissing('freelancer-photos/first.jpg');
    Storage::disk('public')->assertExists('freelancer-photos/second.jpg');
});

it('discards pending edits and the files they introduced', function () {
    Notification::fake();
    Storage::fake('public');
    $profile = profileWithRevision();
    app(ApproveProfile::class)($profile->openRevision, User::factory()->admin()->create());
    $profile->refresh();
    Storage::disk('public')->put('freelancer-photos/draft.jpg', 'draft');
    saveProfile($profile, ['photo_path' => 'freelancer-photos/draft.jpg', 'about' => str_repeat('Draft about. ', 6)]);

    app(DiscardProfileRevision::class)($profile->refresh());

    expect($profile->refresh()->openRevision)->toBeNull()
        ->and($profile->displayStatus())->toBe(ProfileStatus::Published);
    Storage::disk('public')->assertMissing('freelancer-photos/draft.jpg');
});

it('refuses to discard when nothing is live, nothing is open, or the profile is under review', function () {
    Notification::fake();

    $neverPublished = profileWithRevision('draft');
    expect(fn () => app(DiscardProfileRevision::class)($neverPublished))->toThrow(DomainException::class);

    $published = FreelancerProfile::factory()->published()->create();
    expect(fn () => app(DiscardProfileRevision::class)($published))->toThrow(DomainException::class);

    $underReview = FreelancerProfile::factory()->published()->withPendingRevision()->create();
    expect(fn () => app(DiscardProfileRevision::class)($underReview))->toThrow(DomainException::class);
});
