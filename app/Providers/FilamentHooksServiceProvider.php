<?php

namespace App\Providers;

use Filament\Facades\Filament;
use Filament\Events\ServingFilament;
use Illuminate\Support\ServiceProvider;

class FilamentHooksServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
    // Password reset link hook removed
    }
}
