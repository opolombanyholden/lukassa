<?php

namespace App\Services\ProviderSearch;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ProviderSearchService
{
    public function search(array $filters): LengthAwarePaginator
    {
        $hasGeo = isset($filters['lat'], $filters['lng']);

        $query = User::query()
            ->select([
                'users.*',
                DB::raw($hasGeo
                    ? $this->distanceExpr((float) $filters['lat'], (float) $filters['lng']).' AS distance_km'
                    : 'NULL::float AS distance_km'),
            ])
            ->join('profiles', 'profiles.user_id', '=', 'users.id')
            ->join('provider_services', 'provider_services.provider_id', '=', 'users.id')
            ->where('users.type', 'prestataire')
            ->where('users.status', 'active')
            ->whereNull('users.deleted_at')
            ->where('provider_services.service_id', $filters['service_id'])
            ->where('provider_services.is_available', true)
            ->with([
                'profile',
                'providerServices' => fn ($q) => $q->where('service_id', $filters['service_id'])
                                                    ->where('is_available', true)
                                                    ->with('service'),
            ]);

        if (isset($filters['rating_min'])) {
            $query->where('profiles.average_rating', '>=', $filters['rating_min']);
        }

        if (isset($filters['price_max'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereNull('provider_services.price_amount')
                  ->orWhere('provider_services.price_amount', '<=', $filters['price_max']);
            });
        }

        if ($hasGeo) {
            $radius = $filters['radius_km'] ?? 20;
            $query->whereRaw(
                $this->distanceExpr((float) $filters['lat'], (float) $filters['lng']).' <= ?',
                [$radius]
            );
        }

        $sortBy = $filters['sort_by'] ?? ($hasGeo ? 'distance' : 'rating');
        match ($sortBy) {
            'distance' => $query->orderBy('distance_km'),
            'rating'   => $query->orderByDesc('profiles.average_rating'),
            'price'    => $query->orderBy('provider_services.price_amount'),
        };

        return $query->paginate(15);
    }

    private function distanceExpr(float $lat, float $lng): string
    {
        // SQL injection safe: (float) cast applied to coords (validated by FormRequest upstream).
        return "(6371 * acos(LEAST(1, "
             . "cos(radians($lat)) * cos(radians(profiles.latitude)) "
             . "* cos(radians(profiles.longitude) - radians($lng)) "
             . "+ sin(radians($lat)) * sin(radians(profiles.latitude))"
             . ")))";
    }
}
