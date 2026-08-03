<?php

return [
    'enabled' => env('WORKCORE_NATIVE_ENABLED', true),
    'uninstall_policy' => 'retain',
    'runtime_namespace' => 'App\\Domains\\WorkCore',
    'api_middleware' => [
        'api',
        env('WORKCORE_NATIVE_AUTH_MIDDLEWARE', 'auth:api'),
        'workcore.tenant',
        'workcore.api',
    ],
    'approvals' => [
        'signing_key' => env('WORKCORE_CONFIRMATION_SIGNING_KEY'),
        'key_id' => env('WORKCORE_CONFIRMATION_KEY_ID', 'primary'),
        'ttl_seconds' => (int) env('WORKCORE_CONFIRMATION_TTL_SECONDS', 300),
        'enforce_all' => true,
        'allow_legacy_human_confirmation' => false,
    ],
];
