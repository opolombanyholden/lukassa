<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phone' => $this->phone,
            'email' => $this->email,
            'type' => $this->type,
            'status' => $this->status,
            'identity_verified_at' => $this->identity_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'profile' => $this->whenLoaded('profile', function () {
                return [
                    'firstname' => $this->profile->firstname,
                    'lastname' => $this->profile->lastname,
                ];
            }),
        ];
    }
}
