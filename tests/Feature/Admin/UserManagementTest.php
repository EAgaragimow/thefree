<?php

use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Actions\Testing\TestAction;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('lists users and filters those who requested data deletion', function () {
    $requested = User::factory()->create(['deletion_requested_at' => now()]);
    $other = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$requested, $other])
        ->filterTable('deletion_requested')
        ->assertCanSeeTableRecords([$requested])
        ->assertCanNotSeeTableRecords([$other]);
});

it('suspends a user with a reason and audits it', function () {
    $user = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('suspend')->table($user), data: ['reason' => 'Abusive behaviour'])
        ->assertNotified('Account suspended.');

    expect($user->refresh()->status)->toBe(UserStatus::Suspended);

    $log = AuditLog::where('action', 'user.status_changed')->firstOrFail();
    expect($log->actor_id)->toBe($this->admin->id)
        ->and($log->subject_id)->toBe($user->id)
        ->and($log->reason)->toBe('Abusive behaviour')
        ->and($log->metadata)->toBe(['from' => 'active', 'to' => 'suspended']);
});

it('requires a reason to change a user status', function () {
    $user = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('suspend')->table($user), data: ['reason' => ''])
        ->assertHasFormErrors(['reason' => 'required']);

    expect($user->refresh()->status)->toBe(UserStatus::Active);
    expect(AuditLog::count())->toBe(0);
});

it('restricts and then reinstates a user', function () {
    $user = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('restrict')->table($user), data: ['reason' => 'Under investigation'])
        ->assertNotified('Account restricted.');
    expect($user->refresh()->status)->toBe(UserStatus::Restricted);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('reinstate')->table($user), data: ['reason' => 'Cleared'])
        ->assertNotified('Account active.');
    expect($user->refresh()->status)->toBe(UserStatus::Active);
});

it('does not let an admin change the status of their own account', function () {
    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('suspend')->table($this->admin))
        ->assertActionHidden(TestAction::make('deleteAccount')->table($this->admin));
});

it('soft-deletes a user with an audited reason and can restore them', function () {
    $user = User::factory()->create(['deletion_requested_at' => now()]);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('deleteAccount')->table($user), data: ['reason' => 'Fulfilling deletion request'])
        ->assertNotified('Account deleted.');

    expect(User::find($user->id))->toBeNull();

    $log = AuditLog::where('action', 'user.deleted')->firstOrFail();
    expect($log->reason)->toBe('Fulfilling deletion request')->and($log->metadata['deletion_requested_by_user'])->toBeTrue();

    Livewire::test(ListUsers::class)
        ->filterTable('trashed', true)
        ->callAction(TestAction::make('restoreAccount')->table($user), data: ['reason' => 'Requested by mistake'])
        ->assertNotified('Account restored.');

    expect(User::find($user->id))->not->toBeNull();
    expect(AuditLog::where('action', 'user.restored')->exists())->toBeTrue();
});

it('keeps non-admins out of user management', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    $this->get('/admin/users')->assertForbidden();
})->with(['client', 'freelancer']);
