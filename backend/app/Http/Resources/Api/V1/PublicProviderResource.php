<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->profile;
        return [
            'id' => $this->id,
            'firstname' => $profile?->firstname,
            'lastname' => $profile?->lastname,
            'bio' => $profile?->bio,
            'city' => $profile?->city,
            'country' => $profile?->country,
            'average_rating' => $profile ? (float) $profile->average_rating : 0.0,
            'total_reviews' => $profile?->total_reviews ?? 0,
            'intervention_radius_km' => $profile?->intervention_radius_km,
            'services' => $this->whenLoaded('providerServices', function () use ($request) {
                return $this->providerServices
                    ->where('is_available', true)
                    ->map(fn ($ps) => (new ProviderServiceResource($ps))->toArray($request))
                    ->values()
                    ->all();
            }),
        ];
    }
}
