<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        return [
            'parent_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(6),
            'icon' => fake()->randomElement(['🔧', '💡', '🧹', '💇', '🚗']),
            'description' => fake()->sentence(),
            'order_position' => fake()->numberBetween(1, 100),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
