<?php

use App\Models\User;
use Livewire\Livewire;

it('sends guests to the login page', function () {
    $profile = directoryProfile();

    $this->get(route('directory.index'))->assertRedirect(route('login'));
    $this->get(route('directory.show', $profile))->assertRedirect(route('login'));
});

it('lets every signed-in role open the directory', function (string $state) {
    $user = match ($state) {
        'free' => User::factory()->client()->create(),
        'paid' => User::factory()->client()->paid()->create(),
        'payment-restricted' => User::factory()->client()->paymentRestricted()->create(),
        'freelancer' => User::factory()->freelancer()->create(),
        'admin' => User::factory()->admin()->create(),
    };

    $this->actingAs($user)->get(route('directory.index'))->assertOk();
})->with(['free', 'paid', 'payment-restricted', 'freelancer', 'admin']);

it('opens full profiles for paid clients, freelancers and admins', function (string $state) {
    $profile = directoryProfile(['professional_title' => 'Senior Laravel developer'], ['name' => 'Ada Lovelace']);

    $user = match ($state) {
        'paid' => User::factory()->client()->paid()->create(),
        'freelancer' => User::factory()->freelancer()->create(),
        'admin' => User::factory()->admin()->create(),
    };

    $this->actingAs($user)->get(route('directory.show', $profile))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('Senior Laravel developer');
})->with(['paid', 'freelancer', 'admin']);

it('keeps full profiles from free and payment-restricted clients', function (string $state) {
    $profile = directoryProfile(['professional_title' => 'Senior Laravel developer']);

    $user = $state === 'free'
        ? User::factory()->client()->create()
        : User::factory()->client()->paymentRestricted()->create();

    $this->actingAs($user)->get(route('directory.show', $profile))
        ->assertForbidden()
        ->assertDontSee('Senior Laravel developer');
})->with(['free', 'payment-restricted']);

it('does not show a profile that is not live', function (string $case) {
    $profile = match ($case) {
        'never published' => directoryProfile(['published_at' => null]),
        'suspended' => directoryProfile(user: ['status' => 'suspended']),
        'restricted' => directoryProfile(user: ['status' => 'restricted']),
        'deactivated' => directoryProfile(user: ['deactivated_at' => now()]),
        'deleted' => tap(directoryProfile(), fn ($profile) => $profile->user->delete()),
    };

    $this->actingAs(User::factory()->client()->paid()->create())
        ->get(route('directory.show', $profile))
        ->assertNotFound();
})->with(['never published', 'suspended', 'restricted', 'deactivated', 'deleted']);

it('answers 404 for a profile that does not exist', function () {
    $this->actingAs(User::factory()->client()->paid()->create())
        ->get(route('directory.show', 99999))
        ->assertNotFound();
});

it('gives a free client a preview without links or rates', function () {
    $profile = directoryProfile(['professional_title' => 'Brand designer', 'hourly_rate_cents' => 12300], ['name' => 'Grace Hopper']);

    $this->actingAs(User::factory()->client()->create())
        ->get(route('directory.index'))
        ->assertOk()
        ->assertSee('Grace Hopper')
        ->assertSee('Brand designer')
        ->assertSee('Upgrade to the Paid Plan')
        ->assertDontSee(route('directory.show', $profile))
        ->assertDontSee('$123/h')
        ->assertDontSee('Search');
});

it('gives a paid client links to profiles and the filters', function () {
    $profile = directoryProfile(['hourly_rate_cents' => 12300], ['name' => 'Grace Hopper']);

    $this->actingAs(User::factory()->client()->paid()->create())
        ->get(route('directory.index'))
        ->assertOk()
        ->assertSee('Grace Hopper')
        ->assertSee(route('directory.show', $profile))
        ->assertSee('$123/h')
        ->assertSee('Search');
});

it('ignores filters sent by a free client', function () {
    directoryProfile(user: ['name' => 'Grace Hopper']);
    directoryProfile(user: ['name' => 'Alan Turing']);

    Livewire::actingAs(User::factory()->client()->create())
        ->test('pages::directory.index')
        ->set('search', 'Grace')
        ->assertSee('Grace Hopper')
        ->assertSee('Alan Turing');
});
