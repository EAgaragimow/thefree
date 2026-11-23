<?php

namespace Database\Factories;

use App\Enums\ReviewDecision;
use App\Models\FreelancerProfile;
use App\Models\ProfileReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfileReview>
 */
class ProfileReviewFactory extends Factory
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
            'reviewer_id' => User::factory()->admin(),
            'decision' => ReviewDecision::Approved,
            'comment' => null,
        ];
    }
}
