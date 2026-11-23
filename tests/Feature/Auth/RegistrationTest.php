<?php

use App\Enums\BillingState;
use App\Enums\SubscriptionType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Jane Client',
        'email' => 'jane@example.com',
        'password' => 'a-Strong-passw0rd!',
        'password_confirmation' => 'a-Strong-passw0rd!',
        'timezone' => 'Europe/Kyiv',
    ], $overrides);
}

it('renders the registration page', function () {
    $this->get(route('register'))->assertOk()->assertSee('Create your account');
});

it('registers a free client and sends the verification email', function () {
    Notification::fake();

    $this->post(route('register'), registrationPayload())->assertRedirect();

    $user = User::where('email', 'jane@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Client)
        ->and($user->subscription_type)->toBe(SubscriptionType::FreePlan)
        ->and($user->billing_state)->toBe(BillingState::Ok)
        ->and($user->timezone)->toBe('Europe/Kyiv')
        ->and($user->hasVerifiedEmail())->toBeFalse();

    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('always registers the user as a client even when another role is submitted', function () {
    $this->post(route('register'), registrationPayload(['role' => 'admin']))->assertRedirect();

    expect(User::where('email', 'jane@example.com')->firstOrFail()->role)->toBe(UserRole::Client);
});

it('rejects an email address that is already registered', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    $this->post(route('register'), registrationPayload())->assertSessionHasErrors('email');

    expect(User::where('email', 'jane@example.com')->count())->toBe(1);
});

it('rejects an invalid time zone', function () {
    $this->post(route('register'), registrationPayload(['timezone' => 'Mars/Base']))
        ->assertSessionHasErrors('timezone');
});

it('rejects a password that does not match its confirmation', function () {
    $this->post(route('register'), registrationPayload(['password_confirmation' => 'different']))
        ->assertSessionHasErrors('password');
});
