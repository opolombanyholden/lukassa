<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\Profile;
use App\Models\ProviderService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderSearchTest extends TestCase
{
    use RefreshDatabase;

    private function makeProvider(string $phone, float $lat = 0.4, float $lng = 9.4, float $rating = 4.0, string $status = 'active'): User
    {
        $user = User::factory()->prestataire()->create(['phone' => $phone, 'status' => $status]);
        Profile::factory()->create([
            'user_id' => $user->id,
            'latitude' => $lat,
            'longitude' => $lng,
            'average_rating' => $rating,
        ]);
        return $user;
    }

    public function test_search_requires_service_id(): void
    {
        $this->getJson('/api/v1/public/providers/search')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['service_id']);
    }

    public function test_search_validates_lat_requires_lng(): void
    {
        $service = Service::factory()->create();
        $this->getJson("/api/v1/public/providers/search?service_id={$service->id}&lat=0.4")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lng']);
    }

    public function test_search_returns_paginated_results(): void
    {
        $service = Service::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            $p = $this->makeProvider('+24107' . str_pad((string) (200 + $i), 3, '0', STR_PAD_LEFT));
            ProviderService::factory()->create(['provider_id' => $p->id, 'service_id' => $service->id]);
        }

        $this->getJson("/api/v1/public/providers/search?service_id={$service->id}")
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta' => ['pagination' => ['total']]])
            ->assertJsonPath('meta.pagination.total', 3);
    }

    public function test_search_with_geo_returns_distance_km(): void
    {
        $service = Service::factory()->create();
        $p = $this->makeProvider('+24107300', 0.42, 9.47);
        ProviderService::factory()->create(['provider_id' => $p->id, 'service_id' => $service->id]);

        $response = $this->getJson("/api/v1/public/providers/search?service_id={$service->id}&lat=0.4162&lng=9.4673&radius_km=50");

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.0.distance_km'));
    }

    public function test_search_filters_rating_min(): void
    {
        $service = Service::factory()->create();
        $high = $this->makeProvider('+24107301', rating: 4.7);
        $low = $this->makeProvider('+24107302', rating: 3.0);
        ProviderService::factory()->create(['provider_id' => $high->id, 'service_id' => $service->id]);
        ProviderService::factory()->create(['provider_id' => $low->id, 'service_id' => $service->id]);

        $this->getJson("/api/v1/public/providers/search?service_id={$service->id}&rating_min=4.0")
            ->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 1);
    }

    public function test_search_filters_price_max(): void
    {
        $service = Service::factory()->create();
        $p1 = $this->makeProvider('+24107303');
        $p2 = $this->makeProvider('+24107304');
        ProviderService::factory()->create(['provider_id' => $p1->id, 'service_id' => $service->id, 'price_amount' => 10000]);
        ProviderService::factory()->create(['provider_id' => $p2->id, 'service_id' => $service->id, 'price_amount' => 50000]);

        $this->getJson("/api/v1/public/providers/search?service_id={$service->id}&price_max=20000")
            ->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 1);
    }

    public function test_search_excludes_pending_providers(): void
    {
        $service = Service::factory()->create();
        $pending = $this->makeProvider('+24107305', status: 'pending');
        ProviderService::factory()->create(['provider_id' => $pending->id, 'service_id' => $service->id]);

        $this->getJson("/api/v1/public/providers/search?service_id={$service->id}")
            ->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 0);
    }

    public function test_show_provider_returns_public_profile(): void
    {
        $user = User::factory()->prestataire()->create(['phone' => '+24107306']);
        Profile::factory()->create([
            'user_id' => $user->id,
            'firstname' => 'Sarah',
            'lastname' => 'Mbeng',
            'city' => 'Libreville',
            'latitude' => 0.4,
            'longitude' => 9.4,
        ]);

        $response = $this->getJson("/api/v1/public/providers/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.firstname', 'Sarah')
            ->assertJsonPath('data.city', 'Libreville');

        // anti-leak verifications
        $body = $response->json('data');
        $this->assertArrayNotHasKey('phone', $body);
        $this->assertArrayNotHasKey('email', $body);
        $this->assertArrayNotHasKey('address', $body);
        $this->assertArrayNotHasKey('latitude', $body);
        $this->assertArrayNotHasKey('longitude', $body);
    }

    public function test_show_unknown_provider_returns_404(): void
    {
        $this->getJson('/api/v1/public/providers/019e6d9e-0000-0000-0000-000000000000')
            ->assertStatus(404);
    }
}
