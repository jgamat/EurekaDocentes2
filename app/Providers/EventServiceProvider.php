<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;

// Authentication log listeners are auto-registered by the package service provider.

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
    Registered::class => [
        SendEmailVerificationNotification::class,
    ],

    // Los listeners de autenticación son registrados por Rappasoft\LaravelAuthenticationLog\LaravelAuthenticationLogServiceProvider
];
}
