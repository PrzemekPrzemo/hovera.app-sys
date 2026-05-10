<?php

declare(strict_types=1);

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return [
    'stateful' => explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        env('APP_URL') ? ','.parse_url((string) env('APP_URL'), PHP_URL_HOST) : ''
    ))),

    'guard' => ['web'],

    // 30 days. Mobile clients refresh proactively when token age > 21 days.
    'expiration' => 60 * 24 * 30,

    // Token prefix lets us scan-block leaked tokens (GitHub secret scanning).
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'hov_'),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
