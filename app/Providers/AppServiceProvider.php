<?php

namespace App\Providers;

use App\Models\AuthenticationLog;
use App\Models\ProcesoAdministrativo;
use App\Models\ProcesoAlumno;
use App\Models\ProcesoDocente;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Support\CurrentContext;
use BezhanSalleh\FilamentShield\FilamentShield;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;
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

        $this->configureAssignmentMonitors();

        $this->configureFilament();

        $this->configureAuthenticationLogListeners();

        LogViewer::auth(function ($request) {
            return $request->user()
                && $request->user()->hasAnyRole(['super_admin', 'suer_admin']);
        });

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

    private function configureAssignmentMonitors(): void
    {
        ProcesoDocente::saved(function (ProcesoDocente $model) {
            // Ignorar cambios que no sean de asignacion
            if (! $model->wasRecentlyCreated && ! $model->isDirty('prodoc_iAsignacion')) {
                return;
            }

            $user = auth()->user();
            if (! $user) {
                return;
            }

            $event = $model->prodoc_iAsignacion ? 'asignardocente' : 'desasignardocente';

            activity('Modelos')
                ->causedBy($user)
                ->performedOn($model)
                ->event($event)
                ->withChanges([
                    'attributes' => [
                        'DNI' => $model->docente?->doc_vcDni,
                        'Código' => $model->doc_vcCodigo,
                        'Asignado a Local ID' => $model->loc_iCodigo,
                        'Cargo ID' => $model->expadm_iCodigo,
                        'Estado' => $model->prodoc_iAsignacion ? 'Asignado' : 'Desasignado',
                    ],
                    'old' => [],
                ])
                ->log(($model->prodoc_iAsignacion ? 'Asignó' : 'Desasignó').' al Docente: '.($model->docente?->nombre_completo ?? $model->doc_vcCodigo));
        });

        ProcesoAdministrativo::saved(function (ProcesoAdministrativo $model) {
            if (! $model->wasRecentlyCreated && ! $model->isDirty('proadm_iAsignacion')) {
                return;
            }

            $user = auth()->user();
            if (! $user) {
                return;
            }

            $event = $model->proadm_iAsignacion ? 'asignaradministrativo' : 'desasignaradministrativo';

            activity('Modelos')
                ->causedBy($user)
                ->performedOn($model)
                ->event($event)
                ->withChanges([
                    'attributes' => [
                        'DNI' => $model->adm_vcDni,
                        'Asignado a Local ID' => $model->loc_iCodigo,
                        'Cargo ID' => $model->expadm_iCodigo,
                        'Estado' => $model->proadm_iAsignacion ? 'Asignado' : 'Desasignado',
                    ],
                    'old' => [],
                ])
                ->log(($model->proadm_iAsignacion ? 'Asignó' : 'Desasignó').' al Administrativo: '.($model->administrativo?->nombre_completo ?? $model->adm_vcDni));
        });

        ProcesoAlumno::saved(function (ProcesoAlumno $model) {
            if (! $model->wasRecentlyCreated && ! $model->isDirty('proalu_iAsignacion')) {
                return;
            }

            $user = auth()->user();
            if (! $user) {
                return;
            }

            $event = $model->proalu_iAsignacion ? 'asignaralumno' : 'desasignaralumno';

            activity('Modelos')
                ->causedBy($user)
                ->performedOn($model)
                ->event($event)
                ->withChanges([
                    'attributes' => [
                        'Código' => $model->alu_vcCodigo,
                        'Asignado a Local ID' => $model->loc_iCodigo,
                        'Cargo ID' => $model->expadm_iCodigo,
                        'Estado' => $model->proalu_iAsignacion ? 'Asignado' : 'Desasignado',
                    ],
                    'old' => [],
                ])
                ->log(($model->proalu_iAsignacion ? 'Asignó' : 'Desasignó').' al Alumno: '.($model->alumno?->nombre_completo ?? $model->alu_vcCodigo));
        });
    }

    private function configureFilament(): void
    {
        // FilamentShield::prohibitDestructiveCommands($this->app->environment('production'));

        Table::configureUsing(fn (Table $table) => $table->paginationPageOptions([10, 25, 50]));
    }

    private function configureAuthenticationLogListeners(): void
    {
        // Registrar logs de inicio de sesión exitoso
        Event::listen(Login::class, function (Login $event): void {
            $user = $event->user;
            if (! $user) {
                return;
            }

            activity('Access')
                ->causedBy($user)
                ->event('Login')
                ->withProperties([
                    'ip' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                ])
                ->log($user->name.' logged in');
        });

        // Registrar logs de sesión fallidos
        Event::listen(Failed::class, function (Failed $event): void {
            activity('Access')
                ->causedBy($event->user)
                ->event('Failed')
                ->withProperties([
                    'ip' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                    'email' => $event->credentials['email'] ?? 'N/A',
                ])
                ->log(($event->user ? $event->user->name : 'Desconocido').' erro al iniciar sesión');
        });

        Event::listen(Logout::class, function (Logout $event): void {
            $user = $event->user;

            if (! $user) {
                return;
            }

            activity('Access')
                ->causedBy($user)
                ->event('Logout')
                ->log($user->name.' logged out');

            AuthenticationLog::query()
                ->where('authenticatable_type', $user::class)
                ->where('authenticatable_id', (int) $user->getAuthIdentifier())
                ->whereNull('logout_at')
                ->where('login_successful', true)
                ->latest('login_at')
                ->limit(1)
                ->update([
                    'logout_at' => now(),
                ]);
        });
    }
}
