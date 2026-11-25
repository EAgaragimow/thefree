<?php

use App\Models\FreelancerProfile;
use Illuminate\Console\Scheduling\Schedule;

it('clears the newbie flag once a profile is a month old', function () {
    $fresh = FreelancerProfile::factory()->newbie()->create(['created_at' => now()->subDays(29)]);
    $due = FreelancerProfile::factory()->newbie()->create(['created_at' => now()->subMonth()->subMinute()]);
    $old = FreelancerProfile::factory()->newbie()->create(['created_at' => now()->subMonths(5)]);
    $veteran = FreelancerProfile::factory()->create(['created_at' => now()->subMonths(5)]);

    $this->artisan('app:expire-newbie-profiles')
        ->expectsOutputToContain('Cleared the newbie flag of 2 profile(s).')
        ->assertSuccessful();

    expect($fresh->refresh()->is_newbie)->toBeTrue()
        ->and($due->refresh()->is_newbie)->toBeFalse()
        ->and($old->refresh()->is_newbie)->toBeFalse()
        ->and($veteran->refresh()->is_newbie)->toBeFalse();
});

it('runs every day', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains($event->command, 'app:expire-newbie-profiles'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *');
});
