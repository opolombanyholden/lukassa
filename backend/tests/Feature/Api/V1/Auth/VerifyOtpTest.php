<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_otp_activates_user_with_correct_code(): void
    {
        $user = User::factory()->pending()->withOtp('verify_account', '123456')->create([
            'phone' => '+24107900001',
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '+24107900001',
            'otp' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.user.status', 'active');

        $user->refresh();
        $this->assertSame('active', $user->status);
        $this->assertNull($user->otp_code_hash);
    }

    public function test_verify_otp_rejects_wrong_code(): void
    {
        User::factory()->pending()->withOtp('verify_account', '123456')->create([
            'phone' => '+24107900002',
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '+24107900002',
            'otp' => '999999',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'AUTH_001')
            ->assertJsonPath('error.details.attempts_remaining', 4);
    }

    public function test_verify_otp_rejects_expired_code(): void
    {
        User::factory()->pending()->withOtp('verify_account', '123456')->create([
            'phone' => '+24107900003',
            'otp_expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '+24107900003',
            'otp' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'AUTH_002');
    }

    public function test_verify_otp_blocks_after_5_attempts(): void
    {
        User::factory()->pending()->withOtp('verify_account', '123456')->create([
            'phone' => '+24107900004',
            'otp_attempts' => 5,
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '+24107900004',
            'otp' => '123456',
        ]);

        $response->assertStatus(429)
            ->assertJsonPath('error.code', 'AUTH_003');
    }

    public function test_verify_otp_rejects_unknown_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '+24107900005',
            'otp' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'AUTH_001');
    }
}
