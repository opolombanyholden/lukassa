<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use App\Services\Otp\FakeOtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['otp.sender' => 'fake']);
        FakeOtpSender::reset();
    }

    public function test_forgot_password_sends_otp_with_reset_type(): void
    {
        $user = User::factory()->create(['phone' => '+24107930001', 'status' => 'active']);

        $this->postJson('/api/v1/auth/forgot-password', ['phone' => '+24107930001'])
            ->assertStatus(200);

        $user->refresh();
        $this->assertSame('reset_password', $user->otp_type);
        $this->assertNotNull($user->otp_code_hash);
    }

    public function test_forgot_password_returns_200_even_for_unknown_phone(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['phone' => '+24107930099'])
            ->assertStatus(200);
    }
}
