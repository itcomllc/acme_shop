<?php
return [
    'square_plans' => [
        'basic' => env('SQUARE_PLAN_BASIC'),
        'professional' => env('SQUARE_PLAN_PROFESSIONAL'),
        'enterprise' => env('SQUARE_PLAN_ENTERPRISE')
    ],

    'certificate_validity_days' => env('SSL_CERT_VALIDITY_DAYS', 90),
    'auto_renewal_days' => env('SSL_AUTO_RENEWAL_DAYS', 30),
];