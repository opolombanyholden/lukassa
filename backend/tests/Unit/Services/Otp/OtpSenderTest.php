<?php

namespace Tests\Unit\Services\Otp;

use App\Services\Otp\FakeOtpSender;
use App\Services\Otp\LogOtpSender;
use App\Services\Otp\OtpSenderInterface;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OtpSenderTest extends TestCase
{
    public function test_log_sender_implements_interface(): void
    {
        $this->assertInstanceOf(OtpSenderInterface::class, new LogOtpSender());
    }

    public function test_fake_sender_implements_interface(): void
    {
        $this->assertInstanceOf(OtpSenderInterface::class, new FakeOtpSender());
    }

    public function test_log_sender_writes_to_log_channel(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('[OTP] phone=+24107000000 code=123456');

        (new LogOtpSender())->send('+24107000000', '123456');
    }

    public function test_fake_sender_records_last_sent_for_assertions(): void
    {
        FakeOtpSender::reset();
        $sender = new FakeOtpSender();
        $sender->send('+24107000000', '654321');

        $last = FakeOtpSender::lastSent();
        $this->assertSame('+24107000000', $last['phone']);
        $this->assertSame('654321', $last['code']);
    }

    public function test_service_provider_binds_correct_implementation_for_fake(): void
    {
        config(['otp.sender' => 'fake']);
        $this->app->forgetInstance(OtpSenderInterface::class);
        $sender = $this->app->make(OtpSenderInterface::class);
        $this->assertInstanceOf(FakeOtpSender::class, $sender);
    }

    public function test_service_provider_binds_log_for_log_config(): void
    {
        config(['otp.sender' => 'log']);
        $this->app->forgetInstance(OtpSenderInterface::class);
        $sender = $this->app->make(OtpSenderInterface::class);
        $this->assertInstanceOf(LogOtpSender::class, $sender);
    }
}
