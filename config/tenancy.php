<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Central Domain
    |--------------------------------------------------------------------------
    |
    | Tenants are served from "{slug}.{domain}" and the public site (signup,
    | landing page) from the bare domain. Use "localhost" for local work
    | without Sail: browsers resolve "*.localhost" to 127.0.0.1.
    |
    */

    'domain' => env('APP_DOMAIN', 'facilitydesk.test'),

    /*
    |--------------------------------------------------------------------------
    | Reserved Slugs
    |--------------------------------------------------------------------------
    |
    | Subdomains that can never be claimed by a tenant at signup.
    |
    */

    'reserved_slugs' => [
        'admin', 'api', 'app', 'assets', 'billing', 'cdn', 'dashboard', 'docs',
        'help', 'mail', 'static', 'status', 'support', 'www',
    ],

    'trial_days' => (int) env('TENANT_TRIAL_DAYS', 14),

    // How long a "set your password" invitation link stays valid.
    'invitation_expiry_hours' => 72,

];
