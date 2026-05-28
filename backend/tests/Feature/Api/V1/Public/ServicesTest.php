<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_services(): void
    {
        Service::factory()->count(20)->create();

        $response = $this->getJson('/api/v1/public/services');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta' => ['pagination' => ['current_page', 'last_page', 'per_page', 'total']]]);

        $this->assertSame(15, count($response->json('data')));
        $this->assertSame(20, $response->json('meta.pagination.total'));
    }

    public function test_index_filters_by_category_slug(): void
    {
        $cat = Category::factory()->create(['slug' => 'plomberie']);
        Service::factory()->count(3)->create(['category_id' => $cat->id]);
        Service::factory()->count(2)->create(); // other category

        $response = $this->getJson('/api/v1/public/services?category_slug=plomberie');

        $response->assertStatus(200);
        $this->assertSame(3, $response->json('meta.pagination.total'));
    }

    public function test_index_supports_q_param(): void
    {
        Service::factory()->create(['name' => 'Tissage femme']);
        Service::factory()->create(['name' => 'Vidange voiture']);

        $response = $this->getJson('/api/v1/public/services?q=tissage');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.pagination.total'));
        $this->assertSame('Tissage femme', $response->json('data.0.name'));
    }

    public function test_show_returns_service_by_slug(): void
    {
        $cat = Category::factory()->create(['name' => 'Plomberie']);
        Service::factory()->create(['slug' => 'fuite-robinet', 'name' => 'Fuite robinet', 'category_id' => $cat->id]);

        $response = $this->getJson('/api/v1/public/services/fuite-robinet');

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Fuite robinet')
            ->assertJsonPath('data.category.name', 'Plomberie');
    }

    public function test_show_unknown_slug_returns_404(): void
    {
        $this->getJson('/api/v1/public/services/nonexistent')->assertStatus(404);
    }
}
