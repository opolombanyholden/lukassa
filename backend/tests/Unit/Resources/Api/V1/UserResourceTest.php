<?php

namespace Tests\Unit\Resources\Api\V1;

use App\Http\Resources\Api\V1\UserResource;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_resource_exposes_safe_fields(): void
    {
        $user = User::factory()->create([
            'phone' => '+24107888888',
            'email' => 'sarah@example.com',
            'type' => 'client',
            'status' => 'active',
        ]);
        Profile::factory()->create(['user_id' => $user->id, 'firstname' => 'Sarah', 'lastname' => 'Doe']);

        $arr = (new UserResource($user->load('profile')))->toArray(new Request());

        $this->assertSame($user->id, $arr['id']);
        $this->assertSame('+24107888888', $arr['phone']);
        $this->assertSame('sarah@example.com', $arr['email']);
        $this->assertSame('client', $arr['type']);
        $this->assertSame('active', $arr['status']);
        $this->assertSame('Sarah', $arr['profile']['firstname']);
        $this->assertSame('Doe', $arr['profile']['lastname']);

        // Sensitive fields must not leak
        $this->assertArrayNotHasKey('password', $arr);
        $this->assertArrayNotHasKey('otp_code_hash', $arr);
        $this->assertArrayNotHasKey('two_factor_secret', $arr);
    }
}
