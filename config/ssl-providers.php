<?php
return [
    /*
    |--------------------------------------------------------------------------
    | SSL Enhanced Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Enhanced SSL SaaS service with multi-provider support
    |
    */

    'default_provider' => env('SSL_DEFAULT_PROVIDER', 'gogetssl'),

    'auto_select_provider' => env('SSL_AUTO_SELECT_PROVIDER', true),

    'providers' => [
        'gogetssl' => [
            'enabled' => env('GOGETSSL_ENABLED', true),
            'name' => 'GoGetSSL',
            'priority' => 100,
            'features' => [
                'certificate_download' => true,
                'ev_certificates' => true,
                'ov_certificates' => true,
                'dv_certificates' => true,
                'wildcard_certificates' => true,
                'multi_domain_certificates' => true,
                'email_validation' => true,
                'file_validation' => true,
                'dns_validation' => true,
            ],
            'limits' => [
                'max_domains_per_certificate' => 250,
                'max_san_domains' => 249,
                'min_validity_period' => 12, // months
                'max_validity_period' => 36, // months
            ],
            'pricing' => [
                'model' => 'per_certificate',
                'currency' => 'USD',
                'estimated_cost_dv' => 9.99,
                'estimated_cost_ov' => 49.99,
                'estimated_cost_ev' => 99.99,
            ],
            'validation' => [
                'default_method' => 'dns',
                'supported_methods' => ['email', 'file', 'dns'],
                'email_validation_time' => '5-30 minutes',
                'file_validation_time' => '5-30 minutes',
                'dns_validation_time' => '5-30 minutes',
            ]
        ],

        'google_certificate_manager' => [
            'enabled' => env('GOOGLE_CERT_MANAGER_ENABLED', false),
            'name' => 'Google Certificate Manager',
            'priority' => 90,
            'features' => [
                'certificate_download' => false,
                'ev_certificates' => false,
                'ov_certificates' => false,
                'dv_certificates' => true,
                'wildcard_certificates' => true,
                'multi_domain_certificates' => true,
                'automatic_renewal' => true,
                'google_cloud_integration' => true,
                'load_balancer_integration' => true,
                'zero_downtime_renewal' => true,
            ],
            'limits' => [
                'max_domains_per_certificate' => 100,
                'max_certificates_per_project' => 10000,
                'min_validity_period' => 'automatic', // Google manages
                'max_validity_period' => 'automatic', // Google manages
            ],
            'pricing' => [
                'model' => 'usage_based',
                'currency' => 'USD',
                'free_tier_limit' => 100,
                'cost_per_certificate_above_free' => 0.75, // per month
                'load_balancer_required' => true,
            ],
            'validation' => [
                'default_method' => 'dns',
                'supported_methods' => ['dns'],
                'dns_validation_time' => '15-60 minutes',
                'automatic_validation' => true,
            ],
            'requirements' => [
                'google_cloud_project' => true,
                'service_account_key' => true,
                'certificate_manager_api_enabled' => true,
            ]
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Selection Rules
    |--------------------------------------------------------------------------
    |
    | Rules for automatic provider selection based on requirements
    |
    */

    'selection_rules' => [
        'prefer_free_tier' => env('SSL_PREFER_FREE_TIER', true),
        'prefer_automatic_renewal' => env('SSL_PREFER_AUTO_RENEWAL', true),
        'prefer_cloud_integration' => env('SSL_PREFER_CLOUD_INTEGRATION', false),

        'domain_count_thresholds' => [
            'small' => 5,      // 1-5 domains
            'medium' => 20,    // 6-20 domains  
            'large' => 100,    // 21-100 domains
        ],

        'certificate_type_preferences' => [
            'DV' => ['google_certificate_manager', 'gogetssl'],
            'OV' => ['gogetssl'],
            'EV' => ['gogetssl'],
            'WILDCARD' => ['google_certificate_manager', 'gogetssl'],
            'MULTI_DOMAIN' => ['google_certificate_manager', 'gogetssl'],
        ],

        'use_case_preferences' => [
            'small_business' => 'gogetssl',
            'enterprise' => 'google_certificate_manager',
            'e_commerce' => 'gogetssl',
            'saas_platform' => 'google_certificate_manager',
            'api_services' => 'google_certificate_manager',
            'static_websites' => 'google_certificate_manager',
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Health Checks
    |--------------------------------------------------------------------------
    */

    'monitoring' => [
        'health_check_interval' => env('SSL_HEALTH_CHECK_INTERVAL', 300), // seconds
        'connection_timeout' => env('SSL_CONNECTION_TIMEOUT', 30), // seconds
        'retry_attempts' => env('SSL_RETRY_ATTEMPTS', 3),
        'circuit_breaker_threshold' => env('SSL_CIRCUIT_BREAKER_THRESHOLD', 5), // failures
        'circuit_breaker_timeout' => env('SSL_CIRCUIT_BREAKER_TIMEOUT', 600), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificate Management Settings
    |--------------------------------------------------------------------------
    */

    'certificate_management' => [
        'default_validity_days' => env('SSL_DEFAULT_VALIDITY_DAYS', 90),
        'renewal_threshold_days' => env('SSL_RENEWAL_THRESHOLD_DAYS', 30),
        'auto_renewal_enabled' => env('SSL_AUTO_RENEWAL_ENABLED', true),
        'backup_provider_enabled' => env('SSL_BACKUP_PROVIDER_ENABLED', true),
        'validation_timeout_minutes' => env('SSL_VALIDATION_TIMEOUT', 120),

        'notification_settings' => [
            'certificate_issued' => true,
            'certificate_expiring' => true,
            'certificate_renewal_failed' => true,
            'provider_health_issues' => true,
        ],

        'storage' => [
            'encrypt_private_keys' => true,
            'backup_certificates' => true,
            'retention_days' => 365,
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback and Redundancy
    |--------------------------------------------------------------------------
    */

    'redundancy' => [
        'enable_provider_fallback' => env('SSL_ENABLE_PROVIDER_FALLBACK', true),
        'fallback_order' => [
            'primary' => 'auto_select',
            'secondary' => 'gogetssl',
            'tertiary' => 'google_certificate_manager'
        ],
        'fallback_triggers' => [
            'provider_unavailable' => true,
            'validation_timeout' => true,
            'api_rate_limit' => true,
            'certificate_issuance_failed' => true,
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Development and Testing
    |--------------------------------------------------------------------------
    */

    'development' => [
        'test_mode' => env('SSL_TEST_MODE', env('APP_ENV') !== 'production'),
        'mock_providers' => env('SSL_MOCK_PROVIDERS', false),
        'skip_validation' => env('SSL_SKIP_VALIDATION', false),
        'use_staging_apis' => env('SSL_USE_STAGING_APIS', env('APP_ENV') !== 'production'),

        'test_domains' => [
            'example.com',
            'test.example.com',
            '*.staging.example.com'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging and Debugging
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'level' => env('SSL_LOG_LEVEL', 'info'),
        'log_api_requests' => env('SSL_LOG_API_REQUESTS', false),
        'log_api_responses' => env('SSL_LOG_API_RESPONSES', false),
        'log_validation_details' => env('SSL_LOG_VALIDATION_DETAILS', true),
        'sensitive_data_masking' => true,

        'channels' => [
            'default' => 'ssl',
            'api_requests' => 'ssl_api',
            'validation' => 'ssl_validation',
            'errors' => 'ssl_errors'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */

    'rate_limiting' => [
        'gogetssl' => [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
            'burst_limit' => 10,
        ],
        'google_certificate_manager' => [
            'requests_per_minute' => 300,
            'requests_per_hour' => 10000,
            'burst_limit' => 50,
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */

    'security' => [
        'validate_domains' => true,
        'block_internal_domains' => true,
        'allowed_domain_patterns' => [
            '/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/',
        ],
        'blocked_domain_patterns' => [
            '/localhost/',
            '/127\.0\.0\.1/',
            '/192\.168\./',
            '/10\./',
            '/172\.(1[6-9]|2[0-9]|3[0-1])\./',
        ],
        'max_domains_per_request' => 100,
        'require_domain_ownership_verification' => false,
    ]
];
