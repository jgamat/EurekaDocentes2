<?php

namespace App\Filament\Pages;

use App\Models\Locales;
use App\Models\ExperienciaAdmision;
use App\Models\ExperienciaAdmisionMaestro;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\EditAction as TableEditAction;
use Filament\Tables\Actions\Action as TableAction;

class AsignarCargosALocalDocentes extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable, HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationGroup = 'Administración de Locales';
    protected static ?string $navigationLabel = 'Cargos Docentes (Auto)';
    protected static string $view = 'filament.pages.asignar-cargos-a-local-docentes';

    public array $filters = [
        'proceso_id' => null,
        'proceso_fecha_id' => null,
        'local_id' => null,
        'vacantes' => [
            '2' => 0,
            '3' => 0,
            '4' => 0,
        ],
    ];

    public bool $mostrarTabla = false;

    public function mount(): void
    {
        $abierto = \App\Models\Proceso::where('pro_iAbierto', true)->first();
        if($abierto){
            $this->filters['proceso_id'] = $abierto->pro_iCodigo;
            $activa = $abierto->procesoFecha()->where('profec_iActivo', true)->first();
            if($activa){
                $this->filters['proceso_fecha_id'] = $activa->profec_iCodigo;
            }
        }
    $this->form->fill($this->filters);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('proceso_id')
                    ->label('Proceso Abierto')
                    ->options(fn()=> \App\Models\Proceso::where('pro_iAbierto', true)->orderBy('pro_vcNombre')->pluck('pro_vcNombre','pro_iCodigo'))
                    ->reactive()
                    ->afterStateUpdated(function($state){
                        $this->filters['proceso_id']=$state; $this->filters['proceso_fecha_id']=null; $this->filters['local_id']=null; $this->mostrarTabla = false; $this->form->fill($this->filters);
                    })
                    ->required(),
                Select::make('proceso_fecha_id')
                    ->label('Fecha Activa')
                    ->options(function(){
                        $pid = $this->filters['proceso_id'];
                        if(!$pid) return [];
                        return \App\Models\ProcesoFecha::where('pro_iCodigo',$pid)
                            ->where('profec_iActivo', true)
                            ->orderBy('profec_dFecha')
                            ->pluck('profec_dFecha','profec_iCodigo');
                    })
                    ->reactive()
                    ->afterStateUpdated(function($state){
                        $this->filters['proceso_fecha_id']=$state; $this->filters['local_id']=null; $this->mostrarTabla = false; $this->form->fill($this->filters);
                    })
                    ->required(),
                Select::make('local_id')
                    ->label('Local Asignado')
                    ->options(function(){
                        $fecha = $this->filters['proceso_fecha_id'];
                        if(!$fecha) return [];
                        return Locales::where('profec_iCodigo',$fecha)->with('localesMaestro','procesoFecha')
                            ->get()->mapWithKeys(fn($l)=>[$l->loc_iCodigo => ($l->localesMaestro->locma_vcNombre ?? 'N/A')]);
                    })
                    ->reactive()
                    ->afterStateUpdated(function($state){
                        $this->filters['local_id']=$state; $this->mostrarTabla = false; $this->prefillVacantes();
                    })
                    ->required(),

                Section::make('Cargos a asignar (Docentes)')
                    ->description('Ingrese el número de vacantes para cada cargo y guarde las asignaciones.')
                    ->visible(fn()=> filled($this->filters['local_id']))
                    ->schema([
                        Grid::make(2)->schema([
                            Placeholder::make('cargo_2')
                                ->label('Cargo')
                                ->content(fn()=> $this->cargoNombre(2)),
                            TextInput::make('vacantes.2')->label('Vacantes')->numeric()->minValue(0)->default(0),
                        ]),
                        Grid::make(2)->schema([
                            Placeholder::make('cargo_3')
                                ->label('Cargo')
                                ->content(fn()=> $this->cargoNombre(3)),
                            TextInput::make('vacantes.3')->label('Vacantes')->numeric()->minValue(0)->default(0),
                        ]),
                        Grid::make(2)->schema([
                            Placeholder::make('cargo_4')
                                ->label('Cargo')
                                ->content(fn()=> $this->cargoNombre(4)),
                            TextInput::make('vacantes.4')->label('Vacantes')->numeric()->minValue(0)->default(0),
                        ]),
                    ]),
            ])
            ->statePath('filters');
    }

    protected function docenteCargoMaestros(): array
    {
        return [2,3,4];
    }

    protected function autoEnsureDocenteCargos(): void
    {
        $localId = $this->filters['local_id'];
        $fechaId = $this->filters['proceso_fecha_id'];
        if(!$localId || !$fechaId) return;

        $codigos = $this->docenteCargoMaestros();
        // Para cada maestro, asegurar instancia ExperienciaAdmision y vínculo con local
        foreach($codigos as $maestroId){
            $instancia = ExperienciaAdmision::firstOrCreate([
                'profec_iCodigo' => $fechaId,
                'expadmma_iCodigo' => $maestroId,
            ]);
            // Sincronizar sin perder otras relaciones existentes
            $loc = Locales::find($localId);
            if($loc){
                $loc->experienciaAdmision()->syncWithoutDetaching([
                    $instancia->expadm_iCodigo => ['loccar_iVacante' => $loc->experienciaAdmision()->where('experienciaadmision.expadm_iCodigo',$instancia->expadm_iCodigo)->first()->pivot->loccar_iVacante ?? 0]
                ]);
            }
        }
    }

    protected function prefillVacantes(): void
    {
        $localId = $this->filters['local_id'] ?? null;
        $fechaId = $this->filters['proceso_fecha_id'] ?? null;
        if(!$localId || !$fechaId) return;
        $loc = Locales::find($localId);
        if(!$loc) return;
        foreach($this->docenteCargoMaestros() as $maestroId){
            $inst = ExperienciaAdmision::where('profec_iCodigo',$fechaId)->where('expadmma_iCodigo',$maestroId)->first();
            $vac = 0;
            if($inst){
                $pivotRow = $loc->experienciaAdmision()->where('experienciaadmision.expadm_iCodigo',$inst->expadm_iCodigo)->first();
                $vac = $pivotRow?->pivot?->loccar_iVacante ?? 0;
            }
            $this->filters['vacantes'][(string)$maestroId] = (int)$vac;
        }
        $this->form->fill($this->filters);
    }

    protected function cargoNombre(int $maestroId): string
    {
        return ExperienciaAdmisionMaestro::find($maestroId)?->expadmma_vcNombre ?? ('Cargo '.$maestroId);
    }

    protected function getTableQuery(): Builder
    {
        $localId = $this->filters['local_id'];
        $fechaId = $this->filters['proceso_fecha_id'];
        $codigos = $this->docenteCargoMaestros();
        return ExperienciaAdmision::query()
            ->select('experienciaadmision.*','localcargo.loccar_iVacante as pivot_loccar_iVacante','localcargo.loccar_iOcupado as pivot_loccar_iOcupado')
            ->join('localcargo','localcargo.expadm_iCodigo','=','experienciaadmision.expadm_iCodigo')
            ->where('localcargo.loc_iCodigo',$localId ?? 0)
            ->where('experienciaadmision.profec_iCodigo',$fechaId ?? 0)
            ->whereIn('experienciaadmision.expadmma_iCodigo',$codigos);
    }

    public function hasDocentesAsignados(): bool
    {
        // Determina si hay al menos un cargo docente asignado con vacantes > 0 para el local/fecha seleccionados
        try {
            return $this->getTableQuery()->count() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn()=> $this->getTableQuery())
            ->columns([
                TextColumn::make('maestro.expadmma_vcNombre')->label('Cargo')->searchable()->sortable(),
                TextColumn::make('pivot_loccar_iVacante')->label('Vacantes')->sortable(),
                TextColumn::make('pivot_loccar_iOcupado')->label('Ocupados')->sortable(),
            ])
            ->paginated(false)
            ->actions([
                TableEditAction::make()
                    ->label('Editar')
                    ->form([
                        TextInput::make('loccar_iVacante')->label('Vacantes')->numeric()->minValue(0)->required(),
                    ])
                    ->using(function($record, array $data){
                        $localId = $this->filters['local_id'] ?? null;
                        if(!$localId) return;
                        $local = Locales::find($localId);
                        if(!$local) return;
                        $ocupados = (int)($record->pivot_loccar_iOcupado ?? 0);
                        $vac = (int)($data['loccar_iVacante'] ?? 0);
                        if($vac < $ocupados){
                            Notification::make()->title('No permitido')->body("Las vacantes ({$vac}) no pueden ser menores que ocupados ({$ocupados}).")->danger()->send();
                            return;
                        }
                        $local->experienciaAdmision()->updateExistingPivot($record->expadm_iCodigo, ['loccar_iVacante' => $vac]);
                        Notification::make()->title('Actualizado')->success()->send();
                        $this->prefillVacantes();
                    }),
                TableAction::make('desvincular')
                    ->label('Desvincular')
                    ->requiresConfirmation()
                    ->action(function($record){
                        $localId = $this->filters['local_id'] ?? null;
                        if(!$localId) return;
                        $local = Locales::find($localId);
                        if(!$local) return;
                        $ocupados = (int)($record->pivot_loccar_iOcupado ?? 0);
                        if($ocupados > 0){
                            Notification::make()->title('No permitido')->body('No se puede desvincular: tiene ocupados.')->danger()->send();
                            return;
                        }
                        $local->experienciaAdmision()->detach($record->expadm_iCodigo);
                        Notification::make()->title('Desvinculado')->success()->send();
                        $this->prefillVacantes();
                    })
            ]);
    }

    protected function getSaveAction(): Action
    {
        return Action::make('save')->label('Guardar asignaciones')->submit('save');
    }

    public function save(): void
    {
        $localId = $this->filters['local_id'] ?? null;
        $fechaId = $this->filters['proceso_fecha_id'] ?? null;
        if(!$localId || !$fechaId){
            Notification::make()->title('Selecciona local y fecha')->danger()->send();
            return;
        }
        $local = Locales::find($localId);
        if(!$local){ return; }
        $sync = [];
        foreach($this->docenteCargoMaestros() as $maestroId){
            $inst = ExperienciaAdmision::firstOrCreate([
                'profec_iCodigo' => $fechaId,
                'expadmma_iCodigo' => $maestroId,
            ]);
            $vac = (int)($this->filters['vacantes'][(string)$maestroId] ?? 0);
            $pivotRow = $local->experienciaAdmision()->where('experienciaadmision.expadm_iCodigo',$inst->expadm_iCodigo)->first();
            $ocupados = (int)($pivotRow?->pivot?->loccar_iOcupado ?? 0);
            if($vac < $ocupados){
                Notification::make()->title('No permitido')->body("Las vacantes ({$vac}) no pueden ser menores que ocupados ({$ocupados}).")->danger()->send();
                continue;
            }
            $sync[$inst->expadm_iCodigo] = ['loccar_iVacante' => $vac];
        }
        if(!empty($sync)){
            $local->experienciaAdmision()->syncWithoutDetaching($sync);
        }
        Notification::make()->title('Asignaciones guardadas')->success()->send();
        $this->prefillVacantes();
    // Mostrar la tabla solo después del primer guardado en esta sesión
    $this->mostrarTabla = true;
    }
}
