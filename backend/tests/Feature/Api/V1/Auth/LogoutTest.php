<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_user_requires_auth(): void
    {
        $this->getJson('/api/v1/auth/user')->assertStatus(401);
    }

    public function test_get_user_returns_authenticated_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/auth/user')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_logout_bearer_revokes_current_token_only(): void
    {
        $user = User::factory()->create(['status' => 'active', 'password' => 'secret123']);
        $token1 = $user->createToken('device-1')->plainTextToken;
        $token2 = $user->createToken('device-2')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token1}")
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200);

        // Token 1 must be revoked
        $this->withHeader('Authorization', "Bearer {$token1}")
            ->getJson('/api/v1/auth/user')
            ->assertStatus(401);

        // Token 2 still works
        $this->withHeader('Authorization', "Bearer {$token2}")
            ->getJson('/api/v1/auth/user')
            ->assertStatus(200);
    }
}
