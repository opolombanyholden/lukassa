<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_with_valid_otp_updates_hash_and_revokes_tokens(): void
    {
        $user = User::factory()->withOtp('reset_password', '654321')->create([
            'phone' => '+24107940001',
            'password' => 'old-password',
            'status' => 'active',
        ]);
        $user->createToken('device-1');
        $user->createToken('device-2');

        $this->postJson('/api/v1/auth/reset-password', [
            'phone' => '+24107940001',
            'otp' => '654321',
            'password' => 'new-password-2026',
            'password_confirmation' => 'new-password-2026',
        ])->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-2026', $user->password));
        $this->assertNull($user->otp_code_hash);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_reset_password_rejects_wrong_otp_type(): void
    {
        User::factory()->withOtp('verify_account', '111111')->create([
            'phone' => '+24107940002',
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'phone' => '+24107940002',
            'otp' => '111111',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertStatus(422)->assertJsonPath('error.code', 'AUTH_001');
    }
}
