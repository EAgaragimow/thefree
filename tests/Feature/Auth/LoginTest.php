<?php

use App\Models\User;

it('renders the login page', function () {
    $this->get(route('login'))->assertOk()->assertSee('Log in');
});

it('signs in with valid credentials', function () {
    $user = User::factory()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password', function () {
    $user = User::factory()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('refuses to sign in a suspended user and explains why', function () {
    $user = User::factory()->suspended()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'This account has been suspended. Please contact support.']);

    $this->assertGuest();
});

it('reactivates a deactivated account when the user signs in again', function () {
    $user = User::factory()->deactivated()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->deactivated_at)->toBeNull();
});

it('signs out a user who is suspended while their session is open', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user->forceFill(['status' => 'suspended'])->save();

    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $this->assertGuest();
});

it('logs the user out', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect();

    $this->assertGuest();
});
