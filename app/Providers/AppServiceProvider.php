<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Support\CurrentContext;
use BezhanSalleh\FilamentShield\FilamentShield;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    protected array $policies = [
        Activity::class => ActivityPolicy::class,
    ];

    public function register(): void
    {
        $this->app->singleton(CurrentContext::class, function () {
            return new CurrentContext;
        });
    }

    public function boot(): void
    {
        $this->configurePolicies();

        $this->configureDB();

        $this->configureModels();

        $this->configureFilament();

        // Share current context with all views (only in HTTP requests to avoid CLI/DB boot errors)
        if (! $this->app->runningInConsole()) {
            try {
                $ctx = $this->app->make(CurrentContext::class);
                $ctx->ensureLoaded();
                View::share('currentContext', $ctx);
            } catch (\Throwable $e) {
                // Ignore DB connection errors during early boot
            }
        }
    }

    private function configurePolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Global authorization bypass for super admins across resources and pages.
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->hasAnyRole(['super_admin', 'suer_admin'])) {
                return true;
            }

            return null;
        });
    }

    private function configureDB(): void
    {
        DB::prohibitDestructiveCommands($this->app->environment('production'));
    }

    private function configureModels(): void
    {
        Model::preventAccessingMissingAttributes();

        Model::unguard();
    }

    private function configureFilament(): void
    {
        // FilamentShield::prohibitDestructiveCommands($this->app->environment('production'));

        Table::configureUsing(fn (Table $table) => $table->paginationPageOptions([10, 25, 50]));
    }
}
