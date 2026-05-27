<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;

class LogOtpSender implements OtpSenderInterface
{
    public function send(string $phone, string $code): void
    {
        Log::info("[OTP] phone={$phone} code={$code}");
    }
}
