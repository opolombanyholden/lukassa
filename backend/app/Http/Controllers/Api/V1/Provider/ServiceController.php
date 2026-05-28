<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Provider\StoreProviderServiceRequest;
use App\Http\Requests\Api\V1\Provider\UpdateProviderServiceRequest;
use App\Http\Resources\Api\V1\ProviderServiceResource;
use App\Http\Responses\ApiResponse;
use App\Models\ProviderService;
use App\Models\User;
use Illuminate\Http\Request;

final class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $provider = $this->ensureProvider($request);

        $offers = $provider->providerServices()
            ->with('service.category')
            ->orderByDesc('created_at')
            ->paginate(15);

        return ApiResponse::paginated(
            $offers,
            fn ($ps) => (new ProviderServiceResource($ps))->toArray($request)
        );
    }

    public function store(StoreProviderServiceRequest $request)
    {
        $provider = $this->ensureProvider($request);
        $data = $request->validated();

        if ($provider->providerServices()->where('service_id', $data['service_id'])->exists()) {
            throw new ApiException(
                'CATALOG_001',
                422,
                'Tu proposes déjà ce service.',
                ['hint' => 'Utilise PUT /provider/services/{id} pour modifier.']
            );
        }

        $offer = $provider->providerServices()->create($data);
        $offer->load('service.category');

        return ApiResponse::success((new ProviderServiceResource($offer))->toArray($request), 201);
    }

    public function update(UpdateProviderServiceRequest $request, string $id)
    {
        $provider = $this->ensureProvider($request);
        $offer = $this->findOwnedOffer($provider, $id);

        $offer->update($request->validated());
        $offer->load('service.category');

        return ApiResponse::success((new ProviderServiceResource($offer))->toArray($request));
    }

    public function destroy(Request $request, string $id)
    {
        $provider = $this->ensureProvider($request);
        $offer = $this->findOwnedOffer($provider, $id);
        $offer->delete();

        return response()->noContent();
    }

    private function ensureProvider(Request $request): User
    {
        $user = $request->user();
        if ($user->type !== 'prestataire') {
            throw ApiException::accountUnauthorized();
        }
        return $user;
    }

    private function findOwnedOffer(User $provider, string $id): ProviderService
    {
        $offer = ProviderService::where('id', $id)
            ->where('provider_id', $provider->id)
            ->first();
        if (!$offer) {
            abort(404, 'Offre introuvable.');
        }
        return $offer;
    }
}
