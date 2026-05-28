<?php

namespace Tests\Feature\Api\V1\Provider;

use App\Models\ProviderService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_auth_sanctum(): void
    {
        $this->getJson('/api/v1/provider/services')->assertStatus(401);
    }

    public function test_index_rejects_client_with_AUTH_008(): void
    {
        $client = User::factory()->client()->create();
        Sanctum::actingAs($client);

        $this->getJson('/api/v1/provider/services')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_008');
    }

    public function test_index_returns_own_offers_only(): void
    {
        $provider1 = User::factory()->prestataire()->create();
        $provider2 = User::factory()->prestataire()->create();
        ProviderService::factory()->count(2)->create(['provider_id' => $provider1->id]);
        ProviderService::factory()->count(3)->create(['provider_id' => $provider2->id]);

        Sanctum::actingAs($provider1);
        $response = $this->getJson('/api/v1/provider/services');

        $response->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 2);
    }

    public function test_store_creates_offer(): void
    {
        $provider = User::factory()->prestataire()->create();
        $service = Service::factory()->create();
        Sanctum::actingAs($provider);

        $response = $this->postJson('/api/v1/provider/services', [
            'service_id' => $service->id,
            'price_model' => 'fixed',
            'price_amount' => 15000,
            'custom_description' => 'Travail soigné.',
            'is_available' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.price_amount', 15000)
            ->assertJsonPath('data.service.id', $service->id);

        $this->assertDatabaseHas('provider_services', [
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'price_amount' => 15000,
        ]);
    }

    public function test_store_rejects_duplicate_with_CATALOG_001(): void
    {
        $provider = User::factory()->prestataire()->create();
        $service = Service::factory()->create();
        ProviderService::factory()->create(['provider_id' => $provider->id, 'service_id' => $service->id]);

        Sanctum::actingAs($provider);
        $this->postJson('/api/v1/provider/services', [
            'service_id' => $service->id,
            'price_model' => 'fixed',
            'price_amount' => 10000,
        ])->assertStatus(422)
          ->assertJsonPath('error.code', 'CATALOG_001');
    }

    public function test_store_requires_price_amount_unless_quote(): void
    {
        $provider = User::factory()->prestataire()->create();
        $service = Service::factory()->create();
        Sanctum::actingAs($provider);

        // fixed without price_amount — must fail
        $this->postJson('/api/v1/provider/services', [
            'service_id' => $service->id,
            'price_model' => 'fixed',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['price_amount']);

        // quote without price_amount — must pass
        $service2 = Service::factory()->create();
        $this->postJson('/api/v1/provider/services', [
            'service_id' => $service2->id,
            'price_model' => 'quote',
        ])->assertStatus(201);
    }

    public function test_update_modifies_own_offer(): void
    {
        $provider = User::factory()->prestataire()->create();
        $offer = ProviderService::factory()->create([
            'provider_id' => $provider->id,
            'price_amount' => 10000,
        ]);

        Sanctum::actingAs($provider);
        $this->putJson("/api/v1/provider/services/{$offer->id}", [
            'price_amount' => 20000,
            'is_available' => false,
        ])->assertStatus(200)
          ->assertJsonPath('data.price_amount', 20000)
          ->assertJsonPath('data.is_available', false);
    }

    public function test_update_returns_404_on_other_provider_offer(): void
    {
        $providerA = User::factory()->prestataire()->create();
        $providerB = User::factory()->prestataire()->create();
        $offerB = ProviderService::factory()->create(['provider_id' => $providerB->id]);
        $origPrice = $offerB->price_amount;

        Sanctum::actingAs($providerA);
        $this->putJson("/api/v1/provider/services/{$offerB->id}", ['price_amount' => 99999])
            ->assertStatus(404);

        $this->assertSame($origPrice, $offerB->fresh()->price_amount);
    }

    public function test_destroy_removes_own_offer(): void
    {
        $provider = User::factory()->prestataire()->create();
        $offer = ProviderService::factory()->create(['provider_id' => $provider->id]);

        Sanctum::actingAs($provider);
        $this->deleteJson("/api/v1/provider/services/{$offer->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('provider_services', ['id' => $offer->id]);
    }

    public function test_destroy_returns_404_on_other_provider_offer(): void
    {
        $providerA = User::factory()->prestataire()->create();
        $providerB = User::factory()->prestataire()->create();
        $offerB = ProviderService::factory()->create(['provider_id' => $providerB->id]);

        Sanctum::actingAs($providerA);
        $this->deleteJson("/api/v1/provider/services/{$offerB->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('provider_services', ['id' => $offerB->id]);
    }
}
