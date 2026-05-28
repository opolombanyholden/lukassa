<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderSearchResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->profile;
        $matchingOffer = $this->providerServices->first();

        return [
            'id' => $this->id,
            'firstname' => $profile?->firstname,
            'lastname' => $profile?->lastname,
            'city' => $profile?->city,
            'average_rating' => $profile ? (float) $profile->average_rating : 0.0,
            'total_reviews' => $profile?->total_reviews ?? 0,
            'distance_km' => $this->distance_km !== null ? round((float) $this->distance_km, 2) : null,
            'service' => $matchingOffer ? [
                'id' => $matchingOffer->service_id,
                'name' => $matchingOffer->service?->name,
                'price_amount' => $matchingOffer->price_amount,
                'price_model' => $matchingOffer->price_model,
            ] : null,
        ];
    }
}
