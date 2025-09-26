<?php

namespace App\Filament\Pages;

use App\Models\Docente;
use App\Models\LocalCargo;
use App\Models\Locales;
use App\Models\ExperienciaAdmision;
use App\Models\ExperienciaAdmisionMaestro;
use App\Models\ProcesoDocente;
use App\Models\ProcesoFecha;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use Livewire\Attributes\On;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class AsignarDocentes extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;   
    protected static ?string $navigationIcon = 'heroicon-o-user-plus';
    protected static string $view = 'filament.pages.asignar-docentes';
    protected static ?string $navigationLabel = 'Asignar Docentes';
    protected static ?string $navigationGroup = 'Docentes';
    public ?LocalCargo $plazaSeleccionada = null;

    public ?array $data = [];
    #[On('contextoActualizado')]
    public function actualizarPlazaSeleccionada($procesoFechaId, $localId, $experienciaAdmisionId)
    {
        $this->plazaSeleccionada = \App\Models\LocalCargo::where('loc_iCodigo', $localId)
            ->where('expadm_iCodigo', $experienciaAdmisionId)
            ->first();
    }
    public function mount(): void { $this->form->fill(); }
    

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // SELECT 1: FECHA DEL PROCESO
                Select::make('proceso_fecha_id')
                    ->label('1. Seleccione la Fecha del Proceso')
                    ->options(ProcesoFecha::where('profec_iActivo', true)->pluck('profec_dFecha', 'profec_iCodigo'))
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                        // Al cambiar la fecha, reiniciar Local y Cargo y limpiar la plaza
                        $set('local_id', null);
                        $set('experiencia_admision_id', null);
                        $this->plazaSeleccionada = null;
                        // Notificar a la tabla para que se vacíe
                        $this->dispatch('contextoActualizado',
                            procesoFechaId: $state,
                            localId: null,
                            experienciaAdmisionId: null
                        );
                    }),

                Select::make('local_id')
                    ->label('2. Seleccione el Local')
                    ->options(function (callable $get): \Illuminate\Support\Collection {
                        $fechaId = $get('proceso_fecha_id');
                        if (!$fechaId) {
                            return collect();
                        }

                        $user = auth()->user();
                        $docentesLocalesCargos = [2, 3, 4];

                        $query = LocalCargo::query()
                            ->select('localcargo.loc_iCodigo', 'lm.locma_vcNombre')
                            ->join('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'localcargo.expadm_iCodigo')
                            ->join('locales as l', 'l.loc_iCodigo', '=', 'localcargo.loc_iCodigo')
                            ->join('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
                            ->where('ea.profec_iCodigo', $fechaId);

                        // Filtro adicional: si el usuario tiene rol DocentesLocales, solo locales con cargos 2,3,4
                        if ($user && $user->hasRole('DocentesLocales')) {
                            $query->whereIn('ea.expadmma_iCodigo', $docentesLocalesCargos);
                        }

                        $rows = $query
                            ->groupBy('localcargo.loc_iCodigo', 'lm.locma_vcNombre')
                            ->orderBy('lm.locma_vcNombre')
                            ->get();

                        return $rows->pluck('locma_vcNombre', 'loc_iCodigo');
                    })
                    ->preload()
                    ->optionsLimit(1000)
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                        $set('experiencia_admision_id', null);
                        $this->plazaSeleccionada = null;
                        $this->dispatch(
                            'contextoActualizado',
                            procesoFechaId: $get('proceso_fecha_id'),
                            localId: $state,
                            experienciaAdmisionId: null
                        );
                    }),

                Select::make('experiencia_admision_id')
                    ->label('3. Seleccione el Cargo')
                    ->options(function (callable $get): Collection {
                        $fechaId = $get('proceso_fecha_id');
                        $localId = $get('local_id');
                        if (!$fechaId || !$localId) {
                            return collect();
                        }

                        // Roles permitidos para filtrar por tipo de cargo (maestro)
                        $user = auth()->user();
                        $allowed = ExperienciaAdmisionMaestro::query();
                        $docentesLocalesCods = [2, 3, 4];
                        if ($user) {
                            if ($user->hasAnyRole(['DocentesLocales', 'Oprad'])) {
                                $allowed->whereIn('expadmma_iCodigo', $docentesLocalesCods);
                            } elseif ($user->hasAnyRole(['Info', 'Economia', 'super_admin'])) {
                                // sin filtro
                            } else {
                                $allowed->whereNotIn('expadmma_iCodigo', $docentesLocalesCods);
                            }
                        }
                        $allowedIds = $allowed->pluck('expadmma_iCodigo');

                        // Solo cargos ya vinculados al local seleccionado y a la fecha seleccionada (vía localcargo)
                        $cargos = \App\Models\ExperienciaAdmision::query()
                            ->select('experienciaadmision.expadm_iCodigo', 'em.expadmma_vcNombre')
                            ->join('localcargo', 'localcargo.expadm_iCodigo', '=', 'experienciaadmision.expadm_iCodigo')
                            ->join('experienciaadmisionMaestro as em', 'em.expadmma_iCodigo', '=', 'experienciaadmision.expadmma_iCodigo')
                            ->where('localcargo.loc_iCodigo', $localId)
                            ->where('experienciaadmision.profec_iCodigo', $fechaId)
                            ->whereIn('experienciaadmision.expadmma_iCodigo', $allowedIds)
                            ->orderBy('em.expadmma_vcNombre')
                            ->get();

                        return $cargos->pluck('expadmma_vcNombre', 'expadm_iCodigo');
                    })
                    ->searchable()
                    ->preload() // Mostrar todas las opciones sin necesidad de escribir
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $get) {
                        $localId = $get('local_id');
                        if (!$localId || !$state) {
                            $this->plazaSeleccionada = null;
                            return;
                        }
                        $this->plazaSeleccionada = LocalCargo::where('loc_iCodigo', $localId)
                            ->where('expadm_iCodigo', $state)
                            ->first();
                        $this->dispatch(
                            'contextoActualizado',
                            procesoFechaId: $get('proceso_fecha_id'),
                            localId: $localId,
                            experienciaAdmisionId: $state
                        );
                    }),

                    Select::make('docente_id')
                        ->label('Buscar y Asignar Docente')
                        ->searchable()
                        ->placeholder('Escribe nombre, DNI o código')
                        ->getSearchResultsUsing(function (string $search): array {
                            if (strlen($search) < 2) return [];
                            return Docente::query()
                                ->where('doc_iActivo', 1)
                                ->where(function ($q) use ($search) {
                                    $q->where('doc_vcNombre', 'like', "%{$search}%")
                                      ->orWhere('doc_vcPaterno', 'like', "%{$search}%")
                                      ->orWhere('doc_vcMaterno', 'like', "%{$search}%")
                                      ->orWhere('doc_vcDni', 'like', "%{$search}%")
                                      ->orWhere('doc_vcCodigo', 'like', "%{$search}%");
                                })
                                ->orderBy('doc_vcPaterno')
                                ->limit(25)
                                ->get()
                                ->mapWithKeys(fn (Docente $d) => [
                                    $d->doc_vcCodigo => $d->nombre_completo.' - '.$d->doc_vcDni.' - '.$d->doc_vcCodigo,
                                ])
                                ->toArray();
                        })
                        ->getOptionLabelUsing(function ($value): ?string {
                            if (!$value) return null;
                            $d = Docente::where('doc_vcCodigo', $value)->first();
                            return $d ? ($d->nombre_completo.' - '.$d->doc_vcDni.' - '.$d->doc_vcCodigo) : $value;
                        })
                        ->reactive()
                        ->afterStateUpdated(function ($state) {
                            if ($state) { $this->asignarDocenteDirecto(); }
                        }),
            ])
            ->statePath('data');
    }

 
    public function asignarDocenteDirecto(): void
{
    $data = $this->form->getState();
    $codigo = $data['docente_id'] ?? null;
    $docente = $codigo ? Docente::where('doc_vcCodigo', $codigo)->first() : null;
    if (!$docente) {
        Notification::make()
            ->title('Error')
            ->body('No se encontró el docente seleccionado.')
            ->danger()
            ->send();
        return;
    }

    $currentState = $this->form->getState();
    $newState = array_merge($currentState, [
        'docente_id' => $docente->doc_vcCodigo,
    ]);

    // Validaciones
    if (
        empty($newState['proceso_fecha_id']) ||
        empty($newState['local_id']) ||
        empty($newState['experiencia_admision_id']) ||
        empty($docente->doc_vcCodigo)
    ) {
        Notification::make()
            ->title('Error de Formulario')
            ->body('Debes seleccionar Fecha, Local y Cargo antes de asignar un docente.')
            ->danger()
            ->send();
        return;
    }

    // Buscar plaza
    $plaza = LocalCargo::where('loc_iCodigo', $newState['local_id'])
        ->where('expadm_iCodigo', $newState['experiencia_admision_id'])
        ->first();

    if (!$plaza) {
        Notification::make()
            ->title('Error')
            ->body('No se encontró la plaza seleccionada.')
            ->danger()
            ->send();
        return;
    }

    // Validación: el docente no debe tener ya una asignación activa (prodoc_iAsignacion=1) en esta fecha (sin importar local/cargo)
    $asignacionActivaMismaFecha = ProcesoDocente::where('profec_iCodigo', $newState['proceso_fecha_id'])
        ->where('doc_vcCodigo', $docente->doc_vcCodigo)
        ->where('prodoc_iAsignacion', 1)
        ->first();

    if ($asignacionActivaMismaFecha) {
        $locNombre = optional($asignacionActivaMismaFecha->local?->localesMaestro)->locma_vcNombre ?? 'Local desconocido';
        $cargoNombre = optional($asignacionActivaMismaFecha->experienciaAdmision?->maestro)->expadmma_vcNombre ?? 'Cargo desconocido';
        Notification::make()
            ->title('Asignación Bloqueada')
            ->body("El docente {$docente->nombre_completo} ya está asignado en la fecha seleccionada ({$locNombre} - {$cargoNombre}).")
            ->danger()
            ->send();
        return;
    }

    // Validación de vacantes
    if ($plaza->loccar_iOcupado >= $plaza->loccar_iVacante) {
        Notification::make()
            ->title('Asignación Fallida')
            ->body("No quedan vacantes para esta plaza.")
            ->danger()
            ->send();
        return;
    }

    // Buscar si existe un registro previo (inactivo) de este docente en la misma fecha para reactivarlo
    $asignacionPendiente = ProcesoDocente::where('profec_iCodigo', $newState['proceso_fecha_id'])
        ->where('doc_vcCodigo', $docente->doc_vcCodigo)
        ->where('prodoc_iAsignacion', 0)
        ->latest('prodoc_id')
        ->first();

    DB::transaction(function () use ($plaza, $docente, $newState, $asignacionPendiente) {
    $ip = request()->header('X-Forwarded-For') ? explode(',', request()->header('X-Forwarded-For'))[0] : request()->ip();
        if ($asignacionPendiente) {
            // Actualizar el registro existente
            $asignacionPendiente->update([
                'loc_iCodigo' => $plaza->loc_iCodigo,
                'expadm_iCodigo' => $plaza->expadm_iCodigo,
                'prodoc_iAsignacion' => 1,
                'prodoc_dtFechaAsignacion' => now(),
                'user_id' => auth()->id(),
        'prodoc_vcIpAsignacion' => $ip,
            ]);
        } else {
            // Inserción normal
            ProcesoDocente::create([
                'profec_iCodigo' => $newState['proceso_fecha_id'],
                'loc_iCodigo' => $plaza->loc_iCodigo,
                'expadm_iCodigo' => $plaza->expadm_iCodigo,
                'doc_vcCodigo' => $docente->doc_vcCodigo,
                'prodoc_iAsignacion' => 1,
                'prodoc_dtFechaAsignacion' => now(),
                'user_id' => auth()->id(),
        'prodoc_vcIpAsignacion' => $ip,
            ]);
        }
        $plaza->increment('loccar_iOcupado');
    });

    Notification::make()
        ->title('¡Docente Asignado Correctamente!')
        ->success()
        ->send();

    // Refresca la plaza seleccionada y la tabla de asignados
    $this->plazaSeleccionada = $plaza->refresh();
    $this->dispatch(
        'contextoActualizado',
        procesoFechaId: $newState['proceso_fecha_id'],
        localId: $plaza->loc_iCodigo,
        experienciaAdmisionId: $plaza->expadm_iCodigo
    );

    $this->form->fill([
        'proceso_fecha_id' => $newState['proceso_fecha_id'],
        'local_id' => $plaza->loc_iCodigo,
        'experiencia_admision_id' => $plaza->expadm_iCodigo,
        'docente_id' => null,
    ]);
}
}
