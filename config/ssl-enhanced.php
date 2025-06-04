<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default SSL Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default SSL certificate provider that will be
    | used when issuing new certificates. You may change this to any of the
    | providers defined in the "providers" array below.
    |
    */

    'default_provider' => env('SSL_DEFAULT_PROVIDER', 'google_certificate_manager'),

    /*
    |--------------------------------------------------------------------------
    | Provider Timeout
    |--------------------------------------------------------------------------
    |
    | The number of seconds to wait for responses from SSL providers before
    | timing out. This applies to all provider API calls.
    |
    */

    'provider_timeout' => env('SSL_PROVIDER_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Certificate Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for SSL certificate management including auto-renewal
    | settings and validation preferences.
    |
    */

    'certificate' => [
        'auto_renewal_days' => env('SSL_AUTO_RENEWAL_DAYS', 30),
        'validity_days' => env('SSL_VALIDITY_DAYS', 90),
        'key_size' => env('SSL_KEY_SIZE', 2048),
        'key_type' => env('SSL_KEY_TYPE', 'RSA'),
        'organization' => env('SSL_ORGANIZATION', 'SSL SaaS Platform'),
        'country' => env('SSL_COUNTRY', 'US'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SSL Certificate Providers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the SSL certificate providers that your
    | application will use. Each provider has its own configuration
    | requirements and capabilities.
    |
    */

    'providers' => [

        'gogetssl' => [
            'enabled' => env('GOGETSSL_ENABLED', false),
            'priority' => 2,
            'api_key' => env('GOGETSSL_API_KEY'),
            'partner_code' => env('GOGETSSL_PARTNER_CODE'),
            'sandbox' => env('GOGETSSL_SANDBOX', true),
            'base_url' => env('GOGETSSL_BASE_URL', 'https://my.gogetssl.com/api'),
            'auto_renewal' => true,
            'max_validity_days' => 365,
            'supported_types' => ['DV', 'OV', 'EV'],
            'features' => [
                'wildcard_support' => true,
                'san_support' => true,
                'download_support' => true,
                'revocation' => true,
                'auto_validation' => false,
            ],
            'validation_methods' => ['http-01', 'dns-01', 'email'],
            'certificate_formats' => ['pem', 'der', 'p7b', 'p12'],
            'cost' => 'paid',
        ],

        'google_certificate_manager' => [
            'enabled' => env('GOOGLE_CM_ENABLED', true),
            'priority' => 1,
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
            'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
            'location' => env('GOOGLE_CM_LOCATION', 'global'),
            'auto_renewal' => true,
            'max_validity_days' => 90,
            'supported_types' => ['DV'],
            'features' => [
                'wildcard_support' => true,
                'san_support' => true,
                'download_support' => false,
                'revocation' => true,
                'auto_validation' => true,
            ],
            'validation_methods' => ['http-01', 'dns-01'],
            'certificate_formats' => ['pem'],
            'cost' => 'free',
        ],

        'lets_encrypt' => [
            'enabled' => env('LETS_ENCRYPT_ENABLED', true),
            'priority' => 3,
            'directory_url' => env('LETS_ENCRYPT_DIRECTORY_URL', 'https://acme-v02.api.letsencrypt.org/directory'),
            'staging_url' => env('LETS_ENCRYPT_STAGING_URL', 'https://acme-staging-v02.api.letsencrypt.org/directory'),
            'staging' => env('LETS_ENCRYPT_STAGING', false),
            'contact_email' => env('LETS_ENCRYPT_CONTACT_EMAIL', 'admin@example.com'),
            'auto_renewal' => true,
            'max_validity_days' => 90,
            'supported_types' => ['DV'],
            'features' => [
                'wildcard_support' => true,
                'san_support' => true,
                'download_support' => true,
                'revocation' => true,
                'auto_validation' => false,
            ],
            'validation_methods' => ['http-01', 'dns-01'],
            'certificate_formats' => ['pem'],
            'cost' => 'free',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Plan Provider Mapping
    |--------------------------------------------------------------------------
    |
    | Map subscription plans to default SSL providers and their preferences.
    | Referenced in app/Models/Subscription.php
    |
    */

    'plan_providers' => [
        'basic' => [
            'default' => 'google_certificate_manager',
            'fallback' => ['lets_encrypt'],
            'allowed' => ['google_certificate_manager', 'lets_encrypt'],
        ],
        'professional' => [
            'default' => 'gogetssl',
            'fallback' => ['google_certificate_manager', 'lets_encrypt'],
            'allowed' => ['gogetssl', 'google_certificate_manager', 'lets_encrypt'],
        ],
        'enterprise' => [
            'default' => 'gogetssl',
            'fallback' => ['google_certificate_manager', 'lets_encrypt'],
            'allowed' => ['gogetssl', 'google_certificate_manager', 'lets_encrypt'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for monitoring SSL provider health and performance.
    |
    */

    'health_check' => [
        'interval' => env('SSL_HEALTH_CHECK_INTERVAL', 300), // 5 minutes
        'timeout' => env('SSL_HEALTH_CHECK_TIMEOUT', 10),
        'retry_attempts' => env('SSL_HEALTH_CHECK_RETRIES', 3),
        'cache_ttl' => env('SSL_HEALTH_CHECK_CACHE_TTL', 600), // 10 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Alerting
    |--------------------------------------------------------------------------
    |
    | Configuration for SSL certificate monitoring and alerting.
    | Referenced in routes/console.php
    |
    */

    'monitoring' => [
        'enabled' => env('SSL_MONITORING_ENABLED', true),
        'alert_on_failure' => env('SSL_ALERT_ON_FAILURE', true),
        'alert_channels' => env('SSL_ALERT_CHANNELS', 'mail,log'),
        'expiry_alert_days' => [7, 14, 30], // Days before expiry to send alerts
        'check_interval' => env('SSL_MONITORING_INTERVAL', 3600), // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Redundancy and Failover
    |--------------------------------------------------------------------------
    |
    | Settings for provider failover and redundancy handling.
    | Referenced in app/Jobs/ScheduleCertificateRenewal.php and routes/console.php
    |
    */

    'redundancy' => [
        'enable_provider_fallback' => env('SSL_ENABLE_FALLBACK', true),
        'max_retry_attempts' => env('SSL_MAX_RETRY_ATTEMPTS', 3),
        'retry_delay_seconds' => env('SSL_RETRY_DELAY', 60),
        'circuit_breaker_threshold' => env('SSL_CIRCUIT_BREAKER_THRESHOLD', 5),
        'circuit_breaker_timeout' => env('SSL_CIRCUIT_BREAKER_TIMEOUT', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | ACME Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for ACME protocol implementation.
    |
    */

    'acme' => [
        'account_key_type' => env('ACME_ACCOUNT_KEY_TYPE', 'EC'),
        'account_key_size' => env('ACME_ACCOUNT_KEY_SIZE', 256),
        'challenge_timeout' => env('ACME_CHALLENGE_TIMEOUT', 300),
        'challenge_retry_attempts' => env('ACME_CHALLENGE_RETRIES', 5),
        'challenge_retry_delay' => env('ACME_CHALLENGE_RETRY_DELAY', 30),
        'order_timeout' => env('ACME_ORDER_TIMEOUT', 3600), // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting configuration for SSL provider API calls.
    |
    */

    'rate_limiting' => [
        'gogetssl' => [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
            'requests_per_day' => 10000,
        ],
        'google_certificate_manager' => [
            'requests_per_minute' => 600,
            'requests_per_hour' => 10000,
            'requests_per_day' => 100000,
        ],
        'lets_encrypt' => [
            'requests_per_minute' => 300,
            'requests_per_hour' => 3000,
            'requests_per_day' => 10000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for caching SSL-related data.
    |
    */

    'cache' => [
        'provider_status_ttl' => 300, // 5 minutes
        'certificate_status_ttl' => 600, // 10 minutes
        'health_check_ttl' => 300, // 5 minutes
        'rate_limit_ttl' => 3600, // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for SSL provider webhooks.
    |
    */

    'webhooks' => [
        'enabled' => env('SSL_WEBHOOKS_ENABLED', true),
        'signature_verification' => env('SSL_WEBHOOK_VERIFY_SIGNATURE', true),
        'timeout' => env('SSL_WEBHOOK_TIMEOUT', 30),
        'max_attempts' => env('SSL_WEBHOOK_MAX_ATTEMPTS', 3),
        'retry_delay' => env('SSL_WEBHOOK_RETRY_DELAY', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related SSL configuration.
    |
    */

    'security' => [
        'encrypt_private_keys' => env('SSL_ENCRYPT_PRIVATE_KEYS', true),
        'key_rotation_days' => env('SSL_KEY_ROTATION_DAYS', 365),
        'audit_logging' => env('SSL_AUDIT_LOGGING', true),
        'require_strong_ciphers' => env('SSL_REQUIRE_STRONG_CIPHERS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development and Testing
    |--------------------------------------------------------------------------
    |
    | Settings for development and testing environments.
    |
    */

    'development' => [
        'use_staging_providers' => env('SSL_USE_STAGING', false),
        'mock_providers' => env('SSL_MOCK_PROVIDERS', false),
        'debug_logging' => env('SSL_DEBUG_LOGGING', false),
        'test_domains' => env('SSL_TEST_DOMAINS', 'test.example.com'),
    ],

];