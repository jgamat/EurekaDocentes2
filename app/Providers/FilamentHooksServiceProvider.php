<?php

namespace App\Providers;

use Filament\Facades\Filament;
use Filament\Events\ServingFilament;
use Illuminate\Support\ServiceProvider;

class FilamentHooksServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register global render hooks
        \Illuminate\Support\Facades\Event::listen(ServingFilament::class, function () {
            Filament::registerRenderHook('panels::topbar.start', function () {
                return view('filament.partials.context-switcher')->render();
            });
        });
    }
}
