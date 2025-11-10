<?php

namespace App\Filament\Pages;

use App\Models\Proceso;
use App\Models\ProcesoFecha;
use App\Support\CurrentContext;
use App\Support\Traits\UsesGlobalContext;
use Livewire\Attributes\On;
use App\Models\Administrativo;
use App\Models\ProcesoAdministrativo;
use App\Models\LocalCargo;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class DesasignarAdministrativo extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;
    use UsesGlobalContext;

    protected static ?string $navigationIcon = 'heroicon-o-user-minus';
    protected static string $view = 'filament.pages.desasignar-administrativo';
    protected static ?string $navigationLabel = 'Desasignar Administrativo';
    protected static ?string $navigationGroup = 'Administrativos';

    public ?array $data = [];
    // Mantener la asignación encontrada explícitamente para que Blade la muestre sin depender de property virtual
    public ?ProcesoAdministrativo $asignacionActual = null;

    public function mount(): void
    {
        $this->fillContextDefaults(['proceso_id','proceso_fecha_id']);
        // Hidratar explícitamente los campos ocultos para que el placeholder de fecha funcione.
        $ctx = app(CurrentContext::class);
        $this->form?->fill([
            'proceso_id' => $ctx->procesoId(),
            'proceso_fecha_id' => $ctx->fechaId(),
        ]);
    }

    #[On('context-changed')]
    public function onContextChanged(): void
    {
        $this->applyContextFromGlobal(['proceso_id','proceso_fecha_id'], ['administrativo_dni'], 'Se aplicó la Fecha y Proceso globales y se reinició la búsqueda de administrativo.');
    }

    protected function ensureContextIntegrity(): void
    {
        $state = $this->form?->getState() ?? [];
        $ctx = app(CurrentContext::class);
        $payload = [];
        if (empty($state['proceso_id'])) { $payload['proceso_id'] = $ctx->procesoId(); }
        if (empty($state['proceso_fecha_id'])) { $payload['proceso_fecha_id'] = $ctx->fechaId(); }
        if (!empty($payload)) { $this->form?->fill($payload); }
    }


    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // 0. Fecha global solo lectura
                $this->fechaActualPlaceholder('proceso_fecha_id'),
                // 1. Proceso global oculto
                Select::make('proceso_id')
                    ->label('1. Seleccione el Proceso Abierto')
                    ->options(Proceso::where('pro_iAbierto', true)->pluck('pro_vcNombre', 'pro_iCodigo'))
                    ->required()
                    ->reactive()
                    ->hidden()
                    ->dehydrated(true)
                    ->afterStateUpdated(function ($state, callable $set, $livewire) {
                        $set('administrativo_dni', null);
                        $livewire->resetValidation();
                    }),
                // 2. Fecha global oculta
                Select::make('proceso_fecha_id')
                    ->label('2. Seleccione la Fecha Activa')
                    ->options(function (callable $get): Collection {
                        $procesoId = $get('proceso_id');
                        if (!$procesoId) return collect();
                        return ProcesoFecha::where('pro_iCodigo', $procesoId)
                            ->where('profec_iActivo', true)
                            ->pluck('profec_dFecha', 'profec_iCodigo');
                    })
                    ->required(fn (callable $get) => filled($get('proceso_id')))
                    ->reactive()
                    ->hidden()
                    ->dehydrated(true)
                    ->afterStateUpdated(function ($state, callable $set, $livewire) {
                        $set('administrativo_dni', null);
                        $livewire->resetValidation();
                    }),

                Select::make('administrativo_dni')
                    ->label('Buscar Administrativo')
                    ->searchable()
                    ->placeholder('Escribe nombre, DNI o código (min 2 caracteres)')
                    ->helperText('Debe existir una asignación activa en la fecha global para mostrar detalles.')
                    ->getSearchResultsUsing(function (string $search) {
                        if (strlen($search) < 2) return [];
                        return Administrativo::query()
                            ->where(function ($query) use ($search) {
                                $query->where('adm_vcNombres', 'like', "%{$search}%")
                                    ->orWhere('adm_vcDni', 'like', "%{$search}%")
                                    ->orWhere('adm_vcCodigo', 'like', "%{$search}%");
                            })
                            ->limit(10)
                            ->get()
                            ->mapWithKeys(fn($adm) => [
                                $adm->adm_vcDni => "{$adm->adm_vcNombres} - {$adm->adm_vcDni} - {$adm->adm_vcCodigo}",
                            ])
                            ->toArray();
                    })
                    ->required(fn (callable $get) => filled($get('proceso_fecha_id')))
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $get, $livewire) {
                        $livewire->resetValidation();

                        // Si hay fecha seleccionada y se eligió un administrativo, valida si existe asignación
                        $fechaId = $get('proceso_fecha_id');
                        if ($fechaId && $state) {
                            $asignacion = ProcesoAdministrativo::with(['administrativo','local.localesMaestro','experienciaAdmision.maestro'])
                                ->where('adm_vcDni', $state)
                                ->where('profec_iCodigo', $fechaId)
                                ->where('proadm_iAsignacion', 1)
                                ->first();
                            if (!$asignacion) {
                                $this->asignacionActual = null;
                                Notification::make()
                                    ->title('No asignado')
                                    ->body('El administrativo no está asignado en esta fecha.')
                                    ->danger()
                                    ->send();
                            } else {
                                $this->asignacionActual = $asignacion;
                                Notification::make()
                                    ->title('Asignación encontrada')
                                    ->body('Puede proceder a desasignar: '.$asignacion->administrativo->adm_vcNombres)
                                    ->success()
                                    ->send();
                            }
                        }
                    }),
            ])
            ->statePath('data');
    }

    public function desasignarAdministrativo()
    {
        $asignacion = $this->asignacionActual;
        if (!$asignacion) {
            Notification::make()->title('No asignado')->body('El administrativo no está asignado en esta fecha.')->danger()->send();
            return;
        }
        $user = auth()->user();
        $esPlanilla = $user && method_exists($user, 'hasRole') ? $user->hasRole('Planilla') : false;
        if ($asignacion->user_id !== auth()->id() && !$esPlanilla) {
            Notification::make()
                ->title('No autorizado')
                ->body('Solo el usuario que asignó o un usuario con rol Planilla puede desasignar.')
                ->danger()
                ->send();
            return;
        }
    // Guardar IDs previos para refrescar tarjetas luego
    $procesoFechaId = $asignacion->profec_iCodigo;
    $localIdAnterior = $asignacion->loc_iCodigo;
    $expAdmIdAnterior = $asignacion->expadm_iCodigo;

        $localCargo = LocalCargo::where('loc_iCodigo', $asignacion->loc_iCodigo ?? 0)
            ->where('expadm_iCodigo', $asignacion->expadm_iCodigo ?? 0)
            ->first();

        $asignacion->update([
            'proadm_iAsignacion' => false,
            'proadm_dtFechaDesasignacion' => now(),
            'loc_iCodigo' => null,
            'expadm_iCodigo' => null,
            'proadm_dtFechaAsignacion' => null,
            'user_idDesasignador' => auth()->id(),
            'proadm_iCredencial' => false,
        ]);

        if ($localCargo && $localCargo->loccar_iOcupado > 0) {
            $localCargo->decrement('loccar_iOcupado');
        }

        // Disparar evento para refrescar tarjetas/tablas que escuchen el contexto
        if ($procesoFechaId && $localIdAnterior && $expAdmIdAnterior) {
            $this->dispatch(
                'contextoActualizado',
                procesoFechaId: $procesoFechaId,
                localId: $localIdAnterior,
                experienciaAdmisionId: $expAdmIdAnterior,
            );
        }

        Notification::make()->title('Desasignación exitosa')->success()->send();

        $this->form->fill([
            'proceso_id' => $this->data['proceso_id'] ?? null,
            'proceso_fecha_id' => $this->data['proceso_fecha_id'] ?? null,
            'administrativo_dni' => null,
        ]);
    }

    public function getActions(): array
    {
        return [
            Action::make('desasignar')
                ->label('Desasignar')
                ->color('danger')
                ->modalHeading('Confirmar Desasignación')
                ->modalDescription('¿Está seguro que desea desasignar al administrativo?')
                ->modalSubmitActionLabel('Sí, desasignar')
                ->action(fn () => $this->desasignarAdministrativo())
                ->visible(fn () => filled($this->asignacionActual)),
        ];
    }

    public function getModalHeading(): string
    {
        return 'Confirmar Desasignación';
    }

    public function getModalContent(): string
    {
        return '¿Está seguro que desea desasignar al administrativo?';
    }
}
