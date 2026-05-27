<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'firstname' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'bio' => fake()->sentence(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => 'Gabon',
            'latitude' => fake()->randomFloat(8, -1, 1),
            'longitude' => fake()->randomFloat(8, 8, 14),
            'intervention_radius_km' => 10,
            'language' => 'fr',
        ];
    }
}
