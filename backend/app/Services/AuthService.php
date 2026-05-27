<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    private const FAIL_KEY_PREFIX = 'login_fails:';

    public function attemptLogin(string $phone, string $password): User
    {
        $user = User::where('phone', $phone)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            $this->recordFailedAttempt($phone, $user);
            throw ApiException::invalidCredentials();
        }

        if ($user->status === 'pending') {
            throw ApiException::accountNotVerified();
        }

        if ($user->status === 'suspended') {
            throw ApiException::accountSuspended();
        }

        if ($user->status === 'deleted') {
            // Anti-leak : même message qu'identifiants invalides
            throw ApiException::invalidCredentials();
        }

        Cache::forget(self::FAIL_KEY_PREFIX . $phone);
        return $user;
    }

    private function recordFailedAttempt(string $phone, ?User $user): void
    {
        $key = self::FAIL_KEY_PREFIX . $phone;
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addHour());

        $max = (int) env('LOGIN_MAX_FAILS_PER_HOUR', 10);
        if ($user && $count >= $max && $user->status === 'active') {
            $user->forceFill(['status' => 'suspended'])->save();
        }
    }
}
