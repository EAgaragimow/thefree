<?php

use App\Enums\ApplicationStatus;
use App\Filament\Resources\FreelancerApplications\Pages\ListFreelancerApplications;
use App\Filament\Resources\FreelancerApplications\Pages\ViewFreelancerApplication;
use App\Models\AuditLog;
use App\Models\FreelancerApplication;
use App\Models\User;
use App\Notifications\ApplicationRejected;
use App\Notifications\FreelancerInvitation;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('lists pending applications by default', function () {
    $pending = FreelancerApplication::factory()->create();
    $rejected = FreelancerApplication::factory()->rejected()->create();

    Livewire::test(ListFreelancerApplications::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$rejected]);
});

it('approves an application from the table, notifies the applicant and audits it', function () {
    Notification::fake();
    $application = FreelancerApplication::factory()->create();

    Livewire::test(ListFreelancerApplications::class)
        ->callAction(TestAction::make('approve')->table($application), data: ['note' => 'Great portfolio'])
        ->assertNotified('Application approved and invitation sent.');

    expect($application->refresh()->status)->toBe(ApplicationStatus::Approved);
    Notification::assertSentTo($application, FreelancerInvitation::class);
    expect(AuditLog::where('action', 'application.approved')->where('actor_id', $this->admin->id)->exists())->toBeTrue();
});

it('requires a reason to reject an application', function () {
    Notification::fake();
    $application = FreelancerApplication::factory()->create();

    Livewire::test(ListFreelancerApplications::class)
        ->callAction(TestAction::make('reject')->table($application), data: ['reason' => ''])
        ->assertHasFormErrors(['reason' => 'required']);

    expect($application->refresh()->status)->toBe(ApplicationStatus::Pending);
    Notification::assertNothingSent();
});

it('rejects an application with a reason and records it', function () {
    Notification::fake();
    $application = FreelancerApplication::factory()->create();

    Livewire::test(ListFreelancerApplications::class)
        ->callAction(TestAction::make('reject')->table($application), data: ['reason' => 'Not enough experience.'])
        ->assertNotified('Application rejected.');

    expect($application->refresh()->status)->toBe(ApplicationStatus::Rejected);
    Notification::assertSentTo($application, ApplicationRejected::class);

    $log = AuditLog::where('action', 'application.rejected')->firstOrFail();
    expect($log->reason)->toBe('Not enough experience.')->and($log->actor_id)->toBe($this->admin->id);
});

it('only offers approve and reject for pending applications', function () {
    $decided = FreelancerApplication::factory()->rejected()->create();

    Livewire::test(ListFreelancerApplications::class)
        ->filterTable('status', ApplicationStatus::Rejected->value)
        ->assertActionHidden(TestAction::make('approve')->table($decided))
        ->assertActionHidden(TestAction::make('reject')->table($decided));
});

it('resends the invitation for an approved, not yet activated application', function () {
    Notification::fake();
    $application = FreelancerApplication::factory()->approved()->create();

    Livewire::test(ViewFreelancerApplication::class, ['record' => $application->id])
        ->callAction('resendInvitation')
        ->assertNotified('A new invitation has been sent.');

    Notification::assertSentTo($application, FreelancerInvitation::class);
});

it('keeps non-admins out of the applications resource', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    $this->get('/admin/freelancer-applications')->assertForbidden();
})->with(['client', 'freelancer']);
