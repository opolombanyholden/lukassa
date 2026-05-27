<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\ResendOtpRequest;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Profile;
use App\Models\User;
use App\Services\AuthService;
use App\Services\Otp\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function verifyOtp(VerifyOtpRequest $request, OtpService $otp)
    {
        $data = $request->validated();
        $user = User::where('phone', $data['phone'])->first();

        if (!$user) {
            throw ApiException::otpInvalid();
        }

        $otp->verify($user, $data['otp'], 'verify_account');

        $user->forceFill(['status' => 'active'])->save();
        $user->load('profile');

        return ApiResponse::success([
            'user' => new UserResource($user),
            'message' => 'Compte activé.',
        ]);
    }
    public function resendOtp(ResendOtpRequest $request, OtpService $otp)
    {
        $user = User::where('phone', $request->validated()['phone'])->first();

        if ($user && $user->otp_type) {
            $code = $otp->generateFor($user, $user->otp_type);
            $payload = ['message' => 'OTP renvoyé.'];
            if (config('otp.sender') === 'fake') {
                $payload['_dev_otp'] = $code;
            }
            return ApiResponse::success($payload);
        }

        // Anti-leak : on retourne 200 même si user n'existe pas
        return ApiResponse::success(['message' => 'OTP renvoyé.']);
    }
    public function login(LoginRequest $request, AuthService $auth)
    {
        $data = $request->validated();
        $user = $auth->attemptLogin($data['phone'], $data['password']);
        $user->load('profile');

        $deviceName = $data['device_name'] ?? null;

        if ($deviceName) {
            $token = $user->createToken($deviceName)->plainTextToken;
            return ApiResponse::success([
                'user' => new UserResource($user),
                'token' => $token,
            ]);
        }

        // Stateful (web) : pose le cookie de session
        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
        return ApiResponse::success(['user' => new UserResource($user)]);
    }
    public function forgotPassword(Request $request) { abort(501); }
    public function resetPassword(Request $request) { abort(501); }
    public function user(Request $request) { abort(501); }
    public function logout(Request $request) { abort(501); }
}
