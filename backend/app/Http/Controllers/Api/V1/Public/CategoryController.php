<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\ServiceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::where('is_active', true)
            ->orderBy('order_position')
            ->get();

        return ApiResponse::success(
            $categories->map(fn ($cat) => (new CategoryResource($cat))->toArray(request()))->values()->all()
        );
    }

    public function tree()
    {
        $tree = Cache::remember('categories:tree', now()->addHour(), function () {
            $all = Category::where('is_active', true)
                ->orderBy('order_position')
                ->get();
            return self::buildTree($all);
        });

        return ApiResponse::success($tree);
    }

    public function services(Category $category, Request $request)
    {
        $services = $category->services()
            ->where('is_active', true)
            ->with('category')
            ->orderBy('name')
            ->paginate(15);

        return ApiResponse::paginated(
            $services,
            fn ($svc) => (new ServiceResource($svc))->toArray($request)
        );
    }

    public static function buildTree(Collection $all): array
    {
        return $all->whereNull('parent_id')
            ->sortBy('order_position')
            ->map(fn ($cat) => self::nodeArray($cat, $all))
            ->values()
            ->all();
    }

    private static function nodeArray(Category $cat, Collection $all): array
    {
        return [
            'id' => $cat->id,
            'slug' => $cat->slug,
            'name' => $cat->name,
            'icon' => $cat->icon,
            'description' => $cat->description,
            'children' => $all->where('parent_id', $cat->id)
                ->sortBy('order_position')
                ->map(fn ($child) => self::nodeArray($child, $all))
                ->values()
                ->all(),
        ];
    }
}
