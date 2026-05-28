<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(6),
            'description' => fake()->paragraph(),
            'icon' => null,
            'cover_image' => null,
            'min_price_estimate' => fake()->numberBetween(5000, 50000),
            'is_active' => true,
            'requires_quote' => false,
        ];
    }

    public function quoteOnly(): static
    {
        return $this->state(fn () => [
            'requires_quote' => true,
            'min_price_estimate' => null,
        ]);
    }
}
