<?php

use App\Models\FreelancerProfile;
use App\Models\User;

dataset('role dashboards', [
    'client' => ['client', 'client.dashboard'],
    'freelancer' => ['freelancer', 'freelancer.dashboard'],
]);

it('redirects guests from protected pages to the login page', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with([
    'dashboard' => 'dashboard',
    'client dashboard' => 'client.dashboard',
    'freelancer dashboard' => 'freelancer.dashboard',
    'brand profile' => 'client.brand-profile',
    'freelancer profile' => 'freelancer.profile',
    'profile settings' => 'settings.profile',
]);

it('redirects /dashboard to the dashboard that matches the role', function (string $role, string $expectedRoute) {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route($expectedRoute));
})->with([
    'client' => ['client', 'client.dashboard'],
    'freelancer' => ['freelancer', 'freelancer.dashboard'],
    'admin' => ['admin', 'filament.admin.pages.dashboard'],
]);

it('shows each role its own dashboard', function (string $role, string $routeName) {
    $user = User::factory()->{$role}()->create();

    if ($role === 'freelancer') {
        FreelancerProfile::factory()->create(['user_id' => $user->id]);
    }

    $this->actingAs($user)->get(route($routeName))->assertOk();
})->with('role dashboards');

it('forbids a client from the freelancer area', function (string $routeName) {
    $this->actingAs(User::factory()->client()->create())->get(route($routeName))->assertForbidden();
})->with(['freelancer.dashboard', 'freelancer.profile']);

it('forbids a freelancer from the client area', function (string $routeName) {
    $this->actingAs(User::factory()->freelancer()->create())->get(route($routeName))->assertForbidden();
})->with(['client.dashboard', 'client.brand-profile']);

it('forbids an admin from the client and freelancer areas', function (string $routeName) {
    $this->actingAs(User::factory()->admin()->create())->get(route($routeName))->assertForbidden();
})->with(['client.dashboard', 'freelancer.dashboard']);

it('lets only active admins into the admin panel', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
    $this->actingAs(User::factory()->client()->create())->get('/admin')->assertForbidden();
    $this->actingAs(User::factory()->freelancer()->create())->get('/admin')->assertForbidden();
    $this->actingAs(User::factory()->admin()->restricted()->create())->get('/admin')->assertForbidden();
    $this->actingAs(User::factory()->admin()->unverified()->create())->get('/admin')->assertForbidden();
});
