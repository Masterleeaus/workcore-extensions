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
];
