<?php

namespace App\Filament\Pages;

use App\Models\Alumno;
use App\Models\LocalCargo;
use App\Models\Locales;
use App\Models\ProcesoAlumno;
use App\Models\ProcesoFecha;
use App\Support\CurrentContext;
use App\Support\Traits\UsesGlobalContext;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class AsignarAlumnos extends Page implements HasForms
{
    use InteractsWithForms;
    use UsesGlobalContext;
    use HasPageShield;
    

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';
    protected static string $view = 'filament.pages.asignar-alumnos';
    protected static ?string $navigationLabel = 'Asignar Alumnos';
      protected static ?string $navigationGroup = 'Alumnos';

    public ?LocalCargo $plazaSeleccionada = null;
    public ?array $data = [];

    #[On('contextoActualizado')]
    public function actualizarPlazaSeleccionada($procesoFechaId, $localId, $experienciaAdmisionId): void
    {
        $this->plazaSeleccionada = LocalCargo::where('loc_iCodigo', $localId)
            ->where('expadm_iCodigo', $experienciaAdmisionId)
            ->first();
    }

    public function mount(): void
    {
        // Inicializar usando el contexto global
        $this->fillContextDefaults(['proceso_fecha_id']);
        // Rehidratar inmediatamente el campo oculto desde el contexto global
        $ctx = app(CurrentContext::class);
        $this->form?->fill(['proceso_fecha_id' => $ctx->fechaId()]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // 0. Mostrar la fecha del proceso (global) en solo lectura
                $this->fechaActualPlaceholder('proceso_fecha_id'),
                // 1. Fecha del Proceso
                Select::make('proceso_fecha_id')
                    ->label('Fecha del Proceso (global)')
                    ->options(ProcesoFecha::where('profec_iActivo', true)->pluck('profec_dFecha', 'profec_iCodigo'))
                    ->required()
                    ->reactive()
                    ->hidden()
                    ->dehydrated(true)
                    ->default(fn() => app(CurrentContext::class)->fechaId())
                    ->afterStateUpdated(function($state, callable $set){
                        // Reset dependientes (Local y Cargo) y búsqueda al cambiar la fecha
                        $set('local_id', null);
                        $set('experiencia_admision_id', null);
                        $set('alumno_codigo', null);
                        $this->plazaSeleccionada = null;
                        // Disparar evento para limpiar tablas/componentes asociados
                        $this->dispatch(
                            'contextoActualizado',
                            procesoFechaId: $state,
                            localId: null,
                            experienciaAdmisionId: null
                        );
                        Notification::make()
                            ->title('Se reinició Local y Cargo por cambio de Fecha')
                            ->info()
                            ->send();
                    }),

                // 2. Local dependiente de la fecha
               Select::make('local_id')
                    ->label('2. Seleccione el Local')
                    ->options(function (callable $get): Collection {
                        $fechaId = $get('proceso_fecha_id');
                        if (!$fechaId) {
                            return collect();
                        }

                        $rows = LocalCargo::query()
                            ->select('localcargo.loc_iCodigo', 'lm.locma_vcNombre')
                            ->join('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'localcargo.expadm_iCodigo')
                            ->join('locales as l', 'l.loc_iCodigo', '=', 'localcargo.loc_iCodigo')
                            ->join('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
                            ->where('ea.profec_iCodigo', $fechaId)
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
                        // Al cambiar local, reiniciar cargo y plaza
                        $set('experiencia_admision_id', null);
                        $this->plazaSeleccionada = null;
                        $this->dispatch(
                            'contextoActualizado',
                            procesoFechaId: $get('proceso_fecha_id'),
                            localId: $state,
                            experienciaAdmisionId: null
                        );
                    }),


                // 3. Cargo dependiente del local (excluye códigos 2,3,4 para todos los roles)
                Select::make('experiencia_admision_id')
                    ->label('3. Seleccione el Cargo')
                    ->options(function (callable $get): Collection {
                        $localId = $get('local_id');
                        if (!$localId) {
                            return collect();
                        }
                        $local = Locales::find($localId);
                        if(!$local){
                            return collect();
                        }
                        $query = $local->experienciaAdmision();
                        $excluir = [2,3,4];
                        $query->whereHas('maestro', fn($q)=> $q->whereNotIn('expadmma_iCodigo',$excluir));
                        return $query->with('maestro')->get()->pluck('maestro.expadmma_vcNombre','expadm_iCodigo');
                    })
                    ->searchable()
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
                            experienciaAdmisionId: $state,
                        );
                    }),

                // 4. Búsqueda y asignación rápida (Select asíncrono)
                Select::make('alumno_codigo')
                    ->label('Buscar y Asignar Alumno')
                    ->placeholder('Escribe apellidos, nombres, DNI o código')
                    ->searchable()
                    ->helperText('Primero seleccione Local y Cargo; luego elija al alumno para asignar.')
                    ->disabled(fn (callable $get) => empty($get('local_id')) || empty($get('experiencia_admision_id')))
                    ->getSearchResultsUsing(function (string $search): array {
                        if (strlen($search) < 2) return [];
                        return Alumno::query()
                            ->where(function ($q) use ($search) {
                                $q->where('alu_vcNombre', 'like', "%{$search}%")
                                  ->orWhere('alu_vcPaterno', 'like', "%{$search}%")
                                  ->orWhere('alu_vcMaterno', 'like', "%{$search}%")
                                  ->orWhere('alu_vcDni', 'like', "%{$search}%")
                                  ->orWhere('alu_vcCodigo', 'like', "%{$search}%");
                            })
                            ->orderBy('alu_vcPaterno')
                            ->limit(25)
                            ->get()
                            ->mapWithKeys(fn(Alumno $a) => [
                                $a->alu_vcCodigo => $a->nombre_completo.' - '.$a->alu_vcDni.' - '.$a->alu_vcCodigo,
                            ])
                            ->toArray();
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        if (!$value) return null;
                        $a = Alumno::where('alu_vcCodigo', $value)->first();
                        return $a ? ($a->nombre_completo.' - '.$a->alu_vcDni.' - '.$a->alu_vcCodigo) : $value;
                    })
                    ->reactive()
                    ->afterStateUpdated(function ($state) {
                        if ($state) { $this->asignarAlumnoDirecto(); }
                    }),
            ])
            ->statePath('data');
    }

    #[On('context-changed')]
    public function onContextChanged(): void
    {
        $this->applyContextFromGlobal(['proceso_fecha_id'], ['local_id','experiencia_admision_id','alumno_codigo'], 'Se aplicó la Fecha seleccionada globalmente y se reiniciaron los campos dependientes.');
        $this->plazaSeleccionada = null;
        $this->dispatch(
            'contextoActualizado',
            procesoFechaId: app(CurrentContext::class)->fechaId(),
            localId: null,
            experienciaAdmisionId: null
        );
    }

    public function asignarAlumnoDirecto(): void
    {
        $data = $this->form->getState();
        // Reforzar integridad del contexto: usar CurrentContext si falta la fecha y volcarla al state
        $ctx = app(CurrentContext::class);
        $fechaId = $data['proceso_fecha_id'] ?? $ctx->fechaId();
        if (!isset($data['proceso_fecha_id'])) {
            $this->form->fill(['proceso_fecha_id' => $fechaId]);
            $data['proceso_fecha_id'] = $fechaId;
        }

    $codigo = $data['alumno_codigo'] ?? null; // ahora proviene directamente del Select searchable
        $alumno = $codigo ? Alumno::where('alu_vcCodigo', $codigo)->first() : null;
        if (!$alumno) {
            Notification::make()
                ->title('Error')
                ->body('No se encontró el alumno seleccionado.')
                ->danger()
                ->send();
            return;
        }

        if (
            empty($fechaId) ||
            empty($data['local_id']) ||
            empty($data['experiencia_admision_id']) ||
            empty($codigo)
        ) {
            Notification::make()
                ->title('Error de Formulario')
                ->body('Debes seleccionar Fecha, Local y Cargo antes de asignar un alumno.')
                ->danger()
                ->send();
            return;
        }

        $plaza = LocalCargo::where('loc_iCodigo', $data['local_id'])
            ->where('expadm_iCodigo', $data['experiencia_admision_id'])
            ->first();

        if (!$plaza) {
            Notification::make()
                ->title('Error')
                ->body('No se encontró la plaza seleccionada.')
                ->danger()
                ->send();
            return;
        }

        

        // Nueva validación global: el alumno no debe tener ya una asignación activa en la misma fecha (sin importar local/cargo)
        $asignacionActivaMismaFecha = ProcesoAlumno::where('profec_iCodigo', $fechaId)
            ->where('alu_vcCodigo', $codigo)
            ->where('proalu_iAsignacion', 1)
            ->first();

        if ($asignacionActivaMismaFecha) {
            $locNombreExist = optional($asignacionActivaMismaFecha->local?->localesMaestro)->locma_vcNombre ?? 'Local desconocido';
            $cargoNombreExist = optional($asignacionActivaMismaFecha->experienciaAdmision?->maestro)->expadmma_vcNombre ?? 'Cargo desconocido';
            Notification::make()
                ->title('Asignación Bloqueada')
                ->body("El alumno {$alumno->nombre_completo} ya está asignado en la fecha seleccionada ({$locNombreExist} - {$cargoNombreExist}).")
                ->danger()
                ->send();
            return;
        }

       

        if (($plaza->loccar_iOcupado ?? 0) >= ($plaza->loccar_iVacante ?? 0)) {
            Notification::make()
                ->title('Asignación Fallida')
                ->body('No quedan vacantes para esta plaza.')
                ->danger()
                ->send();
            return;
        }

        $asignacionPendiente = ProcesoAlumno::where('profec_iCodigo', $fechaId)
            ->where('alu_vcCodigo', $codigo)
            ->where('proalu_iAsignacion', 0)
            ->first();

    DB::transaction(function () use ($plaza, $alumno, $data, $asignacionPendiente, $codigo, $fechaId) {
        $ip = request()->header('X-Forwarded-For') ? explode(',', request()->header('X-Forwarded-For'))[0] : request()->ip();
            if ($asignacionPendiente) {
                $asignacionPendiente->update([
                    'loc_iCodigo' => $plaza->loc_iCodigo,
                    'expadm_iCodigo' => $plaza->expadm_iCodigo,
                    'proalu_iAsignacion' => 1,
                    'proalu_dtFechaAsignacion' => now(),
                    'user_id' => auth()->id(),
                    'alu_vcCodigo' => $codigo,
            'proalu_vcIpAsignacion' => $ip,
                ]);
            } else {
                ProcesoAlumno::create([
                    'profec_iCodigo' => $fechaId,
                    'loc_iCodigo' => $plaza->loc_iCodigo,
                    'expadm_iCodigo' => $plaza->expadm_iCodigo,
                    'alu_vcCodigo' => $codigo,
                    'proalu_iAsignacion' => 1,
                    'proalu_dtFechaAsignacion' => now(),
                    'user_id' => auth()->id(),
            'proalu_vcIpAsignacion' => $ip,
                ]);
            }
            $plaza->increment('loccar_iOcupado');
        });

        Notification::make()
            ->title('¡Alumno Asignado Correctamente!')
            ->success()
            ->send();

        // Refrescar estado y mantener selección
        $this->plazaSeleccionada = $plaza->refresh();
        $this->form->fill([
            'proceso_fecha_id' => $fechaId,
            'local_id' => $plaza->loc_iCodigo,
            'experiencia_admision_id' => $plaza->expadm_iCodigo,
            'alumno_codigo' => null,
        ]);

        $this->dispatch(
            'contextoActualizado',
            procesoFechaId: $data['proceso_fecha_id'],
            localId: $plaza->loc_iCodigo,
            experienciaAdmisionId: $plaza->expadm_iCodigo,
        );
    }
}
