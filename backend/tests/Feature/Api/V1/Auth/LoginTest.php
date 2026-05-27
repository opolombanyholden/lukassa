<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // Disable throttle middleware so we can test the 10-fails lockout without hitting throttle:5,1
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    public function test_login_with_device_name_returns_bearer_token(): void
    {
        User::factory()->create([
            'phone' => '+24107920001',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+24107920001',
            'password' => 'secret123',
            'device_name' => 'iPhone Sarah',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['user', 'token']])
            ->assertJsonPath('data.user.phone', '+24107920001');

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_login_without_device_name_uses_stateful(): void
    {
        User::factory()->create([
            'phone' => '+24107920002',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+24107920002',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['user']])
            ->assertJsonMissingPath('data.token');
    }

    public function test_login_rejects_pending_user(): void
    {
        User::factory()->pending()->create([
            'phone' => '+24107920003',
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+24107920003',
            'password' => 'secret123',
            'device_name' => 'x',
        ]);

        $response->assertStatus(403)->assertJsonPath('error.code', 'AUTH_005');
    }

    public function test_login_rejects_suspended_user(): void
    {
        User::factory()->suspended()->create([
            'phone' => '+24107920004',
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+24107920004',
            'password' => 'secret123',
            'device_name' => 'x',
        ]);

        $response->assertStatus(403)->assertJsonPath('error.code', 'AUTH_006');
    }

    public function test_login_returns_401_on_wrong_password(): void
    {
        User::factory()->create([
            'phone' => '+24107920005',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+24107920005',
            'password' => 'wrong-password',
            'device_name' => 'x',
        ]);

        $response->assertStatus(401)->assertJsonPath('error.code', 'AUTH_004');
    }

    public function test_login_returns_401_on_unknown_phone_without_leak(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+24107999999',
            'password' => 'whatever',
            'device_name' => 'x',
        ]);

        $response->assertStatus(401)->assertJsonPath('error.code', 'AUTH_004');
    }

    public function test_login_after_10_fails_suspends_account(): void
    {
        $user = User::factory()->create([
            'phone' => '+24107920006',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'phone' => '+24107920006',
                'password' => 'wrong',
                'device_name' => 'x',
            ]);
        }

        $this->assertSame('suspended', $user->fresh()->status);
    }
}
