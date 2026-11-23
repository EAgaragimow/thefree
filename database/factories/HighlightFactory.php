<?php

namespace Database\Factories;

use App\Models\FreelancerProfile;
use App\Models\Highlight;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Highlight>
 */
class HighlightFactory extends Factory
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
            'title' => fake()->sentence(3),
            'cover_path' => null,
            'description' => fake()->paragraph(),
            'gallery' => [],
            'video_urls' => [],
            'links' => [],
            'sort_order' => 0,
        ];
    }
}
