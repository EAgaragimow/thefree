<?php

use App\Actions\Applications\ApproveApplication;
use App\Actions\Applications\RejectApplication;
use App\Actions\Applications\ResendInvitation;
use App\Enums\ApplicationStatus;
use App\Models\AuditLog;
use App\Models\FreelancerApplication;
use App\Models\FreelancerProfile;
use App\Models\User;
use App\Notifications\ApplicationRejected;
use App\Notifications\FreelancerInvitation;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

it('approves a pending application and emails a single-use invitation', function () {
    Notification::fake();
    $this->freezeTime();
    $admin = User::factory()->admin()->create();
    $application = FreelancerApplication::factory()->create();

    app(ApproveApplication::class)($application, $admin, 'Strong portfolio');

    $application->refresh();

    expect($application->status)->toBe(ApplicationStatus::Approved)
        ->and($application->reviewed_by)->toBe($admin->id)
        ->and($application->invitation_token_hash)->not->toBeNull()
        ->and($application->invitation_expires_at->toDateTimeString())->toBe(now()->addDays(7)->toDateTimeString());

    Notification::assertSentTo($application, FreelancerInvitation::class, function (FreelancerInvitation $notification) use ($application) {
        return hash('sha256', $notification->token) === $application->invitation_token_hash;
    });
});

it('does not create a user or publish anything when approving', function () {
    Notification::fake();

    app(ApproveApplication::class)(FreelancerApplication::factory()->create(), User::factory()->admin()->create());

    expect(User::where('role', 'freelancer')->count())->toBe(0)
        ->and(FreelancerProfile::count())->toBe(0);
});

it('records the approval in the audit log with the admin as actor', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $application = FreelancerApplication::factory()->create();

    app(ApproveApplication::class)($application, $admin, 'Looks great');

    $log = AuditLog::firstOrFail();

    expect($log->action)->toBe('application.approved')
        ->and($log->actor_id)->toBe($admin->id)
        ->and($log->subject_type)->toBe($application->getMorphClass())
        ->and($log->subject_id)->toBe($application->id)
        ->and($log->reason)->toBe('Looks great');
});

it('refuses to approve an application that was already decided', function (string $state) {
    Notification::fake();
    $application = FreelancerApplication::factory()->{$state}()->create();

    expect(fn () => app(ApproveApplication::class)($application, User::factory()->admin()->create()))
        ->toThrow(DomainException::class);

    Notification::assertNothingSent();
})->with(['approved', 'rejected']);

it('rejects an application with a mandatory reason and notifies the applicant', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $application = FreelancerApplication::factory()->create();

    app(RejectApplication::class)($application, $admin, 'Portfolio is too thin.');

    expect($application->refresh())
        ->status->toBe(ApplicationStatus::Rejected)
        ->rejection_reason->toBe('Portfolio is too thin.');

    Notification::assertSentTo($application, ApplicationRejected::class, fn (ApplicationRejected $notification) => $notification->reason === 'Portfolio is too thin.');
    expect(AuditLog::firstOrFail()->action)->toBe('application.rejected');
});

it('refuses to reject an application without a reason', function () {
    Notification::fake();
    $application = FreelancerApplication::factory()->create();

    expect(fn () => app(RejectApplication::class)($application, User::factory()->admin()->create(), '   '))
        ->toThrow(ValidationException::class);

    expect($application->refresh()->status)->toBe(ApplicationStatus::Pending);
    Notification::assertNothingSent();
});

it('resends an invitation with a fresh link and invalidates the previous one', function () {
    Notification::fake();
    $application = FreelancerApplication::factory()->approved($oldToken)->create();
    $oldHash = $application->invitation_token_hash;

    app(ResendInvitation::class)($application, User::factory()->admin()->create());

    expect($application->refresh()->invitation_token_hash)->not->toBe($oldHash);
    Notification::assertSentTo($application, FreelancerInvitation::class);
});

it('refuses to resend an invitation that was already used', function () {
    Notification::fake();
    $application = FreelancerApplication::factory()->approved()->create(['activated_at' => now()]);

    expect(fn () => app(ResendInvitation::class)($application, User::factory()->admin()->create()))
        ->toThrow(DomainException::class);
});
