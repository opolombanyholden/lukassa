<?php

namespace App\Services\Otp;

interface OtpSenderInterface
{
    public function send(string $phone, string $code): void;
}
