<?php

use App\Models\User;
use Livewire\Livewire;

it('saves notification preferences', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.account')
        ->set('notify_messages', false)
        ->set('notify_billing', false)
        ->call('savePreferences')
        ->assertSet('savedPreferences', true);

    expect($user->fresh()->notification_preferences)->toBe([
        'messages' => false,
        'projects' => true,
        'bookings' => true,
        'billing' => false,
    ]);
});

it('records a personal-data deletion request after confirming the password', function () {
    $this->freezeTime();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.account')
        ->set('deletion_password', 'password')
        ->call('requestDataDeletion')
        ->assertHasNoErrors();

    expect($user->fresh()->deletion_requested_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('does not record a deletion request when the password is wrong', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.account')
        ->set('deletion_password', 'wrong')
        ->call('requestDataDeletion')
        ->assertHasErrors('deletion_password');

    expect($user->fresh()->deletion_requested_at)->toBeNull();
});

it('lets the user cancel a deletion request', function () {
    $user = User::factory()->create(['deletion_requested_at' => now()]);

    Livewire::actingAs($user)->test('pages::settings.account')->call('cancelDataDeletion');

    expect($user->fresh()->deletion_requested_at)->toBeNull();
});

it('deactivates the account and signs the user out', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.account')
        ->set('deactivate_password', 'password')
        ->call('deactivate')
        ->assertRedirect(route('login'));

    expect($user->fresh()->isDeactivated())->toBeTrue();
    $this->assertGuest();
});

it('does not deactivate the account when the password is wrong', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.account')
        ->set('deactivate_password', 'wrong')
        ->call('deactivate')
        ->assertHasErrors('deactivate_password');

    expect($user->fresh()->isDeactivated())->toBeFalse();
});
