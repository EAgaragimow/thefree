<?php

namespace Database\Factories;

use App\Models\BrandProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandProfile>
 */
class BrandProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->client(),
            'company_name' => fake()->company(),
            'description' => fake()->paragraph(),
            'industry' => fake()->word(),
            'website' => fake()->url(),
            'location' => fake()->city().', '.fake()->country(),
            'social_links' => ['linkedin' => fake()->url()],
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => fake()->phoneNumber(),
        ];
    }
}
