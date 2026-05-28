<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ServiceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Service::query()
            ->where('is_active', true)
            ->with('category');

        if ($request->filled('category_slug')) {
            $cat = Category::where('slug', $request->input('category_slug'))->first();
            if ($cat) {
                $query->where('category_id', $cat->id);
            } else {
                $query->whereRaw('1 = 0'); // no results
            }
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($w) use ($q) {
                $w->where('name', 'ILIKE', '%'.$q.'%')
                  ->orWhere('description', 'ILIKE', '%'.$q.'%');
            });
        }

        $services = $query->orderBy('name')->paginate(15);

        return ApiResponse::paginated(
            $services,
            fn ($svc) => (new ServiceResource($svc))->toArray($request)
        );
    }

    public function show(Service $service, Request $request)
    {
        $service->load('category');
        return ApiResponse::success((new ServiceResource($service))->toArray($request));
    }
}
