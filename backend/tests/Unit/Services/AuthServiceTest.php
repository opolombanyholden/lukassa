<?php

namespace Tests\Unit\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_attempt_login_returns_user_on_valid_credentials(): void
    {
        $user = User::factory()->create([
            'phone' => '+24107222222',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $result = (new AuthService())->attemptLogin('+24107222222', 'secret123');
        $this->assertSame($user->id, $result->id);
    }

    public function test_attempt_login_throws_on_unknown_phone(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Identifiants invalides.');
        (new AuthService())->attemptLogin('+24107000000', 'secret123');
    }

    public function test_attempt_login_throws_on_wrong_password(): void
    {
        User::factory()->create([
            'phone' => '+24107333333',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Identifiants invalides.');
        (new AuthService())->attemptLogin('+24107333333', 'wrong-password');
    }

    public function test_attempt_login_rejects_pending_user(): void
    {
        User::factory()->pending()->create([
            'phone' => '+24107444444',
            'password' => 'secret123',
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Compte non vérifié. Confirme ton numéro via OTP.');
        (new AuthService())->attemptLogin('+24107444444', 'secret123');
    }

    public function test_attempt_login_rejects_suspended_user(): void
    {
        User::factory()->suspended()->create([
            'phone' => '+24107555555',
            'password' => 'secret123',
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Compte suspendu. Contacte le support.');
        (new AuthService())->attemptLogin('+24107555555', 'secret123');
    }

    public function test_lockout_after_10_failed_attempts_suspends_user(): void
    {
        $user = User::factory()->create([
            'phone' => '+24107666666',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $service = new AuthService();
        for ($i = 1; $i <= 10; $i++) {
            try {
                $service->attemptLogin('+24107666666', 'wrong');
            } catch (ApiException $e) {
                // expected
            }
        }

        $this->assertSame('suspended', $user->fresh()->status);
    }

    public function test_successful_login_resets_fail_counter(): void
    {
        User::factory()->create([
            'phone' => '+24107777777',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $service = new AuthService();
        // 3 failed attempts
        for ($i = 0; $i < 3; $i++) {
            try { $service->attemptLogin('+24107777777', 'wrong'); } catch (ApiException $e) {}
        }
        // success
        $service->attemptLogin('+24107777777', 'secret123');

        // counter should be reset to 0 after success
        $this->assertNull(Cache::get('login_fails:+24107777777'));
        try { $service->attemptLogin('+24107777777', 'wrong'); } catch (ApiException $e) {}
        $this->assertSame(1, Cache::get('login_fails:+24107777777'));
    }
}
