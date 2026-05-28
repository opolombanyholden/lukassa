<?php

namespace Tests\Unit\Resources;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryWithChildrenTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_tree_returns_only_root_with_nested_children(): void
    {
        $parent = Category::factory()->create([
            'slug' => 'plomberie', 'parent_id' => null, 'order_position' => 1,
        ]);
        Category::factory()->create([
            'slug' => 'fuite', 'parent_id' => $parent->id, 'order_position' => 1,
        ]);
        Category::factory()->create([
            'slug' => 'sanitaires', 'parent_id' => $parent->id, 'order_position' => 2,
        ]);

        $all = Category::all();
        $tree = \App\Http\Controllers\Api\V1\Public\CategoryController::buildTree($all);

        $this->assertCount(1, $tree);
        $this->assertSame('plomberie', $tree[0]['slug']);
        $this->assertCount(2, $tree[0]['children']);
        $this->assertSame('fuite', $tree[0]['children'][0]['slug']);
        $this->assertSame('sanitaires', $tree[0]['children'][1]['slug']);
    }

    public function test_build_tree_respects_order_position(): void
    {
        Category::factory()->create(['slug' => 'b', 'parent_id' => null, 'order_position' => 2]);
        Category::factory()->create(['slug' => 'a', 'parent_id' => null, 'order_position' => 1]);

        $tree = \App\Http\Controllers\Api\V1\Public\CategoryController::buildTree(Category::all());

        $this->assertSame('a', $tree[0]['slug']);
        $this->assertSame('b', $tree[1]['slug']);
    }
}
