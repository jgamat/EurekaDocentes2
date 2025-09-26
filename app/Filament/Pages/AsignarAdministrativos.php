<?php

namespace App\Filament\Pages;

use App\Models\Administrativo;
use App\Models\LocalCargo;
use App\Models\Locales;
use App\Models\ProcesoAdministrativo;
use App\Models\ProcesoFecha;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class AsignarAdministrativos extends Page implements HasForms
{
    use InteractsWithForms;
     use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';
    protected static string $view = 'filament.pages.asignar-administrativos';
    protected static ?string $navigationLabel = 'Asignar Administrativos';
        protected static ?string $navigationGroup = 'Administrativos';

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
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // 1. Fecha del Proceso
                Select::make('proceso_fecha_id')
                    ->label('1. Seleccione la Fecha del Proceso')
                    ->options(ProcesoFecha::where('profec_iActivo', true)->pluck('profec_dFecha', 'profec_iCodigo'))
                    ->searchable()
                    ->required()
                    ->reactive(),

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
                        // Al cambiar local reiniciar cargo y plaza
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

                        // notificar al componente Livewire de tabla
                        $this->dispatch(
                            'contextoActualizado',
                            procesoFechaId: $get('proceso_fecha_id'),
                            localId: $localId,
                            experienciaAdmisionId: $state,
                        );
                    }),

                // 4. Búsqueda y asignación rápida (Select dinámico)
                Select::make('administrativo_dni')
                    ->label('Buscar y Asignar Administrativo')
                    ->searchable()
                    ->placeholder('Escribe nombre, DNI o código')
                    ->getSearchResultsUsing(function (string $search): array {
                        if (strlen($search) < 2) return [];
                        return Administrativo::query()
                            ->where(function ($q) use ($search) {
                                $q->where('adm_vcNombres', 'like', "%{$search}%")
                                  ->orWhere('adm_vcDni', 'like', "%{$search}%")
                                  ->orWhere('adm_vcCodigo', 'like', "%{$search}%");
                            })
                            ->orderBy('adm_vcNombres')
                            ->limit(25)
                            ->get()
                            ->mapWithKeys(fn (Administrativo $a) => [
                                $a->adm_vcDni => $a->adm_vcNombres.' - '.$a->adm_vcDni.' - '.$a->adm_vcCodigo,
                            ])
                            ->toArray();
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        if (!$value) return null;
                        $a = Administrativo::where('adm_vcDni', $value)->first();
                        return $a ? ($a->adm_vcNombres.' - '.$a->adm_vcDni.' - '.$a->adm_vcCodigo) : $value;
                    })
                    ->reactive()
                    ->afterStateUpdated(function ($state) {
                        if ($state) { $this->asignarAdministrativoDirecto(); }
                    }),
            ])
            ->statePath('data');
    }

    public function asignarAdministrativoDirecto(): void
    {
        $data = $this->form->getState();

        // El DNI llega directamente desde el Select searchable
        $dni = $data['administrativo_dni'] ?? null;

        $administrativo = $dni ? Administrativo::where('adm_vcDni', $dni)->first() : null;
        if (!$administrativo) {
            Notification::make()
                ->title('Error')
                ->body('No se encontró el administrativo seleccionado.')
                ->danger()
                ->send();
            return;
        }

        // Validaciones de contexto
        if (
            empty($data['proceso_fecha_id']) ||
            empty($data['local_id']) ||
            empty($data['experiencia_admision_id']) ||
            empty($dni)
        ) {
            Notification::make()
                ->title('Error de Formulario')
                ->body('Debes seleccionar Fecha, Local y Cargo antes de asignar un administrativo.')
                ->danger()
                ->send();
            return;
        }

        // Buscar plaza
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

        // Validación de asignación única activa
        $asignacionExistente = ProcesoAdministrativo::where('profec_iCodigo', $data['proceso_fecha_id'])
            ->where('loc_iCodigo', $data['local_id'])
            ->where('expadm_iCodigo', $data['experiencia_admision_id'])
            ->where('adm_vcDni', $dni)
            ->where('proadm_iAsignacion', 1)
            ->first();

        // Nueva validación global: el administrativo no debe tener ya una asignación activa en la misma fecha (sin importar local/cargo)
        $asignacionActivaMismaFecha = ProcesoAdministrativo::where('profec_iCodigo', $data['proceso_fecha_id'])
            ->where('adm_vcDni', $dni)
            ->where('proadm_iAsignacion', 1)
            ->first();

        if ($asignacionActivaMismaFecha) {
            $locNombreExist = optional($asignacionActivaMismaFecha->local?->localesMaestro)->locma_vcNombre ?? 'Local desconocido';
            $cargoNombreExist = optional($asignacionActivaMismaFecha->experienciaAdmision?->maestro)->expadmma_vcNombre ?? 'Cargo desconocido';
            Notification::make()
                ->title('Asignación Bloqueada')
                ->body("El administrativo {$administrativo->adm_vcNombres} ya está asignado en la fecha seleccionada ({$locNombreExist} - {$cargoNombreExist}).")
                ->danger()
                ->send();
            return;
        }

        if ($asignacionExistente) {
            $localNombre = $plaza->localesMaestro->locma_vcNombre ?? 'Local desconocido';
            $cargoNombre = $plaza->maestro->expadmma_vcNombre ?? 'Cargo desconocido';

            Notification::make()
                ->title('Asignación Bloqueada')
                ->body("El administrativo {$administrativo->adm_vcNombres} ya está asignado en esta fecha en el {$localNombre} - {$cargoNombre}.")
                ->danger()
                ->send();
            return;
        }

        // Validación de vacantes
        if (($plaza->loccar_iOcupado ?? 0) >= ($plaza->loccar_iVacante ?? 0)) {
            Notification::make()
                ->title('Asignación Fallida')
                ->body('No quedan vacantes para esta plaza.')
                ->danger()
                ->send();
            return;
        }

        // Reusar registro pendiente si existe
        $asignacionPendiente = ProcesoAdministrativo::where('profec_iCodigo', $data['proceso_fecha_id'])
            ->where('adm_vcDni', $dni)
            ->where('proadm_iAsignacion', 0)
            ->first();

    DB::transaction(function () use ($plaza, $administrativo, $data, $asignacionPendiente, $dni) {
        $ip = request()->header('X-Forwarded-For') ? explode(',', request()->header('X-Forwarded-For'))[0] : request()->ip();
            if ($asignacionPendiente) {
                $asignacionPendiente->update([
                    'loc_iCodigo' => $plaza->loc_iCodigo,
                    'expadm_iCodigo' => $plaza->expadm_iCodigo,
                    'proadm_iAsignacion' => 1,
                    'proadm_dtFechaAsignacion' => now(),
                    'user_id' => auth()->id(),
                    'adm_vcDni' => $dni,
            'proadm_vcIpAsignacion' => $ip,
                ]);
            } else {
                ProcesoAdministrativo::create([
                    'profec_iCodigo' => $data['proceso_fecha_id'],
                    'loc_iCodigo' => $plaza->loc_iCodigo,
                    'expadm_iCodigo' => $plaza->expadm_iCodigo,
                    'adm_vcDni' => $dni,
                    'proadm_iAsignacion' => 1,
                    'proadm_dtFechaAsignacion' => now(),
                    'user_id' => auth()->id(),
            'proadm_vcIpAsignacion' => $ip,
                ]);
            }
            $plaza->increment('loccar_iOcupado');
        });

        Notification::make()
            ->title('¡Administrativo Asignado Correctamente!')
            ->success()
            ->send();

        // Refrescar estado y mantener selección
        $this->plazaSeleccionada = $plaza->refresh();

        $this->form->fill([
            'proceso_fecha_id' => $data['proceso_fecha_id'],
            'local_id' => $plaza->loc_iCodigo,
            'experiencia_admision_id' => $plaza->expadm_iCodigo,
            'administrativo_dni' => null,
        ]);

        // notificar al componente Livewire de tabla para refrescar
        $this->dispatch(
            'contextoActualizado',
            procesoFechaId: $data['proceso_fecha_id'],
            localId: $plaza->loc_iCodigo,
            experienciaAdmisionId: $plaza->expadm_iCodigo,
        );
    }
}
