<?php

namespace Tests\Feature\Api\V1;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_requires_auth(): void
    {
        $this->getJson('/api/v1/profile')->assertStatus(401);
    }

    public function test_show_returns_user_profile(): void
    {
        $user = User::factory()->create();
        Profile::factory()->create([
            'user_id' => $user->id,
            'firstname' => 'Sarah',
            'city' => 'Libreville',
        ]);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.firstname', 'Sarah')
            ->assertJsonPath('data.city', 'Libreville')
            ->assertJsonPath('data.country', 'Gabon');
    }

    public function test_update_modifies_profile_fields(): void
    {
        $user = User::factory()->create();
        Profile::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile', [
            'firstname' => 'Sarah',
            'lastname' => 'Mbeng',
            'bio' => 'Plombière expérimentée',
            'address' => '123 rue Foch',
            'city' => 'Port-Gentil',
            'latitude' => -0.7193,
            'longitude' => 8.7815,
            'intervention_radius_km' => 25,
            'language' => 'fr',
        ])->assertStatus(200)
            ->assertJsonPath('data.firstname', 'Sarah')
            ->assertJsonPath('data.city', 'Port-Gentil')
            ->assertJsonPath('data.intervention_radius_km', 25);
    }

    public function test_update_rejects_invalid_latitude(): void
    {
        $user = User::factory()->create();
        Profile::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile', [
            'latitude' => 999,  // out of valid range
        ])->assertStatus(422)->assertJsonValidationErrors(['latitude']);
    }

    public function test_update_rejects_invalid_radius(): void
    {
        $user = User::factory()->create();
        Profile::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile', [
            'intervention_radius_km' => -5,
        ])->assertStatus(422)->assertJsonValidationErrors(['intervention_radius_km']);
    }
}
