<?php

namespace App\Filament\Pages;
use App\Models\ExperienciaAdmision;
use App\Models\ExperienciaAdmisionMaestro;
use App\Models\Locales;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;



class AsignarCargosALocal extends Page
{
   use InteractsWithForms;
   use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static string $view = 'filament.pages.asignar-cargos-a-local';
   protected static ?string $navigationGroup = 'Administración de Locales';
    protected static ?string $navigationLabel = 'Agregar Cargos a Local';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('proceso_id')
                    ->label('Proceso Abierto')
                    ->options(fn()=> \App\Models\Proceso::where('pro_iAbierto', true)->orderBy('pro_vcNombre')->pluck('pro_vcNombre','pro_iCodigo'))
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated(function(callable $set){ $set('proceso_fecha_id', null); $set('local_id', null); })
                    ->required(),
                Select::make('proceso_fecha_id')
                    ->label('Fecha Activa')
                    ->options(function(callable $get){
                        $procesoId = $get('proceso_id');
                        if(!$procesoId) return [];
                        return \App\Models\ProcesoFecha::where('pro_iCodigo',$procesoId)
                            ->where('profec_iActivo', true)
                            ->orderBy('profec_dFecha')
                            ->get()
                            ->mapWithKeys(fn($f)=>[$f->profec_iCodigo => (string)$f->profec_dFecha])
                            ->toArray();
                    })
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated(function(callable $set){ $set('local_id', null); })
                    ->required(),
                Select::make('local_id')
                    ->label('Seleccione el Local Asignado al que añadirá cargos')
                    ->options(function (callable $get) {
                        $fechaId = $get('proceso_fecha_id');
                        if(!$fechaId) return [];
                        return Locales::with(['procesoFecha','localesMaestro'])
                            ->where('profec_iCodigo', $fechaId)
                            ->get()
                            ->mapWithKeys(function ($local) {
                                $localNombre = $local->localesMaestro->locma_vcNombre ?? 'N/A';
                                $fechaNombre = $local->procesoFecha->profec_dFecha ?? 'N/A';
                                return [$local->loc_iCodigo => "{$localNombre} (Fecha: {$fechaNombre})"]; 
                            });
                    })
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($state) => $this->dispatch('cargosActualizados', localId: $state)),
                
                // El Repeater permite añadir bloques de asignación dinámicamente
                Repeater::make('asignaciones')
                    ->label('Cargos a Asignar')                   
                    ->schema([
                 Select::make('expadmma_iCodigo') 
                ->label('Tipo de Cargo')
                ->options(function (callable $get): array {
                   
                    $localId = $get('../../local_id');

                    
                    if (!$localId) {
                        return \App\Models\ExperienciaAdmisionMaestro::pluck('expadmma_Nombre', 'expadmma_iCodigo')->toArray();
                    }

                    
                    $local = Locales::find($localId);
                    if (!$local) {
                        return []; 
                    }

                    $instanciasAsignadasIds = $local->experienciaAdmision()->pluck('experienciaadmision.expadm_iCodigo')->toArray();

                    
                    $maestrosYaAsignadosIds = ExperienciaAdmision::whereIn('expadm_iCodigo', $instanciasAsignadasIds)
                        ->pluck('expadmma_iCodigo') 
                        ->unique()
                        ->toArray();

                    
                    return ExperienciaAdmisionMaestro::whereNotIn('expadmma_iCodigo', $maestrosYaAsignadosIds)
                        ->pluck('expadmma_vcNombre', 'expadmma_iCodigo')
                        ->toArray();
                })
                ->searchable()
                ->required()
                ->distinct()
                ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                        TextInput::make('loccar_iVacante')
                            ->label('Vacantes')
                            ->numeric()
                            ->required()
                            ->default(0),

                        
                    ])
                    ->addActionLabel('Añadir otro Cargo')
                    ->columns(3)
                    ->collapsible()
                    // Solo muestra el repeater si se ha seleccionado un local
                    ->visible(fn (callable $get) => filled($get('local_id'))),
            ])
            ->statePath('data');
    }

     protected function getSaveAction(): Action
    {
        return Action::make('save')->label('Guardar Asignaciones')->submit('save');
    }

    public function save(): void
    {
       
    $data = $this->form->getState();

    // Verificamos que se haya seleccionado un local
    if (empty($data['local_id'])) {
        Notification::make()->title('Error')->body('Primero debes seleccionar un local.')->danger()->send();
        return;
    }
    
    // Verificamos que se haya añadido al menos un cargo en el repeater
    if (empty($data['asignaciones'])) {
        Notification::make()->title('No hay nada que guardar')->body('Debes añadir al menos un cargo para asignar.')->warning()->send();
        return;
    }

    // Obtenemos la instancia del Local seleccionado
    $local = Locales::find($data['local_id']);
    // Obtenemos el ID de la fecha del proceso a través de la relación del local
    $fechaId = $local->profec_iCodigo;

    // Preparamos un array para sincronizar la relación final
    $cargosParaSincronizar = [];

    // Iteramos sobre cada fila que el usuario añadió en el Repeater
    foreach ($data['asignaciones'] as $asignacion) {
        
        $cargoMaestroId = $asignacion['expadmma_iCodigo'];

       
        $instanciaCargo = ExperienciaAdmision::firstOrCreate(
            [
                'profec_iCodigo'     => $fechaId,          // Para esta fecha
                'expadmma_iCodigo' => $cargoMaestroId, // De este tipo de cargo maestro
            ]
        );

        
        $cargosParaSincronizar[$instanciaCargo->expadm_iCodigo] = [
            'loccar_iVacante' => $asignacion['loccar_iVacante'],
           
        ];
    }

   
    $local->experienciaAdmision()->syncWithoutDetaching($cargosParaSincronizar);

    // Enviamos notificación de éxito y reseteamos el formulario
    Notification::make()->title('Cargos asignados con éxito')->success()->send();
    $this->dispatch('cargosActualizados', localId: $data['local_id']); 
    // Mantener proceso y fecha seleccionados, limpiar solo las asignaciones
    $this->form->fill([
        'proceso_id' => $data['proceso_id'] ?? null,
        'proceso_fecha_id' => $data['proceso_fecha_id'] ?? $fechaId,
        'local_id' => $data['local_id'],
        'asignaciones' => [],
    ]);

}

}
