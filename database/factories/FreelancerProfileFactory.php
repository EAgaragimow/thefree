<?php

namespace Database\Factories;

use App\Models\FreelancerProfile;
use App\Models\FreelancerProfileRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FreelancerProfile>
 */
class FreelancerProfileFactory extends Factory
{
    /**
     * Define the model's default state: a live profile row that has never been published.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->freelancer(),
            'professional_title' => fake()->jobTitle(),
            'about' => fake()->paragraphs(2, true),
            'location' => fake()->city().', '.fake()->country(),
            'timezone' => 'UTC',
            'website' => fake()->url(),
            'social_links' => ['linkedin' => fake()->url()],
        ];
    }

    /**
     * The profile has been approved at least once and is visible to clients.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => now(),
            'content_updated_at' => now(),
        ]);
    }

    public function withDraftRevision(): static
    {
        return $this->has(FreelancerProfileRevision::factory()->draft(), 'revisions');
    }

    public function withPendingRevision(): static
    {
        return $this->has(FreelancerProfileRevision::factory()->pendingReview(), 'revisions');
    }

    public function withChangesRequested(string $comment = 'Please expand your About section.'): static
    {
        return $this->has(FreelancerProfileRevision::factory()->changesRequested($comment), 'revisions');
    }

    /**
     * A profile in its first month, which gets a head start in the directory.
     */
    public function newbie(): static
    {
        return $this->state(fn (array $attributes) => ['is_newbie' => true]);
    }

    public function featured(int $sort = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'featured_at' => now(),
            'featured_sort' => $sort,
        ]);
    }
}
