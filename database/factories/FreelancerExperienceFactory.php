<?php

namespace Database\Factories;

use App\Models\FreelancerExperience;
use App\Models\FreelancerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FreelancerExperience>
 */
class FreelancerExperienceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'freelancer_profile_id' => FreelancerProfile::factory(),
            'company' => fake()->company(),
            'role' => fake()->jobTitle(),
            'started_at' => fake()->dateTimeBetween('-10 years', '-3 years'),
            'ended_at' => fake()->dateTimeBetween('-3 years', '-1 month'),
            'description' => fake()->sentence(),
            'sort_order' => 0,
        ];
    }
}
