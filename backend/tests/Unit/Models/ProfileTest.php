<?php

namespace Tests\Unit\Models;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_uuid_auto_generated(): void
    {
        $profile = Profile::factory()->create();
        $this->assertSame(36, strlen($profile->id));
    }

    public function test_user_relation_returns_user(): void
    {
        $user = User::factory()->create();
        $profile = Profile::factory()->create(['user_id' => $user->id]);
        $this->assertSame($user->id, $profile->user->id);
    }

    public function test_default_country_is_gabon(): void
    {
        $profile = Profile::factory()->create();
        $this->assertSame('Gabon', $profile->country);
    }

    public function test_latitude_longitude_cast_to_decimal(): void
    {
        $profile = Profile::factory()->create([
            'latitude' => 0.41622,
            'longitude' => 9.46728,
        ]);
        $fresh = $profile->fresh();
        $this->assertSame('0.41622000', (string) $fresh->latitude);
        $this->assertSame('9.46728000', (string) $fresh->longitude);
    }
}
