<?php

namespace Tests\Unit\Services\ProviderSearch;

use App\Models\Profile;
use App\Models\ProviderService;
use App\Models\Service;
use App\Models\User;
use App\Services\ProviderSearch\ProviderSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeProvider(string $phone, float $lat, float $lng, float $rating = 4.0): User
    {
        $user = User::factory()->prestataire()->create(['phone' => $phone, 'status' => 'active']);
        Profile::factory()->create([
            'user_id' => $user->id,
            'latitude' => $lat,
            'longitude' => $lng,
            'average_rating' => $rating,
        ]);
        return $user;
    }

    public function test_filters_by_service_id(): void
    {
        $service1 = Service::factory()->create();
        $service2 = Service::factory()->create();
        $p1 = $this->makeProvider('+24107001', 0.4, 9.4);
        $p2 = $this->makeProvider('+24107002', 0.4, 9.4);
        ProviderService::factory()->create(['provider_id' => $p1->id, 'service_id' => $service1->id]);
        ProviderService::factory()->create(['provider_id' => $p2->id, 'service_id' => $service2->id]);

        $result = (new ProviderSearchService())->search(['service_id' => $service1->id]);

        $this->assertSame(1, $result->total());
        $this->assertSame($p1->id, $result->items()[0]->id);
    }

    public function test_filters_by_geo_distance(): void
    {
        $service = Service::factory()->create();
        // Libreville approx
        $near = $this->makeProvider('+24107003', 0.4162, 9.4673);
        // 30 km away (approx)
        $far = $this->makeProvider('+24107004', 0.7, 9.4673);
        ProviderService::factory()->create(['provider_id' => $near->id, 'service_id' => $service->id]);
        ProviderService::factory()->create(['provider_id' => $far->id, 'service_id' => $service->id]);

        $result = (new ProviderSearchService())->search([
            'service_id' => $service->id,
            'lat' => 0.4162,
            'lng' => 9.4673,
            'radius_km' => 10,
        ]);

        $this->assertSame(1, $result->total());
        $this->assertSame($near->id, $result->items()[0]->id);
    }

    public function test_applies_rating_min(): void
    {
        $service = Service::factory()->create();
        $high = $this->makeProvider('+24107005', 0.4, 9.4, 4.7);
        $low = $this->makeProvider('+24107006', 0.4, 9.4, 3.2);
        ProviderService::factory()->create(['provider_id' => $high->id, 'service_id' => $service->id]);
        ProviderService::factory()->create(['provider_id' => $low->id, 'service_id' => $service->id]);

        $result = (new ProviderSearchService())->search([
            'service_id' => $service->id,
            'rating_min' => 4.0,
        ]);

        $this->assertSame(1, $result->total());
        $this->assertSame($high->id, $result->items()[0]->id);
    }

    public function test_applies_price_max_including_null_prices(): void
    {
        $service = Service::factory()->create();
        $cheap = $this->makeProvider('+24107007', 0.4, 9.4);
        $expensive = $this->makeProvider('+24107008', 0.4, 9.4);
        $quote = $this->makeProvider('+24107009', 0.4, 9.4);
        ProviderService::factory()->create(['provider_id' => $cheap->id, 'service_id' => $service->id, 'price_amount' => 10000]);
        ProviderService::factory()->create(['provider_id' => $expensive->id, 'service_id' => $service->id, 'price_amount' => 30000]);
        ProviderService::factory()->create(['provider_id' => $quote->id, 'service_id' => $service->id, 'price_amount' => null]);

        $result = (new ProviderSearchService())->search([
            'service_id' => $service->id,
            'price_max' => 15000,
        ]);

        // cheap (10000 <= 15000) + quote (null) = 2 results
        $this->assertSame(2, $result->total());
    }

    public function test_sorts_by_rating_desc_when_no_geo(): void
    {
        $service = Service::factory()->create();
        $a = $this->makeProvider('+24107010', 0.4, 9.4, 3.0);
        $b = $this->makeProvider('+24107011', 0.4, 9.4, 4.8);
        $c = $this->makeProvider('+24107012', 0.4, 9.4, 4.0);
        ProviderService::factory()->create(['provider_id' => $a->id, 'service_id' => $service->id]);
        ProviderService::factory()->create(['provider_id' => $b->id, 'service_id' => $service->id]);
        ProviderService::factory()->create(['provider_id' => $c->id, 'service_id' => $service->id]);

        $result = (new ProviderSearchService())->search(['service_id' => $service->id]);

        $ids = collect($result->items())->pluck('id')->all();
        $this->assertSame([$b->id, $c->id, $a->id], $ids);
    }

    public function test_excludes_pending_and_suspended_providers(): void
    {
        $service = Service::factory()->create();
        $active = $this->makeProvider('+24107013', 0.4, 9.4);
        $pending = User::factory()->prestataire()->pending()->create(['phone' => '+24107014']);
        Profile::factory()->create(['user_id' => $pending->id, 'latitude' => 0.4, 'longitude' => 9.4]);
        $suspended = User::factory()->prestataire()->suspended()->create(['phone' => '+24107015']);
        Profile::factory()->create(['user_id' => $suspended->id, 'latitude' => 0.4, 'longitude' => 9.4]);

        ProviderService::factory()->create(['provider_id' => $active->id, 'service_id' => $service->id]);
        ProviderService::factory()->create(['provider_id' => $pending->id, 'service_id' => $service->id]);
        ProviderService::factory()->create(['provider_id' => $suspended->id, 'service_id' => $service->id]);

        $result = (new ProviderSearchService())->search(['service_id' => $service->id]);

        $this->assertSame(1, $result->total());
        $this->assertSame($active->id, $result->items()[0]->id);
    }

    public function test_excludes_unavailable_offers(): void
    {
        $service = Service::factory()->create();
        $p1 = $this->makeProvider('+24107016', 0.4, 9.4);
        $p2 = $this->makeProvider('+24107017', 0.4, 9.4);
        ProviderService::factory()->create(['provider_id' => $p1->id, 'service_id' => $service->id, 'is_available' => true]);
        ProviderService::factory()->unavailable()->create(['provider_id' => $p2->id, 'service_id' => $service->id]);

        $result = (new ProviderSearchService())->search(['service_id' => $service->id]);

        $this->assertSame(1, $result->total());
        $this->assertSame($p1->id, $result->items()[0]->id);
    }

    public function test_paginates_15_per_page(): void
    {
        $service = Service::factory()->create();
        for ($i = 0; $i < 20; $i++) {
            $p = $this->makeProvider('+24107' . str_pad((string)(100+$i), 3, '0', STR_PAD_LEFT), 0.4, 9.4);
            ProviderService::factory()->create(['provider_id' => $p->id, 'service_id' => $service->id]);
        }

        $result = (new ProviderSearchService())->search(['service_id' => $service->id]);

        $this->assertSame(15, $result->perPage());
        $this->assertSame(20, $result->total());
        $this->assertSame(2, $result->lastPage());
    }

    public function test_sort_by_distance_when_geo(): void
    {
        $service = Service::factory()->create();
        // closer
        $near = $this->makeProvider('+24107018', 0.42, 9.47);
        // farther
        $far = $this->makeProvider('+24107019', 0.5, 9.47);
        ProviderService::factory()->create(['provider_id' => $near->id, 'service_id' => $service->id]);
        ProviderService::factory()->create(['provider_id' => $far->id, 'service_id' => $service->id]);

        $result = (new ProviderSearchService())->search([
            'service_id' => $service->id,
            'lat' => 0.4162,
            'lng' => 9.4673,
            'radius_km' => 100,
        ]);

        $items = $result->items();
        $this->assertSame($near->id, $items[0]->id);
        $this->assertSame($far->id, $items[1]->id);
        $this->assertLessThan($items[1]->distance_km, $items[0]->distance_km);
    }
}
