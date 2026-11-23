<?php

use App\Models\BrandProfile;
use App\Models\User;
use Livewire\Livewire;

it('shows a free client the upgrade call to action', function () {
    Livewire::actingAs(User::factory()->client()->create())
        ->test('pages::client.dashboard')
        ->assertSee('Upgrade to the Paid Plan');
});

it('does not show a paid client the upgrade call to action', function () {
    Livewire::actingAs(User::factory()->client()->paid()->create())
        ->test('pages::client.dashboard')
        ->assertDontSee('Upgrade to the Paid Plan');
});

it('asks a client without a complete brand profile to fill it in', function () {
    Livewire::actingAs(User::factory()->client()->create())
        ->test('pages::client.dashboard')
        ->assertSee('Complete your brand profile');
});

it('stops prompting once the brand profile is complete', function () {
    $user = User::factory()->client()->create();
    BrandProfile::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::client.dashboard')
        ->assertDontSee('Complete your brand profile');
});
