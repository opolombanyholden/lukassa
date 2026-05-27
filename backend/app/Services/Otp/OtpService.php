<?php

namespace App\Services\Otp;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    public function __construct(private OtpSenderInterface $sender)
    {
    }

    public function generateFor(User $user, string $type): string
    {
        $length = (int) config('otp.code_length', 6);
        $min = (int) str_pad('1', $length, '0');
        $max = (int) str_pad('9', $length, '9');
        $code = (string) random_int($min, $max);

        $user->forceFill([
            'otp_code_hash' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes((int) config('otp.expiration_minutes', 10)),
            'otp_attempts' => 0,
            'otp_type' => $type,
        ])->save();

        $this->sender->send($user->phone, $code);

        return $code;
    }

    public function verify(User $user, string $otp, string $expectedType): void
    {
        if ($user->otp_type !== $expectedType || !$user->otp_code_hash) {
            throw ApiException::otpInvalid();
        }

        if (!$user->otp_expires_at || $user->otp_expires_at->isPast()) {
            throw ApiException::otpExpired();
        }

        $maxAttempts = (int) config('otp.max_attempts', 5);
        if ($user->otp_attempts >= $maxAttempts) {
            throw ApiException::otpTooManyAttempts();
        }

        if (!Hash::check($otp, $user->otp_code_hash)) {
            $user->increment('otp_attempts');
            $remaining = $maxAttempts - $user->otp_attempts;
            throw ApiException::otpInvalid(['attempts_remaining' => max(0, $remaining)]);
        }

        $user->forceFill([
            'otp_code_hash' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
            'otp_type' => null,
        ])->save();
    }
}
