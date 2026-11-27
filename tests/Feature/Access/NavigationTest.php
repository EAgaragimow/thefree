<?php

use App\Models\FreelancerProfile;
use App\Models\User;

function headerLinks(string $html): string
{
    preg_match('#<header.*?</header>#s', $html, $matches);

    return $matches[0] ?? '';
}

it('shows guests the way in and no account links', function () {
    $header = headerLinks($this->get(route('home'))->assertOk()->getContent());

    expect($header)
        ->toContain('Become a Freelancer')
        ->toContain('Log in')
        ->toContain('Sign up')
        ->not->toContain('Dashboard')
        ->not->toContain('Messages')
        ->not->toContain('Log out');
});

it('shows a brand its pages and drops the sign-in links', function (string $state) {
    $user = $state === 'paid' ? User::factory()->client()->paid()->create() : User::factory()->client()->create();

    $response = $this->actingAs($user)->get(route('client.dashboard'))->assertOk();
    $header = headerLinks($response->getContent());

    expect($header)
        ->not->toContain('Become a Freelancer')
        ->not->toContain('Log in')
        ->not->toContain('Sign up')
        ->toContain('Brand settings')
        ->toContain('Log out');

    $response->assertSeeInOrder(['Dashboard', 'Messages', 'Freelancers', 'Blog'])
        ->assertSeeHtml('href="'.route('directory.index').'"')
        ->assertSeeHtml('href="'.route('blog.index').'"')
        ->assertSeeHtml('href="'.route('messages.index').'"');
})->with(['free', 'paid']);

it('shows a freelancer the Dashboard, Profile and Messages only', function () {
    $response = $this->actingAs(User::factory()->freelancer()->create())->get(route('freelancer.dashboard'))->assertOk();
    $header = headerLinks($response->getContent());

    $response->assertSeeInOrder(['Dashboard', 'Profile', 'Messages'])
        ->assertSeeHtml('href="'.route('freelancer.preview').'"');

    expect($header)
        ->not->toContain('Freelancers')
        ->not->toContain('Blog')
        ->not->toContain('Brand settings')
        ->not->toContain('Become a Freelancer')
        ->toContain('Log out');

    $response->assertDontSeeHtml('href="'.route('directory.index').'"')
        ->assertDontSeeHtml('href="'.route('blog.index').'"');
});

it('shows an admin the admin panel and every public page', function () {
    $response = $this->actingAs(User::factory()->admin()->create())->get(route('messages.index'))->assertOk();

    $response->assertSeeInOrder(['Admin panel', 'Freelancers', 'Blog', 'Messages'])
        ->assertSeeHtml('href="'.url('/admin').'"')
        ->assertSeeHtml('href="'.route('directory.index').'"')
        ->assertSeeHtml('href="'.route('blog.index').'"');

    expect(headerLinks($response->getContent()))->not->toContain('Become a Freelancer');
});

it('keeps the settings and log out in the account menu of every role', function (string $role) {
    $response = $this->actingAs(User::factory()->{$role}()->create())->get(route('messages.index'))->assertOk();

    $response->assertSeeHtml('href="'.route('settings.profile').'"')
        ->assertSee('Log out');
})->with(['client', 'freelancer', 'admin']);

it('gives the brand dashboard a sticky tab bar of anchors', function () {
    $this->actingAs(User::factory()->client()->create())->get(route('client.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Overview', 'Calls', 'Projects', 'Messages'])
        ->assertSeeHtml('href="#overview"')
        ->assertSeeHtml('href="#calls"')
        ->assertSeeHtml('href="#projects"')
        ->assertSeeHtml('href="#messages"')
        ->assertSeeHtml('sticky top-0')
        ->assertSeeHtml('id="messages"');
});

it('keeps Favourites and Recommended in the account menu of a brand, not in the tab bar', function () {
    $response = $this->actingAs(User::factory()->client()->create())->get(route('client.dashboard'))->assertOk();

    $nav = preg_match('#<nav[^>]*aria-label="Sections".*?</nav>#s', $response->getContent(), $matches) === 1 ? $matches[0] : '';

    expect($nav)->not->toContain('Favourites')->not->toContain('Recommended');

    $response->assertSeeHtml('href="'.route('client.favorites').'"')
        ->assertSeeHtml('href="'.route('client.recommended').'"')
        ->assertSeeHtml('href="'.route('client.brand-profile').'"');
});

it('puts Profile settings in the account menu of a freelancer', function () {
    $this->actingAs(FreelancerProfile::factory()->published()->create()->user)->get(route('freelancer.dashboard'))
        ->assertOk()
        ->assertSee('Profile settings')
        ->assertSeeHtml('href="'.route('freelancer.profile').'"');
});

it('has no tab bar outside the two dashboards', function () {
    $client = User::factory()->client()->create();

    foreach (['blog.index', 'messages.index', 'client.favorites', 'client.recommended', 'client.brand-profile', 'directory.index'] as $route) {
        $this->actingAs($client)->get(route($route))->assertOk()->assertDontSee('aria-label="Sections"', false);
    }

    $freelancer = FreelancerProfile::factory()->published()->create()->user;

    foreach (['freelancer.preview', 'freelancer.profile', 'messages.index'] as $route) {
        $this->actingAs($freelancer)->get(route($route))->assertOk()->assertDontSee('aria-label="Sections"', false);
    }
});

it('has a footer on every page', function () {
    $this->get(route('home'))->assertSee('All rights reserved.');

    $this->actingAs(User::factory()->client()->create())->get(route('client.dashboard'))->assertSee('All rights reserved.');
});

it('opens the messages page for every role and sends guests to log in', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create())->get(route('messages.index'))
        ->assertOk()
        ->assertSee('Messages');
})->with(['client', 'freelancer', 'admin']);

it('sends a guest from the messages page to the login page', function () {
    $this->get(route('messages.index'))->assertRedirect(route('login'));
});
