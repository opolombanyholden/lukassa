<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_uuid_auto_generated(): void
    {
        $cat = Category::factory()->create();
        $this->assertSame(36, strlen($cat->id));
    }

    public function test_slug_must_be_unique(): void
    {
        Category::factory()->create(['slug' => 'plomberie']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        Category::factory()->create(['slug' => 'plomberie']);
    }

    public function test_parent_children_relations(): void
    {
        $parent = Category::factory()->create(['name' => 'Plomberie', 'slug' => 'plomberie']);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'slug' => 'plomberie-fuite']);

        $this->assertSame($parent->id, $child->parent->id);
        $this->assertSame($child->id, $parent->children->first()->id);
    }

    public function test_route_key_is_slug(): void
    {
        $cat = Category::factory()->create(['slug' => 'electricite']);
        $this->assertSame('slug', $cat->getRouteKeyName());
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $cat = Category::factory()->create(['is_active' => 1]);
        $this->assertSame(true, $cat->fresh()->is_active);
    }
}
