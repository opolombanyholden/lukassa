<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Profile;
use App\Models\User;
use App\Services\Otp\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, OtpService $otp)
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'type' => $data['type'],
            ]);
            // status n'est pas dans fillable — restera 'pending' (DB default)
            Profile::create(['user_id' => $user->id]);
            return $user;
        });

        $code = $otp->generateFor($user, 'verify_account');

        $payload = [
            'user_id' => $user->id,
            'message' => 'OTP envoyé à votre numéro.',
        ];
        if (config('otp.sender') === 'fake') {
            $payload['_dev_otp'] = $code;
        }

        return ApiResponse::success($payload, 201);
    }

    public function verifyOtp(Request $request) { abort(501); }
    public function resendOtp(Request $request) { abort(501); }
    public function login(Request $request) { abort(501); }
    public function forgotPassword(Request $request) { abort(501); }
    public function resetPassword(Request $request) { abort(501); }
    public function user(Request $request) { abort(501); }
    public function logout(Request $request) { abort(501); }
}
