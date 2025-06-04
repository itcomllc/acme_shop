<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Basic SSL Configuration
    |--------------------------------------------------------------------------
    |
    | Basic SSL settings that are referenced by legacy code.
    | For advanced SSL configuration, see config/ssl-enhanced.php
    |
    */

    'square_plans' => [
        'basic' => env('SQUARE_PLAN_BASIC'),
        'professional' => env('SQUARE_PLAN_PROFESSIONAL'),
        'enterprise' => env('SQUARE_PLAN_ENTERPRISE')
    ],

    // Referenced in app/Jobs/MonitorCertificateIssuance.php
    'certificate_validity_days' => env('SSL_CERT_VALIDITY_DAYS', 90),
    'auto_renewal_days' => env('SSL_AUTO_RENEWAL_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Legacy Settings
    |--------------------------------------------------------------------------
    |
    | These settings are kept for backward compatibility.
    | New code should use config/ssl-enhanced.php instead.
    |
    */

    'default_provider' => env('SSL_DEFAULT_PROVIDER', 'google_certificate_manager'),
    'provider_timeout' => env('SSL_PROVIDER_TIMEOUT', 30),
];