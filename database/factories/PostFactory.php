<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(5);

        return [
            'user_id'      => User::factory(),
            'title'        => $title,
            'slug'         => Str::slug($title) . '-' . fake()->unique()->numberBetween(1000, 9999),
            'content'      => fake()->paragraphs(3, true),
            'excerpt'      => fake()->sentence(),
            'cover_image'  => null,
            'status'       => 'published',
            'pinned'       => false,
            'views_count'  => 0,
            'published_at' => now(),
        ];
    }

    /**
     * State for a draft post.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'draft',
            'published_at' => null,
        ]);
    }

    /**
     * State for a pinned post.
     */
    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => [
            'pinned' => true,
        ]);
    }
}
