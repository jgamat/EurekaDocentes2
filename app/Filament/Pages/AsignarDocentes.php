<?php

namespace App\Filament\Pages;

use App\Models\Docente;
use App\Models\LocalCargo;
use App\Models\Locales;
use App\Models\ExperienciaAdminision;
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
                    ->reactive(),

                // SELECT 2: LOCAL (depende de la fecha)
                Select::make('local_id')
                    ->label('2. Seleccione el Local')
                    ->options(function (callable $get): Collection {
                        $fechaId = $get('proceso_fecha_id');
                        if (!$fechaId) {
                            return collect();
                        }
                        return Locales::where('profec_iCodigo', $fechaId)
                            ->get()
                            ->pluck('localesMaestro.locma_vcNombre', 'loc_iCodigo');
                    })
                    ->searchable()
                    ->required()
                    ->reactive(),

                Select::make('experiencia_admision_id')
                    ->label('3. Seleccione el Cargo')
                    ->options(function (callable $get): Collection {
                        $localId = $get('local_id');
                        if (!$localId) {
                            return collect();
                        }
                        $local = Locales::find($localId);
                        if (!$local) {
                            return collect();
                        }
                        $user = auth()->user();
                            $fechaId = $get('proceso_fecha_id');
                            // Aseguramos que existan instancias ExperienciaAdmision para TODOS los maestros permitidos.
                            $allowedMaestroQuery = ExperienciaAdmisionMaestro::query();
                            $codigos = [2,3,4];
                            if($user){
                                if ($user->hasRole('DocentesLocales')) {
                                    $allowedMaestroQuery->whereIn('expadmma_iCodigo',$codigos);
                                } elseif ($user->hasAnyRole(['Info','Economia','Economía'])) {
                                    // todos (sin filtro)
                                } else {
                                    $allowedMaestroQuery->whereNotIn('expadmma_iCodigo',$codigos);
                                }
                            }
                            $maestros = $allowedMaestroQuery->get();

                            if($fechaId){
                                foreach($maestros as $m){
                                    // Crear instancia por fecha si no existe
                                    $inst = ExperienciaAdmision::firstOrCreate([
                                        'profec_iCodigo' => $fechaId,
                                        'expadmma_iCodigo' => $m->expadmma_iCodigo,
                                    ]);
                                    // Asegurar vínculo con local
                                    if($local){
                                        $local->experienciaAdmision()->syncWithoutDetaching([
                                            $inst->expadm_iCodigo => [ 'loccar_iVacante' => $local->experienciaAdmision()->where('experienciaadmision.expadm_iCodigo',$inst->expadm_iCodigo)->first()->pivot->loccar_iVacante ?? 0 ]
                                        ]);
                                    }
                                }
                            }

                            // Ahora construir el query sobre instancias ligadas al local filtradas por maestros permitidos
                            $query = $local->experienciaAdmision()->whereHas('maestro', function($q) use ($maestros){
                                $q->whereIn('expadmma_iCodigo', $maestros->pluck('expadmma_iCodigo'));
                            });
                        return $query->with('maestro')->get()->pluck('maestro.expadmma_vcNombre','expadm_iCodigo');
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

                    TextInput::make('busqueda_docente')
    ->label('Buscar y Asignar Docente')
    ->id('busqueda_docente')
    ->datalist(function (callable $get) {
        $valor = $get('busqueda_docente');
        if (!$valor || strlen($valor) < 2) {
            return [];
        }
        return \App\Models\Docente::where('doc_iActivo', 1)
            ->where(function ($query) use ($valor) {
                $query->where('doc_vcNombre', 'like', "%{$valor}%")
                    ->orWhere('doc_vcPaterno', 'like', "%{$valor}%")
                    ->orWhere('doc_vcMaterno', 'like', "%{$valor}%")
                    ->orWhere('doc_vcDni', 'like', "%{$valor}%")
                    ->orWhere('doc_vcCodigo', 'like', "%{$valor}%");
            })
            ->limit(10)
            ->get()
            ->mapWithKeys(fn($docente) => [
                "{$docente->nombre_completo} - {$docente->doc_vcDni} - {$docente->doc_vcCodigo}" =>
                "{$docente->nombre_completo} - {$docente->doc_vcDni} - {$docente->doc_vcCodigo}"
            ])
            ->toArray();
    })
    ->autocomplete('off')
    ->reactive()
    ->afterStateUpdated(fn ($state, callable $get, $set) => $this->asignarDocenteDirecto()),

                
                Hidden::make('docente_id')
                        ->id('docente_id')
                        ->required(),
            ])
            ->statePath('data');
    }

 
    public function asignarDocenteDirecto(): void
{
    $data = $this->form->getState();
    $docente = Docente::where('doc_vcCodigo', $data['docente_id'])->first();
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
        'busqueda_docente' => $docente->nombre_completo,
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

    // Validación de asignación única por fecha y cargo con prodoc_iAsignacion = 1
    $asignacionExistente = ProcesoDocente::where('profec_iCodigo', $newState['proceso_fecha_id'])
        ->where('loc_iCodigo', $newState['local_id'])
        ->where('expadm_iCodigo', $newState['experiencia_admision_id'])
        ->where('doc_vcCodigo', $docente->doc_vcCodigo)
        ->where('prodoc_iAsignacion', 1)
        ->first();

    if ($asignacionExistente) {
        $localNombre = $plaza->localesMaestro->locma_vcNombre ?? 'Local desconocido';
        $cargoNombre = $plaza->maestro->expadmma_vcNombre ?? 'Cargo desconocido';

        Notification::make()
            ->title('Asignación Bloqueada')
            ->body("El docente {$docente->nombre_completo} ya está asignado en esta fecha en el {$localNombre} - {$cargoNombre}.")
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

    // Buscar si existe un registro con prodoc_iAsignacion = 0 para este docente y fecha
    $asignacionPendiente = ProcesoDocente::where('profec_iCodigo', $newState['proceso_fecha_id'])
        ->where('doc_vcCodigo', $docente->doc_vcCodigo)
        ->where('prodoc_iAsignacion', 0)
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
        'busqueda_docente' => null,
    ]);
}
}
