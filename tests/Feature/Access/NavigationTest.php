<?php

use App\Models\User;

it('shows the Freelancers tab to brands only', function (string $state, bool $visible) {
    $user = match ($state) {
        'free' => User::factory()->client()->create(),
        'paid' => User::factory()->client()->paid()->create(),
        'freelancer' => User::factory()->freelancer()->create(),
        'admin' => User::factory()->admin()->create(),
    };

    $page = $state === 'freelancer' ? route('freelancer.dashboard') : route('settings.profile');

    $response = $this->actingAs($user)->get($page)->assertOk();
    $link = 'href="'.route('directory.index').'"';

    $visible ? $response->assertSee($link, false) : $response->assertDontSee($link, false);
})->with([
    'free client' => ['free', true],
    'paid client' => ['paid', true],
    'freelancer' => ['freelancer', false],
    'admin' => ['admin', false],
]);

it('groups the profile pages of a freelancer under Profile settings', function () {
    $freelancer = User::factory()->freelancer()->create();

    $this->actingAs($freelancer)->get(route('freelancer.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Dashboard', 'Profile settings', 'My profile', 'Highlights', 'Services', 'Consultations', 'Preview']);
});

it('does not give clients the Profile settings group', function () {
    $this->actingAs(User::factory()->client()->create())->get(route('client.dashboard'))
        ->assertOk()
        ->assertDontSee('Profile settings');
});

it('orders the brand tabs and calls the last one Brand settings', function () {
    $this->actingAs(User::factory()->client()->create())->get(route('client.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Dashboard', 'Freelancers', 'Calls', 'Projects', 'Favourites', 'Recommended', 'Brand settings'])
        ->assertDontSee('Brand profile</');
});
