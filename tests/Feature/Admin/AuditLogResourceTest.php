<?php

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
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
