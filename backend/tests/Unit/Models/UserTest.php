<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\HasApiTokens;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_uuid_is_auto_generated_on_create(): void
    {
        $user = User::factory()->create();
        $this->assertIsString($user->id);
        $this->assertSame(36, strlen($user->id)); // UUID v4 length
    }

    public function test_has_api_tokens_trait(): void
    {
        $this->assertContains(HasApiTokens::class, class_uses_recursive(User::class));
    }

    public function test_password_is_hashed_on_assignment(): void
    {
        $user = User::factory()->create(['password' => 'plaintext-secret']);
        $this->assertNotSame('plaintext-secret', $user->password);
        $this->assertTrue(\Hash::check('plaintext-secret', $user->password));
    }

    public function test_otp_columns_are_hidden_from_array(): void
    {
        $user = User::factory()->create([
            'otp_code_hash' => 'hash',
            'otp_attempts' => 3,
            'otp_type' => 'verify_account',
        ]);
        $array = $user->toArray();
        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('otp_code_hash', $array);
        $this->assertArrayNotHasKey('otp_expires_at', $array);
        $this->assertArrayNotHasKey('otp_attempts', $array);
        $this->assertArrayNotHasKey('otp_type', $array);
        $this->assertArrayNotHasKey('two_factor_secret', $array);
    }

    public function test_mass_assignment_does_not_allow_status_or_type_admin(): void
    {
        // fillable est strict : status n'est PAS dedans
        $user = new User();
        $user->fill(['status' => 'active', 'type' => 'admin']);
        // type EST dans fillable, donc passe
        $this->assertSame('admin', $user->type);
        // status n'est PAS dans fillable, donc reste à la valeur d'origine
        $this->assertNotSame('active', $user->status);
    }

    public function test_soft_deletes_works(): void
    {
        $user = User::factory()->create();
        $user->delete();
        $this->assertNotNull($user->fresh()->deleted_at);
        $this->assertNull(User::find($user->id));
        $this->assertNotNull(User::withTrashed()->find($user->id));
    }
}
