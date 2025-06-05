<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'database'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', 'Laravel Log'),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        // Database logging channel
        'database' => [
            'driver' => 'custom',
            'via' => \App\Logging\DatabaseLoggerFactory::class,
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        // SSL-specific logging channels
        'ssl' => [
            'driver' => 'stack',
            'channels' => ['ssl_file', 'database'],
            'ignore_exceptions' => false,
        ],

        'ssl_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/ssl.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
            'replace_placeholders' => true,
        ],

        'ssl_certificates' => [
            'driver' => 'stack',
            'channels' => ['ssl_certificates_file', 'database'],
            'ignore_exceptions' => false,
        ],

        'ssl_certificates_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/ssl_certificates.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 90,
            'replace_placeholders' => true,
        ],

        'ssl_providers' => [
            'driver' => 'stack',
            'channels' => ['ssl_providers_file', 'database'],
            'ignore_exceptions' => false,
        ],

        'ssl_providers_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/ssl_providers.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
            'replace_placeholders' => true,
        ],

        // Payment and billing logging
        'payment' => [
            'driver' => 'stack',
            'channels' => ['payment_file', 'database'],
            'ignore_exceptions' => false,
        ],

        'payment_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/payment.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 365, // Keep payment logs longer for audit
            'replace_placeholders' => true,
        ],

        // Authentication and security logging
        'auth' => [
            'driver' => 'stack',
            'channels' => ['auth_file', 'database'],
            'ignore_exceptions' => false,
        ],

        'auth_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/auth.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 90,
            'replace_placeholders' => true,
        ],

        'security' => [
            'driver' => 'stack',
            'channels' => ['security_file', 'database'],
            'ignore_exceptions' => false,
        ],

        'security_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/security.log'),
            'level' => env('LOG_LEVEL', 'warning'),
            'days' => 365, // Keep security logs longer for audit
            'replace_placeholders' => true,
        ],

        // API logging
        'api' => [
            'driver' => 'stack',
            'channels' => ['api_file', 'database'],
            'ignore_exceptions' => false,
        ],

        'api_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/api.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
            'replace_placeholders' => true,
        ],

        // Admin activity logging
        'admin' => [
            'driver' => 'stack',
            'channels' => ['admin_file', 'database'],
            'ignore_exceptions' => false,
        ],

        'admin_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/admin.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 365, // Keep admin logs longer for audit
            'replace_placeholders' => true,
        ],

        // System health and monitoring
        'monitoring' => [
            'driver' => 'stack',
            'channels' => ['monitoring_file', 'database'],
            'ignore_exceptions' => false,
        ],

        'monitoring_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/monitoring.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
            'replace_placeholders' => true,
        ],

        // Job and queue logging
        'jobs' => [
            'driver' => 'stack',
            'channels' => ['jobs_file', 'database'],
            'ignore_exceptions' => false,
        ],

        'jobs_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/jobs.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 14,
            'replace_placeholders' => true,
        ],

        // ACME protocol logging
        'acme' => [
            'driver' => 'stack',
            'channels' => ['acme_file', 'database'],
            'ignore_exceptions' => false,
        ],

        'acme_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/acme.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
            'replace_placeholders' => true,
        ],

        // Webhook logging
        'webhooks' => [
            'driver' => 'stack',
            'channels' => ['webhooks_file', 'database'],
            'ignore_exceptions' => false,
        ],

        'webhooks_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/webhooks.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
            'replace_placeholders' => true,
        ],
    ],

];