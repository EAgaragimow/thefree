<?php

use App\Enums\ProfileStatus;
use App\Filament\Resources\FreelancerProfiles\FreelancerProfileResource;
use App\Filament\Resources\ProfileRevisions\Pages\ListProfileRevisions;
use App\Filament\Resources\ProfileRevisions\Pages\ViewProfileRevision;
use App\Filament\Resources\ProfileRevisions\ProfileRevisionResource;
use App\Models\AuditLog;
use App\Models\FreelancerProfile;
use App\Models\FreelancerProfileRevision;
use App\Models\User;
use App\Notifications\ProfileChangesRequested;
use App\Notifications\ProfilePublished;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

function pendingRevision(): FreelancerProfileRevision
{
    return FreelancerProfileRevision::factory()->pendingReview()->create();
}

it('lists revisions that are pending review by default', function () {
    $pending = pendingRevision();
    $draft = FreelancerProfileRevision::factory()->draft()->create();

    Livewire::test(ListProfileRevisions::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$draft]);
});

it('approves and publishes a revision', function () {
    Notification::fake();
    $revision = pendingRevision();

    Livewire::test(ListProfileRevisions::class)
        ->callAction(TestAction::make('approve')->table($revision), data: ['comment' => 'Looks great'])
        ->assertNotified('Profile approved and published.');

    $profile = $revision->profile->refresh();

    expect($revision->refresh()->status)->toBe(ProfileStatus::Published)
        ->and($profile->isPublished())->toBeTrue()
        ->and($profile->professional_title)->toBe($revision->content()->get('professional_title'));
    Notification::assertSentTo($profile->user, ProfilePublished::class);
    expect(AuditLog::where('action', 'profile.approved')->where('actor_id', $this->admin->id)->exists())->toBeTrue();
});

it('requires a comment when requesting changes', function () {
    Notification::fake();
    $revision = pendingRevision();

    Livewire::test(ListProfileRevisions::class)
        ->callAction(TestAction::make('requestChanges')->table($revision), data: ['comment' => ''])
        ->assertHasFormErrors(['comment' => 'required']);

    expect($revision->refresh()->status)->toBe(ProfileStatus::PendingReview);
});

it('returns the revision to the freelancer when changes are requested', function () {
    Notification::fake();
    $revision = pendingRevision();

    Livewire::test(ListProfileRevisions::class)
        ->callAction(TestAction::make('requestChanges')->table($revision), data: ['comment' => 'Add a portfolio link.'])
        ->assertNotified('Changes requested.');

    expect($revision->refresh())
        ->status->toBe(ProfileStatus::ChangesRequested)
        ->last_change_request->toBe('Add a portfolio link.');
    Notification::assertSentTo($revision->profile->user, ProfileChangesRequested::class);
});

it('does not offer review actions for revisions that are not pending review', function () {
    $draft = FreelancerProfileRevision::factory()->draft()->create();

    Livewire::test(ListProfileRevisions::class)
        ->filterTable('status', ProfileStatus::Draft->value)
        ->assertActionHidden(TestAction::make('approve')->table($draft))
        ->assertActionHidden(TestAction::make('requestChanges')->table($draft));
});

it('shows which fields a revision changes compared to the live profile', function () {
    $profile = FreelancerProfile::factory()->published()->create(['professional_title' => 'Old title']);
    $revision = FreelancerProfileRevision::factory()->pendingReview()->create([
        'freelancer_profile_id' => $profile->id,
        'payload' => $profile->liveContent()->with(['professional_title' => 'New title'])->toArray(),
    ]);

    Livewire::test(ViewProfileRevision::class, ['record' => $revision->getKey()])
        ->assertSuccessful()
        ->assertSee('Professional title')
        ->assertSee('New title');
});

it('lets an administrator view a live profile', function () {
    $profile = FreelancerProfile::factory()->published()->create();

    $this->get(FreelancerProfileResource::getUrl('view', ['record' => $profile]))->assertOk();
});

it('keeps non-admins out of the profile review queue', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    $this->get(ProfileRevisionResource::getUrl('index'))->assertForbidden();
})->with(['client', 'freelancer']);
