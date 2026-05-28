<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\SearchProvidersRequest;
use App\Http\Resources\Api\V1\ProviderSearchResultResource;
use App\Http\Resources\Api\V1\PublicProviderResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\ProviderSearch\ProviderSearchService;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    public function search(SearchProvidersRequest $request, ProviderSearchService $service)
    {
        $results = $service->search($request->validated());

        return ApiResponse::paginated(
            $results,
            fn ($u) => (new ProviderSearchResultResource($u))->toArray($request)
        );
    }

    public function show(string $id, Request $request)
    {
        $user = User::where('id', $id)
            ->where('type', 'prestataire')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->with([
                'profile',
                'providerServices' => fn ($q) => $q->where('is_available', true)->with('service.category'),
            ])
            ->first();

        if (!$user) {
            abort(404, 'Prestataire introuvable.');
        }

        return ApiResponse::success((new PublicProviderResource($user))->toArray($request));
    }
}
