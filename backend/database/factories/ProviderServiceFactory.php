<?php

namespace Database\Factories;

use App\Models\ProviderService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderServiceFactory extends Factory
{
    protected $model = ProviderService::class;

    public function definition(): array
    {
        return [
            'provider_id' => User::factory()->prestataire(),
            'service_id' => Service::factory(),
            'price_model' => fake()->randomElement(['fixed', 'hourly', 'quote']),
            'price_amount' => fake()->numberBetween(5000, 50000),
            'custom_description' => fake()->sentence(),
            'is_available' => true,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn () => ['is_available' => false]);
    }
}
