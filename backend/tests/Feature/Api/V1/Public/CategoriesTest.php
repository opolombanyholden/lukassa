<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CategoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_index_returns_active_categories(): void
    {
        Category::factory()->create(['name' => 'Plomberie', 'is_active' => true]);
        Category::factory()->inactive()->create(['name' => 'Hidden']);

        $response = $this->getJson('/api/v1/public/categories');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Plomberie');
    }

    public function test_tree_returns_hierarchical_structure(): void
    {
        $parent = Category::factory()->create([
            'name' => 'Plomberie', 'slug' => 'plomberie', 'parent_id' => null,
        ]);
        Category::factory()->create([
            'name' => 'Fuite', 'slug' => 'fuite', 'parent_id' => $parent->id,
        ]);

        $response = $this->getJson('/api/v1/public/categories/tree');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.slug', 'plomberie')
            ->assertJsonPath('data.0.children.0.slug', 'fuite');
    }

    public function test_tree_excludes_inactive_categories(): void
    {
        Category::factory()->inactive()->create(['parent_id' => null]);
        Category::factory()->create(['parent_id' => null]);

        $response = $this->getJson('/api/v1/public/categories/tree');
        $this->assertCount(1, $response->json('data'));
    }

    public function test_tree_result_is_cached(): void
    {
        Category::factory()->create(['parent_id' => null]);
        $this->getJson('/api/v1/public/categories/tree')->assertStatus(200);
        $this->assertTrue(Cache::has('categories:tree'));
    }

    public function test_services_in_category_returns_paginated_list(): void
    {
        $cat = Category::factory()->create(['slug' => 'plomberie']);
        Service::factory()->count(3)->create(['category_id' => $cat->id]);

        $response = $this->getJson('/api/v1/public/categories/plomberie/services');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_services_in_unknown_category_returns_404(): void
    {
        $this->getJson('/api/v1/public/categories/nonexistent/services')
            ->assertStatus(404);
    }

    public function test_cache_invalidated_when_category_saved(): void
    {
        Category::factory()->create(['parent_id' => null]);
        $this->getJson('/api/v1/public/categories/tree'); // populates cache
        $this->assertTrue(Cache::has('categories:tree'));

        Category::factory()->create(['parent_id' => null]);
        $this->assertFalse(Cache::has('categories:tree'));
    }
}
