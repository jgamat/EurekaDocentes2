<?php

return [
    'db_connection' => env('AUTH_LOG_DB_CONNECTION'),
    'table_name' => env('AUTH_LOG_TABLE', 'authentication_log'),

    'behind_cdn' => false,
    'behind_cdn.http_header_field' => 'HTTP_X_FORWARDED_FOR',

    'events' => [
        'login' => \Illuminate\Auth\Events\Login::class,
        'failed' => \Illuminate\Auth\Events\Failed::class,
        'logout' => \Illuminate\Auth\Events\Logout::class,
        'other-device-logout' => \Illuminate\Auth\Events\OtherDeviceLogout::class,
    ],

    'listeners' => [
        'login' => \Rappasoft\LaravelAuthenticationLog\Listeners\LoginListener::class,
        'failed' => \Rappasoft\LaravelAuthenticationLog\Listeners\FailedLoginListener::class,
        'logout' => \Rappasoft\LaravelAuthenticationLog\Listeners\LogoutListener::class,
        'other-device-logout' => \Rappasoft\LaravelAuthenticationLog\Listeners\OtherDeviceLogoutListener::class,
    ],

    'notifications' => [
        'new-device' => [
            'enabled' => false,
            'template' => null,
            'location' => false,
        ],
    ],
];
