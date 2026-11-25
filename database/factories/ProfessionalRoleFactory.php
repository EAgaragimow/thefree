<?php

namespace Database\Factories;

use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProfessionalRole>
 */
class ProfessionalRoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'professional_function_id' => ProfessionalFunction::factory(),
            'sort_order' => 0,
        ];
    }
}
