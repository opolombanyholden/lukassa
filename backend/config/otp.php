<?php

return [
    'sender' => env('OTP_SENDER', 'log'),
    'code_length' => env('OTP_CODE_LENGTH', 6),
    'expiration_minutes' => env('OTP_EXPIRATION_MINUTES', 10),
    'max_attempts' => env('OTP_MAX_ATTEMPTS', 5),
];
