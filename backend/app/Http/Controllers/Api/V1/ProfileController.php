<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $profile = $request->user()->profile ?? $request->user()->profile()->create([]);
        return ApiResponse::success((new ProfileResource($profile))->toArray($request));
    }

    public function update(UpdateProfileRequest $request)
    {
        $profile = $request->user()->profile ?? $request->user()->profile()->create([]);
        $profile->fill($request->validated())->save();
        return ApiResponse::success((new ProfileResource($profile->fresh()))->toArray($request));
    }
}
