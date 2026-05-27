<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'phone' => '+241' . fake()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'type' => fake()->randomElement(['client', 'prestataire']),
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }

    public function client(): static
    {
        return $this->state(fn () => ['type' => 'client']);
    }

    public function prestataire(): static
    {
        return $this->state(fn () => ['type' => 'prestataire']);
    }

    public function withOtp(string $type = 'verify_account', string $code = '123456'): static
    {
        return $this->state(fn () => [
            'otp_code_hash' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(10),
            'otp_attempts' => 0,
            'otp_type' => $type,
        ]);
    }
}
