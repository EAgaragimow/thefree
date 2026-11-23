<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;

it('creates a verified administrator and audits it', function () {
    $this->artisan('app:create-admin', [
        '--name' => 'Root Admin',
        '--email' => 'root@example.com',
        '--password' => 'a-Strong-passw0rd!',
    ])->assertSuccessful();

    $admin = User::where('email', 'root@example.com')->firstOrFail();

    expect($admin->role)->toBe(UserRole::Admin)
        ->and($admin->hasVerifiedEmail())->toBeTrue()
        ->and($admin->canAccessPanel(Filament\Facades\Filament::getPanel('admin')))->toBeTrue()
        ->and(AuditLog::where('action', 'admin.created')->where('subject_id', $admin->id)->exists())->toBeTrue();
});

it('refuses an email address that is already taken', function () {
    User::factory()->create(['email' => 'root@example.com']);

    $this->artisan('app:create-admin', [
        '--name' => 'Root Admin',
        '--email' => 'root@example.com',
        '--password' => 'a-Strong-passw0rd!',
    ])->assertFailed();

    expect(User::where('role', UserRole::Admin)->count())->toBe(0);
});

it('refuses a weak password', function () {
    $this->artisan('app:create-admin', [
        '--name' => 'Root Admin',
        '--email' => 'root@example.com',
        '--password' => 'short',
    ])->assertFailed();

    expect(User::where('email', 'root@example.com')->exists())->toBeFalse();
});
