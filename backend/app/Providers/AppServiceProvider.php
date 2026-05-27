<?php

namespace App\Providers;

use App\Services\Otp\FakeOtpSender;
use App\Services\Otp\LogOtpSender;
use App\Services\Otp\OtpSenderInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OtpSenderInterface::class, function ($app) {
            return match (config('otp.sender')) {
                'fake' => new FakeOtpSender(),
                'log'  => new LogOtpSender(),
                default => throw new \RuntimeException(
                    'Unknown OTP_SENDER value: '.config('otp.sender')
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
