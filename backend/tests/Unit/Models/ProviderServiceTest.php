<?php

namespace Tests\Unit\Models;

use App\Models\ProviderService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_uuid_auto_generated(): void
    {
        $ps = ProviderService::factory()->create();
        $this->assertSame(36, strlen($ps->id));
    }

    public function test_belongs_to_provider_and_service(): void
    {
        $provider = User::factory()->prestataire()->create();
        $service = Service::factory()->create();
        $ps = ProviderService::factory()->create([
            'provider_id' => $provider->id,
            'service_id' => $service->id,
        ]);
        $this->assertSame($provider->id, $ps->provider->id);
        $this->assertSame($service->id, $ps->service->id);
    }

    public function test_unique_constraint_provider_service(): void
    {
        $provider = User::factory()->prestataire()->create();
        $service = Service::factory()->create();
        ProviderService::factory()->create([
            'provider_id' => $provider->id,
            'service_id' => $service->id,
        ]);
        $this->expectException(\Illuminate\Database\QueryException::class);
        ProviderService::factory()->create([
            'provider_id' => $provider->id,
            'service_id' => $service->id,
        ]);
    }

    public function test_user_provider_services_relation(): void
    {
        $provider = User::factory()->prestataire()->create();
        ProviderService::factory()->count(3)->create(['provider_id' => $provider->id]);
        $this->assertCount(3, $provider->providerServices);
    }

    public function test_is_available_and_price_amount_casts(): void
    {
        $ps = ProviderService::factory()->create(['is_available' => 1, 'price_amount' => '12000']);
        $fresh = $ps->fresh();
        $this->assertTrue($fresh->is_available);
        $this->assertSame(12000, $fresh->price_amount);
    }
}
