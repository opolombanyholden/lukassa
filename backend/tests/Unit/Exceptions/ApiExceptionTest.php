<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\ApiException;
use Tests\TestCase;

class ApiExceptionTest extends TestCase
{
    public function test_otp_invalid_factory_sets_code_and_status(): void
    {
        $e = ApiException::otpInvalid(['attempts_remaining' => 3]);
        $this->assertSame('AUTH_001', $e->errorCode);
        $this->assertSame(422, $e->httpStatus);
        $this->assertSame(['attempts_remaining' => 3], $e->details);
    }

    public function test_to_json_response_returns_uniform_error_envelope(): void
    {
        $e = ApiException::accountSuspended();
        $response = $e->toJsonResponse();
        $body = $response->getData(true);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertSame('AUTH_006', $body['error']['code']);
        $this->assertArrayHasKey('timestamp', $body['meta']);
        $this->assertSame('v1', $body['meta']['version']);
    }

    public function test_all_7_factories_produce_distinct_codes(): void
    {
        $codes = [
            ApiException::otpInvalid()->errorCode,
            ApiException::otpExpired()->errorCode,
            ApiException::otpTooManyAttempts()->errorCode,
            ApiException::invalidCredentials()->errorCode,
            ApiException::accountNotVerified()->errorCode,
            ApiException::accountSuspended()->errorCode,
            ApiException::invalidAccountType()->errorCode,
        ];
        $this->assertSame(
            ['AUTH_001', 'AUTH_002', 'AUTH_003', 'AUTH_004', 'AUTH_005', 'AUTH_006', 'AUTH_007'],
            $codes
        );
    }
}
