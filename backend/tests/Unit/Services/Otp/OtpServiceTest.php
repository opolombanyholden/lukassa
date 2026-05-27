<?php

namespace Tests\Unit\Services\Otp;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Otp\FakeOtpSender;
use App\Services\Otp\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['otp.sender' => 'fake']);
        FakeOtpSender::reset();
    }

    private function service(): OtpService
    {
        return new OtpService(new FakeOtpSender());
    }

    public function test_generate_for_stores_hashed_code_with_ttl_and_returns_plain(): void
    {
        $user = User::factory()->create();
        $code = $this->service()->generateFor($user, 'verify_account');

        $this->assertSame(6, strlen($code));
        $this->assertTrue(ctype_digit($code));

        $user->refresh();
        $this->assertNotNull($user->otp_code_hash);
        $this->assertTrue(Hash::check($code, $user->otp_code_hash));
        $this->assertSame(0, $user->otp_attempts);
        $this->assertSame('verify_account', $user->otp_type);
        $this->assertTrue($user->otp_expires_at->isFuture());
    }

    public function test_verify_success_clears_otp_columns(): void
    {
        $user = User::factory()->create();
        $code = $this->service()->generateFor($user, 'verify_account');

        $this->service()->verify($user->fresh(), $code, 'verify_account');

        $user->refresh();
        $this->assertNull($user->otp_code_hash);
        $this->assertNull($user->otp_expires_at);
        $this->assertSame(0, $user->otp_attempts);
        $this->assertNull($user->otp_type);
    }

    public function test_verify_rejects_when_no_otp_active(): void
    {
        $user = User::factory()->create();
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Code OTP invalide.');
        $this->service()->verify($user, '123456', 'verify_account');
    }

    public function test_verify_rejects_when_expired(): void
    {
        $user = User::factory()->withOtp('verify_account', '123456')->create([
            'otp_expires_at' => now()->subMinute(),
        ]);
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Code OTP expiré, demande un nouveau code.');
        $this->service()->verify($user, '123456', 'verify_account');
    }

    public function test_verify_rejects_wrong_type(): void
    {
        $user = User::factory()->withOtp('verify_account', '123456')->create();
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Code OTP invalide.');
        $this->service()->verify($user, '123456', 'reset_password');
    }

    public function test_verify_increments_attempts_on_wrong_code(): void
    {
        $user = User::factory()->withOtp('verify_account', '123456')->create();
        try {
            $this->service()->verify($user, '999999', 'verify_account');
            $this->fail('Should have thrown');
        } catch (ApiException $e) {
            $this->assertSame('AUTH_001', $e->errorCode);
        }
        $this->assertSame(1, $user->fresh()->otp_attempts);
    }

    public function test_verify_blocks_after_max_attempts(): void
    {
        $user = User::factory()->withOtp('verify_account', '123456')->create([
            'otp_attempts' => 5,
        ]);
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Trop de tentatives OTP, demande un nouveau code.');
        $this->service()->verify($user, '123456', 'verify_account');
    }

    public function test_generate_calls_sender(): void
    {
        $user = User::factory()->create(['phone' => '+24107111111']);
        $code = $this->service()->generateFor($user, 'verify_account');

        $last = FakeOtpSender::lastSent();
        $this->assertSame('+24107111111', $last['phone']);
        $this->assertSame($code, $last['code']);
    }
}
