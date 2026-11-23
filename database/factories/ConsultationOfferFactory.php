<?php

namespace Database\Factories;

use App\Enums\ConsultationType;
use App\Models\ConsultationOffer;
use App\Models\FreelancerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsultationOffer>
 */
class ConsultationOfferFactory extends Factory
{
    /**
     * Define the model's default state: an offered paid consultation, Monday to Friday.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'freelancer_profile_id' => FreelancerProfile::factory(),
            'type' => ConsultationType::Paid,
            'is_enabled' => true,
            'duration_minutes' => fake()->randomElement([30, 60, 90]),
            'price_cents' => fake()->numberBetween(5, 30) * 1000,
            'description' => fake()->sentence(),
            'availability' => collect(['mon', 'tue', 'wed', 'thu', 'fri'])
                ->map(fn (string $day): array => ['day' => $day, 'start' => '09:00', 'end' => '17:00'])
                ->all(),
        ];
    }

    public function free(): static
    {
        return $this->state(fn (): array => ['type' => ConsultationType::Free, 'price_cents' => 0]);
    }

    public function paid(): static
    {
        return $this->state(fn (): array => ['type' => ConsultationType::Paid]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['is_enabled' => false]);
    }
}
