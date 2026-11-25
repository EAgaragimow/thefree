<?php

use App\Enums\BillingState;
use App\Enums\SubscriptionType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
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

it('signs up with UTC when the browser reports an unknown or retired time zone name', function (string $reported) {
    $this->post(route('register'), registrationPayload(['timezone' => $reported]))
        ->assertSessionHasNoErrors();

    expect(User::where('email', 'jane@example.com')->firstOrFail()->timezone)->toBe('UTC');
})->with(['retired name' => 'Europe/Kiev', 'unknown' => 'Mars/Base']);

it('rejects a password that does not match its confirmation', function () {
    $this->post(route('register'), registrationPayload(['password_confirmation' => 'different']))
        ->assertSessionHasErrors('password');
});

it('records the registration in the audit log', function () {
    $this->post(route('register'), registrationPayload())->assertRedirect();

    $user = User::where('email', 'jane@example.com')->firstOrFail();
    $log = AuditLog::where('action', 'user.registered')->firstOrFail();

    expect($log->actor_id)->toBe($user->id)
        ->and($log->subject_id)->toBe($user->id)
        ->and($log->metadata['role'])->toBe('client');
});

it('records a working verification link in the audit log only in the local environment', function (string $environment, bool $recorded) {
    // CSRF checks are skipped only in the testing environment, which this test leaves.
    $this->withoutMiddleware(PreventRequestForgery::class);
    app()->detectEnvironment(fn () => $environment);

    $this->post(route('register'), registrationPayload())->assertRedirect();

    $link = AuditLog::where('action', 'user.registered')->firstOrFail()->metadata['activation_url'] ?? null;

    expect($link !== null)->toBe($recorded);

    if ($recorded) {
        $this->get($link)->assertRedirect();

        expect(User::where('email', 'jane@example.com')->firstOrFail()->hasVerifiedEmail())->toBeTrue();
    }
})->with([
    'local' => ['local', true],
    'production' => ['production', false],
]);
