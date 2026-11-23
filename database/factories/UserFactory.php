<?php

namespace Database\Factories;

use App\Enums\BillingState;
use App\Enums\SubscriptionType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Client,
            'status' => UserStatus::Active,
            'timezone' => 'UTC',
            'subscription_type' => SubscriptionType::FreePlan,
            'billing_state' => BillingState::Ok,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function client(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Client]);
    }

    public function freelancer(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Freelancer]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Admin]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => ['subscription_type' => SubscriptionType::PaidPlan]);
    }

    /**
     * A Paid client whose renewal failed: still `paid-plan`, but billing is restricted.
     */
    public function paymentRestricted(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_type' => SubscriptionType::PaidPlan,
            'billing_state' => BillingState::PaymentRestricted,
        ]);
    }

    public function restricted(): static
    {
        return $this->state(fn (array $attributes) => ['status' => UserStatus::Restricted]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => UserStatus::Suspended]);
    }

    public function deactivated(): static
    {
        return $this->state(fn (array $attributes) => ['deactivated_at' => now()]);
    }
}
