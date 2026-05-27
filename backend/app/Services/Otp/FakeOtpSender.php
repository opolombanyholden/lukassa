<?php

namespace App\Services\Otp;

class FakeOtpSender implements OtpSenderInterface
{
    private static array $sent = [];

    public function send(string $phone, string $code): void
    {
        self::$sent[] = ['phone' => $phone, 'code' => $code, 'at' => now()];
    }

    public static function lastSent(): ?array
    {
        return self::$sent ? self::$sent[array_key_last(self::$sent)] : null;
    }

    public static function all(): array
    {
        return self::$sent;
    }

    public static function reset(): void
    {
        self::$sent = [];
    }
}
