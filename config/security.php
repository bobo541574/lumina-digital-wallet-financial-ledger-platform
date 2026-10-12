<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    |
    | Named rate limiters enforced via the `throttle` middleware. Each entry is
    | "requests per minute". Auth is keyed by IP; money/admin by actor, so a
    | shared NAT cannot lock out unrelated users.
    |
    */

    'rate_limits' => [
        'auth' => (int) env('RATE_LIMIT_AUTH', 10),
        'webhooks' => (int) env('RATE_LIMIT_WEBHOOKS', 120),
        'money' => (int) env('RATE_LIMIT_MONEY', 60),
        'admin' => (int) env('RATE_LIMIT_ADMIN', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-factor authentication
    |--------------------------------------------------------------------------
    |
    | When `admin_required` is true, staff roles (Admin, FinanceOperator) must
    | have enrolled and confirmed TOTP before their admin endpoints respond.
    | Disabled by default so non-MFA environments keep working; enable in prod.
    |
    */

    'two_factor' => [
        'issuer' => env('TWO_FACTOR_ISSUER', env('APP_NAME', 'Lumina')),
        'window' => (int) env('TWO_FACTOR_WINDOW', 1),
        'recovery_codes' => (int) env('TWO_FACTOR_RECOVERY_CODES', 8),
        'challenge_ttl' => (int) env('TWO_FACTOR_CHALLENGE_TTL', 300),
        'admin_required' => (bool) env('SECURITY_ADMIN_MFA_REQUIRED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Response security headers
    |--------------------------------------------------------------------------
    |
    | Baseline headers are always applied by the SecurityHeaders middleware.
    | HSTS requires TLS; CSP is nonce-based and off by default because enabling
    | it before every asset/inline allowance is verified can break the SPA.
    |
    */

    'headers' => [
        'hsts' => (bool) env('SECURITY_HSTS', false),
        'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
        'csp_enabled' => (bool) env('SECURITY_CSP_ENABLED', false),
        'csp_report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', true),

        // Extra origins allowed by `connect-src` (e.g. an absolute API origin or
        // a dedicated WebSocket host when it is not the app origin).
        'csp_connect_extra' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SECURITY_CSP_CONNECT_EXTRA', '')),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redaction
    |--------------------------------------------------------------------------
    |
    | Keys whose values are masked before being written to logs or stored on
    | audit records (case-insensitive substring match on the key name).
    |
    */

    'redact_keys' => [
        'password',
        'password_confirmation',
        'secret',
        'token',
        'authorization',
        'signature',
        'sign',
        'hashvalue',
        'api_key',
        'private_key',
        'client_secret',
        'two_factor',
        'recovery_code',
        'card',
        'cvv',
        'pin',
    ],

];
