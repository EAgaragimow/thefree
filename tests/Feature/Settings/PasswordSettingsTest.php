<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('changes the password when the current password is correct', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.password')
        ->set('current_password', 'password')
        ->set('password', 'a-New-passw0rd!')
        ->set('password_confirmation', 'a-New-passw0rd!')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('saved', true);

    expect(Hash::check('a-New-passw0rd!', $user->fresh()->password))->toBeTrue();
});

it('rejects a wrong current password', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.password')
        ->set('current_password', 'not-my-password')
        ->set('password', 'a-New-passw0rd!')
        ->set('password_confirmation', 'a-New-passw0rd!')
        ->call('save')
        ->assertHasErrors('current_password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('rejects a new password that does not match its confirmation', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.password')
        ->set('current_password', 'password')
        ->set('password', 'a-New-passw0rd!')
        ->set('password_confirmation', 'different')
        ->call('save')
        ->assertHasErrors('password');
});
