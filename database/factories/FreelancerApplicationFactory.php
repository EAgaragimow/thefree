<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\FreelancerApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FreelancerApplication>
 */
class FreelancerApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'full_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'location' => fake()->city().', '.fake()->country(),
            'specialty' => fake()->jobTitle(),
            'years_experience' => fake()->numberBetween(1, 20),
            'experience_summary' => fake()->paragraph(),
            'about' => fake()->paragraph(),
            'skills' => ['Laravel', 'Design'],
            'portfolio_links' => [fake()->url()],
            'social_links' => ['linkedin' => fake()->url()],
            'status' => ApplicationStatus::Pending,
        ];
    }

    public function rejected(string $reason = 'Does not meet our requirements.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApplicationStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }

    /**
     * An approved application with a still-valid invitation. The plain token is returned via $token.
     */
    public function approved(?string &$token = null, int $validForDays = 7): static
    {
        $token = Str::random(64);
        $hash = hash('sha256', $token);

        return $this->state(fn (array $attributes) => [
            'status' => ApplicationStatus::Approved,
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
            'invitation_token_hash' => $hash,
            'invitation_expires_at' => now()->addDays($validForDays),
        ]);
    }

    public function expiredInvitation(): static
    {
        return $this->state(fn (array $attributes) => [
            'invitation_expires_at' => now()->subDay(),
        ]);
    }
}
