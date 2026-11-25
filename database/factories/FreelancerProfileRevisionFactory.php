<?php

namespace Database\Factories;

use App\Actions\Profiles\ProfileContent;
use App\Enums\ProfileStatus;
use App\Enums\WeeklyHours;
use App\Models\FreelancerProfile;
use App\Models\FreelancerProfileRevision;
use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use App\Models\Skill;
use App\Models\Tool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FreelancerProfileRevision>
 */
class FreelancerProfileRevisionFactory extends Factory
{
    /**
     * Define the model's default state: a Draft revision whose content is complete enough
     * to be submitted for review.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'freelancer_profile_id' => FreelancerProfile::factory(),
            'status' => ProfileStatus::Draft,
            'payload' => fn (): array => ProfileContent::fromArray([
                'professional_title' => fake()->jobTitle(),
                'about' => fake()->paragraphs(2, true),
                'location' => fake()->city().', '.fake()->country(),
                'timezone' => 'UTC',
                'website' => fake()->url(),
                'social_links' => ['linkedin' => fake()->url()],
                ...self::completeDetails(),
            ])->toArray(),
        ];
    }

    /**
     * Catalog selections and answers that satisfy every requirement for submitting a profile.
     *
     * @return array<string, mixed>
     */
    public static function completeDetails(): array
    {
        return [
            'professional_function_id' => ProfessionalFunction::factory()->create()->id,
            'role_ids' => [ProfessionalRole::factory()->create()->id],
            'skill_ids' => Skill::factory()->count(ProfileContent::MIN_SKILLS)->create()->modelKeys(),
            'tool_ids' => Tool::factory()->count(ProfileContent::MIN_TOOLS)->create()->modelKeys(),
            'hourly_rate_cents' => fake()->numberBetween(30, 200) * 100,
            'offers_monthly_retainers' => false,
            'weekly_hours' => WeeklyHours::From21To40->value,
            'open_to_full_time' => false,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProfileStatus::Draft]);
    }

    public function pendingReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProfileStatus::PendingReview,
            'submitted_at' => now(),
        ]);
    }

    public function changesRequested(string $comment = 'Please expand your About section.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProfileStatus::ChangesRequested,
            'submitted_at' => now()->subDay(),
            'last_change_request' => $comment,
        ]);
    }

    /**
     * An approved revision kept as history.
     */
    public function applied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProfileStatus::Published,
            'submitted_at' => now()->subDay(),
            'applied_at' => now(),
        ]);
    }
}
