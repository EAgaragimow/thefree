<?php

namespace Database\Factories;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    /**
     * Define the model's default state: a draft that nobody but admins can see.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(5);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(100, 99999),
            'excerpt' => fake()->sentence(18),
            'body' => '<p>'.implode('</p><p>', fake()->paragraphs(4)).'</p>',
            'status' => BlogPostStatus::Draft,
            'published_at' => null,
            'author_id' => User::factory()->admin(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlogPostStatus::Published,
            'published_at' => now()->subHour(),
        ]);
    }
}
