<?php

use App\Enums\BillingState;
use App\Enums\SubscriptionType;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('grants paid access only to clients on a paid plan with healthy billing', function (string $state, bool $expected) {
    $user = match ($state) {
        'free' => User::factory()->client()->create(),
        'paid' => User::factory()->client()->paid()->create(),
        'payment-restricted' => User::factory()->client()->paymentRestricted()->create(),
        'freelancer' => User::factory()->freelancer()->paid()->create(),
        'admin' => User::factory()->admin()->paid()->create(),
    };

    expect($user->hasPaidAccess())->toBe($expected);
})->with([
    'free client' => ['free', false],
    'paid client' => ['paid', true],
    'payment-restricted client' => ['payment-restricted', false],
    'freelancer' => ['freelancer', false],
    'admin' => ['admin', false],
]);

it('keeps a payment-restricted client on the paid-plan type', function () {
    $user = User::factory()->client()->paymentRestricted()->create();

    expect($user->subscription_type)->toBe(SubscriptionType::PaidPlan)
        ->and($user->billing_state)->toBe(BillingState::PaymentRestricted)
        ->and($user->isPaymentRestricted())->toBeTrue();
});

it('applies the subscription access matrix to each ability', function (string $ability, array $expected) {
    $users = [
        'free' => User::factory()->client()->create(),
        'paid' => User::factory()->client()->paid()->create(),
        'payment-restricted' => User::factory()->client()->paymentRestricted()->create(),
        'freelancer' => User::factory()->freelancer()->create(),
        'admin' => User::factory()->admin()->create(),
    ];

    expect(Gate::forUser(null)->allows($ability))->toBeFalse();

    foreach ($expected as $key => $allowed) {
        expect(Gate::forUser($users[$key])->allows($ability))
            ->toBe($allowed, "{$ability} for {$key}");
    }
})->with([
    'view-full-directory' => ['view-full-directory', ['free' => false, 'paid' => true, 'payment-restricted' => false, 'freelancer' => true, 'admin' => true]],
    'view-full-freelancer-profile' => ['view-full-freelancer-profile', ['free' => false, 'paid' => true, 'payment-restricted' => false, 'freelancer' => true, 'admin' => true]],
    'read-full-blog-article' => ['read-full-blog-article', ['free' => false, 'paid' => true, 'payment-restricted' => false, 'freelancer' => false, 'admin' => true]],
    'favorite-freelancers' => ['favorite-freelancers', ['free' => false, 'paid' => true, 'payment-restricted' => false, 'freelancer' => false, 'admin' => false]],
]);
