<?php

namespace Database\Factories;

use App\Enums\PricingMode;
use App\Models\FreelancerProfile;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state: a fixed-price service.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'freelancer_profile_id' => FreelancerProfile::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->text(150),
            'pricing_mode' => PricingMode::Fixed,
            'price_cents' => fake()->numberBetween(50, 500) * 100,
            'sort_order' => 0,
        ];
    }

    public function startingFrom(): static
    {
        return $this->state(fn (array $attributes) => ['pricing_mode' => PricingMode::StartingFrom]);
    }

    public function customQuote(): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_mode' => PricingMode::CustomQuote,
            'price_cents' => null,
        ]);
    }
}
