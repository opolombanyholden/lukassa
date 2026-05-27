<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use App\Services\Otp\FakeOtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['otp.sender' => 'fake']);
        FakeOtpSender::reset();
    }

    public function test_register_creates_user_in_pending_status_and_sends_otp(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => '+24107123456',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'type' => 'client',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['user_id', '_dev_otp', 'message']]);

        $user = User::where('phone', '+24107123456')->first();
        $this->assertNotNull($user);
        $this->assertSame('pending', $user->status);
        $this->assertSame('client', $user->type);
        $this->assertNotNull($user->otp_code_hash);
        $this->assertSame('verify_account', $user->otp_type);

        // Profile linked
        $this->assertNotNull($user->profile);
    }

    public function test_register_rejects_duplicate_phone(): void
    {
        User::factory()->create(['phone' => '+24107000111']);

        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => '+24107000111',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'type' => 'client',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_register_rejects_invalid_phone_format(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => 'not-a-phone',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'type' => 'client',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_register_rejects_password_too_short(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => '+24107222333',
            'password' => 'short',
            'password_confirmation' => 'short',
            'type' => 'client',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_rejects_password_mismatch(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => '+24107222444',
            'password' => 'secret123',
            'password_confirmation' => 'different',
            'type' => 'client',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_rejects_admin_type(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => '+24107222555',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'type' => 'admin',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_register_accepts_prestataire_type(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => '+24107222666',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'type' => 'prestataire',
        ]);

        $response->assertStatus(201);
        $this->assertSame('prestataire', User::where('phone', '+24107222666')->first()->type);
    }
}
