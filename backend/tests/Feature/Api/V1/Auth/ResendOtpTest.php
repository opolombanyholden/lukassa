<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use App\Services\Otp\FakeOtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResendOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['otp.sender' => 'fake']);
        FakeOtpSender::reset();
    }

    public function test_resend_otp_generates_new_code_and_resets_attempts(): void
    {
        $user = User::factory()->pending()->withOtp('verify_account', '111111')->create([
            'phone' => '+24107910001',
            'otp_attempts' => 4,
        ]);
        $oldHash = $user->otp_code_hash;

        $response = $this->postJson('/api/v1/auth/resend-otp', [
            'phone' => '+24107910001',
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $user->refresh();
        $this->assertNotSame($oldHash, $user->otp_code_hash);
        $this->assertSame(0, $user->otp_attempts);
    }

    public function test_resend_otp_returns_200_even_for_unknown_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/resend-otp', [
            'phone' => '+24107910099',
        ]);
        $response->assertStatus(200);
    }
}
