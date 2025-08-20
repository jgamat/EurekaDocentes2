<?php


namespace App\Filament\Pages;

use App\Models\Proceso;
use App\Models\ProcesoFecha;
use App\Models\Docente;
use App\Models\ProcesoDocente;
use App\Models\LocalCargo;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Filament\Pages\Actions\Action;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class DesasignarDocente extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-user-minus';
    protected static string $view = 'filament.pages.desasignar-docente';
    protected static ?string $navigationLabel = 'Desasignar Docente';
    protected static ?string $navigationGroup = 'Docentes';

    public ?array $data = [];
    
    public function mount(): void
    {
        $this->form->fill();
    }

    public function getAsignacionActualProperty()
    {
        $data = $this->form->getState();
        $docenteCodigo = $data['docente_id'] ?? null;
        $fechaId = $data['proceso_fecha_id'] ?? null;

        if ($docenteCodigo && $fechaId) {
            return ProcesoDocente::where('doc_vcCodigo', $docenteCodigo)
                ->where('profec_iCodigo', $fechaId)
                ->where('prodoc_iAsignacion', true)
                ->first();
        }
        return null;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('proceso_id')
                    ->label('1. Seleccione el Proceso Abierto')
                    ->options(Proceso::where('pro_iAbierto', true)->pluck('pro_vcNombre', 'pro_iCodigo'))
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set, $livewire) {
                        $set('docente_id', null);
                        $livewire->resetValidation();
                    }),

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
                    ->afterStateUpdated(function ($state, callable $set, $livewire) {
                        $set('docente_id', null);
                        $livewire->resetValidation();
                    }),

                Select::make('docente_id')
                    ->label('Buscar Docente')
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search) {
                        if (strlen($search) < 2) return [];
                        return Docente::where('doc_iActivo', 1)
                            ->where(function ($query) use ($search) {
                                $query->where('doc_vcNombre', 'like', "%{$search}%")
                                    ->orWhere('doc_vcPaterno', 'like', "%{$search}%")
                                    ->orWhere('doc_vcMaterno', 'like', "%{$search}%")
                                    ->orWhere('doc_vcDni', 'like', "%{$search}%")
                                    ->orWhere('doc_vcCodigo', 'like', "%{$search}%");
                            })
                            ->limit(10)
                            ->get()
                            ->mapWithKeys(fn($docente) => [
                                $docente->doc_vcCodigo => "{$docente->nombre_completo} - {$docente->doc_vcDni} - {$docente->doc_vcCodigo}"
                            ])
                            ->toArray();
                    })
                    ->required(fn (callable $get) => filled($get('proceso_fecha_id')))
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set, $livewire) {
                        $livewire->resetValidation();
                    }),
            ])
            ->statePath('data');
    }

    

    public function desasignarDocente()
    {
        $asignacion = $this->asignacionActual;
        if (!$asignacion) {
            Notification::make()->title('No asignado')->body('El docente no está asignado en esta fecha.')->danger()->send();
           
            return;
        }
        if ($asignacion->user_id !== auth()->id()) {
            Notification::make()->title('No autorizado')->body('Solo el usuario que asignó puede desasignar.')->danger()->send();
           
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
            'prodoc_iAsignacion' => false,
            'prodoc_dtFechaDesasignacion' => now(),
            'loc_iCodigo' => null,
            'expadm_iCodigo' => null,
            'prodoc_dtFechaAsignacion' => null,
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
            'docente_id' => null,
        ]);
        
    }

    public function getActions(): array
    {
        return [
            Action::make('desasignar')
                ->label('Desasignar')
                ->color('danger')
                ->modalHeading('Confirmar Desasignación')
                ->modalDescription('¿Está seguro que desea desasignar al docente?')
                ->modalSubmitActionLabel('Sí, desasignar')
                ->action(fn () => $this->desasignarDocente())
                ->visible(fn () => filled($this->asignacionActual)),
        ];
    }

    public function getModalHeading(): string
    {
        return 'Confirmar Desasignación';
    }

    public function getModalContent(): string
    {
        return '¿Está seguro que desea desasignar al docente?';
    }

   public function confirmarDesasignacion()
    {
        $this->showModal('confirmarDesasignacion');
    }
}