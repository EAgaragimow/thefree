<?php

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Models\AuditLog;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('lists audit log entries newest first', function () {
    $older = AuditLog::factory()->create(['created_at' => now()->subDay()]);
    $newer = AuditLog::factory()->create(['created_at' => now()]);

    Livewire::test(ListAuditLogs::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
});

it('shows a copyable activation link when the entry has one', function () {
    $log = AuditLog::factory()->create(['metadata' => ['activation_url' => 'http://thefree.test/freelancer/activate/abc123', 'other' => 'kept']]);

    Livewire::test(ViewAuditLog::class, ['record' => $log->getKey()])
        ->assertSuccessful()
        ->assertSee('Activation link')
        ->assertSee('http://thefree.test/freelancer/activate/abc123')
        ->assertSee('kept');
});

it('does not show an activation link section for ordinary entries', function () {
    $log = AuditLog::factory()->create(['metadata' => ['other' => 'kept']]);

    Livewire::test(ViewAuditLog::class, ['record' => $log->getKey()])
        ->assertSuccessful()
        ->assertDontSee('Activation link');
});

it('is read-only', function () {
    $log = AuditLog::factory()->create();

    expect(AuditLogResource::canCreate())->toBeFalse()
        ->and(AuditLogResource::canEdit($log))->toBeFalse()
        ->and(AuditLogResource::canDelete($log))->toBeFalse();
});

it('keeps non-admins out of the audit log', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    $this->get('/admin/audit-logs')->assertForbidden();
})->with(['client', 'freelancer']);
