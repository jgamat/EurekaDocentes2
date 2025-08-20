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
                        return Locales::where('profec_iCodigo', $fechaId)
                            ->get()
                            ->pluck('localesMaestro.locma_vcNombre', 'loc_iCodigo');
                    })
                    ->searchable()
                    ->required()
                    ->reactive(),

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

                // 4. Búsqueda y asignación rápida
                TextInput::make('busqueda_administrativo')
                    ->label('Buscar y Asignar Administrativo')
                    ->id('busqueda_administrativo')
                    ->placeholder('Nombres, DNI o Código')
                    ->datalist(function (callable $get) {
                        $valor = $get('busqueda_administrativo');
                        if (!$valor || strlen($valor) < 2) {
                            return [];
                        }
                        return Administrativo::query()
                            ->where(function ($q) use ($valor) {
                                $q->where('adm_vcNombres', 'like', "%{$valor}%")
                                  ->orWhere('adm_vcDni', 'like', "%{$valor}%")
                                  ->orWhere('adm_vcCodigo', 'like', "%{$valor}%");
                            })
                            ->limit(10)
                            ->get()
                            ->mapWithKeys(fn ($adm) => [
                                "{$adm->adm_vcNombres} - {$adm->adm_vcDni} - {$adm->adm_vcCodigo}" =>
                                "{$adm->adm_vcNombres} - {$adm->adm_vcDni} - {$adm->adm_vcCodigo}",
                            ])
                            ->toArray();
                    })
                    ->autocomplete('off')
                    ->reactive(),

                Hidden::make('administrativo_dni')
                    ->id('administrativo_dni')
                    ->afterStateUpdated(function ($state) {
                        // Cuando el DNI queda definido (por selección del datalist), intenta asignar
                        if (filled($state)) {
                            $this->asignarAdministrativoDirecto();
                        }
                    }),
            ])
            ->statePath('data');
    }

    public function asignarAdministrativoDirecto(): void
    {
        $data = $this->form->getState();

        // Determinar DNI desde hidden o parsear el texto del datalist
        $dni = $data['administrativo_dni'] ?? null;
        if (!$dni && !empty($data['busqueda_administrativo'])) {
            $parts = array_map('trim', explode('-', $data['busqueda_administrativo']));
            if (count($parts) >= 2) {
                $dniCandidate = $parts[1];
                if (preg_match('/^\d{8}$/', $dniCandidate)) {
                    $dni = $dniCandidate;
                }
            }
        }

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
            'busqueda_administrativo' => null,
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
