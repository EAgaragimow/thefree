<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('renders the forgot password page', function () {
    $this->get(route('password.request'))->assertOk()->assertSee('Forgot your password?');
});

it('emails a reset link to a registered user', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'a-New-passw0rd!',
        'password_confirmation' => 'a-New-passw0rd!',
    ])->assertRedirect(route('login'));

    $this->post(route('login'), ['email' => $user->email, 'password' => 'a-New-passw0rd!']);
    $this->assertAuthenticatedAs($user);
});

it('does not reset the password with an invalid token', function () {
    $user = User::factory()->create();

    $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'a-New-passw0rd!',
        'password_confirmation' => 'a-New-passw0rd!',
    ])->assertSessionHasErrors('email');
});
