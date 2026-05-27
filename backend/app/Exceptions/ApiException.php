<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class ApiException extends \Exception
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $httpStatus,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function toJsonResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'details' => $this->details,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'version' => 'v1',
            ],
        ], $this->httpStatus);
    }

    public static function otpInvalid(array $details = []): self
    {
        return new self('AUTH_001', 422, 'Code OTP invalide.', $details);
    }

    public static function otpExpired(): self
    {
        return new self('AUTH_002', 422, 'Code OTP expiré, demande un nouveau code.');
    }

    public static function otpTooManyAttempts(): self
    {
        return new self('AUTH_003', 429, 'Trop de tentatives OTP, demande un nouveau code.');
    }

    public static function invalidCredentials(): self
    {
        return new self('AUTH_004', 401, 'Identifiants invalides.');
    }

    public static function accountNotVerified(): self
    {
        return new self('AUTH_005', 403, 'Compte non vérifié. Confirme ton numéro via OTP.');
    }

    public static function accountSuspended(): self
    {
        return new self('AUTH_006', 403, 'Compte suspendu. Contacte le support.');
    }

    public static function invalidAccountType(): self
    {
        return new self('AUTH_007', 422, 'Type de compte invalide.');
    }
}
