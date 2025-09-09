<?php

return [

    'resources' => [
        'AutenticationLogResource' => \App\Filament\Resources\AuthenticationLogResource::class,
    ],

    'authenticable-resources' => [
        \App\Models\User::class,
    ],

    'authenticatable' => [
    'field-to-display' => 'name',
    ],

    // Which roles can see and access the authentication logs
    'allowed_roles' => [ 'super_admin' ],

    'navigation' => [
        'authentication-log' => [
            'register' => true,
            'sort' => 1,
            'icon' => 'heroicon-o-shield-check',
        ],
    ],

    'sort' => [
        'column' => 'login_at',
        'direction' => 'desc',
    ],
];
