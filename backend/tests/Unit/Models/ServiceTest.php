<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_uuid_auto_generated(): void
    {
        $service = Service::factory()->create();
        $this->assertSame(36, strlen($service->id));
    }

    public function test_belongs_to_category(): void
    {
        $cat = Category::factory()->create();
        $service = Service::factory()->create(['category_id' => $cat->id]);
        $this->assertSame($cat->id, $service->category->id);
    }

    public function test_route_key_is_slug(): void
    {
        $service = Service::factory()->create(['slug' => 'tissage']);
        $this->assertSame('slug', $service->getRouteKeyName());
    }

    public function test_requires_quote_cast(): void
    {
        $service = Service::factory()->create(['requires_quote' => 1]);
        $this->assertTrue($service->fresh()->requires_quote);
    }
}
