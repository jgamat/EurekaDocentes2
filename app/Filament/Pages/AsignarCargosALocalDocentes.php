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
use Illuminate\Support\Facades\DB;
use App\Support\Traits\UsesGlobalContext;
use App\Support\CurrentContext;

class AsignarCargosALocalDocentes extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable, HasPageShield;
    use UsesGlobalContext;

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

    protected $listeners = ['context-changed' => 'onContextChanged'];

    public bool $mostrarTabla = false;

    public function mount(): void
    {
        $this->filters['proceso_id'] = app(CurrentContext::class)->procesoId();
        $this->filters['proceso_fecha_id'] = app(CurrentContext::class)->fechaId();
        $this->form->fill($this->filters);
    }

    public function onContextChanged(): void
    {
        // Aplicar nuevo contexto y limpiar local + vacantes
        $this->applyContextFromGlobal(['proceso_id','proceso_fecha_id'], ['local_id'], 'Contexto actualizado.');
        $this->filters['local_id'] = null;
        foreach(['2','3','4'] as $k){ $this->filters['vacantes'][$k] = 0; }
        $this->mostrarTabla = false;
        $this->form->fill($this->filters);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Fecha global solo lectura
                $this->fechaActualPlaceholder('proceso_fecha_id'),
                Select::make('proceso_id')
                    ->label('Proceso Abierto')
                    ->options(fn()=> \App\Models\Proceso::where('pro_iAbierto', true)->orderBy('pro_vcNombre')->pluck('pro_vcNombre','pro_iCodigo'))
                    ->reactive()
                    ->afterStateUpdated(function($state){
                        $this->filters['proceso_id']=(int)$state; $this->filters['proceso_fecha_id']=null; $this->filters['local_id']=null; $this->mostrarTabla = false; $this->form->fill($this->filters);
                    })
                    ->required()
                    ->hidden(),
                Select::make('proceso_fecha_id')
                    ->label('Fecha Activa')
                    ->options(function(){
                        $pid = (int)($this->filters['proceso_id'] ?? 0);
                        if(!$pid) return [];
                        return \App\Models\ProcesoFecha::where('pro_iCodigo',$pid)
                            ->where('profec_iActivo', true)
                            ->orderBy('profec_dFecha')
                            ->pluck('profec_dFecha','profec_iCodigo');
                    })
                    ->reactive()
                    ->afterStateUpdated(function($state){
                        $this->filters['proceso_fecha_id']=(int)$state; $this->filters['local_id']=null; $this->mostrarTabla = false; $this->form->fill($this->filters);
                    })
                    ->required()
                    ->hidden(),
                Select::make('local_id')
                    ->label('Local Asignado')
                    ->options(function(){
                        $fecha = (int)($this->filters['proceso_fecha_id'] ?? 0);
                        if(!$fecha) return [];
                        return Locales::where('profec_iCodigo',$fecha)
                            ->with('localesMaestro','procesoFecha')
                            ->get()
                            ->sortBy(function($l){ return $l->localesMaestro->locma_vcNombre ?? ''; })
                            ->mapWithKeys(fn($l)=>[$l->loc_iCodigo => ($l->localesMaestro->locma_vcNombre ?? 'N/A')]);
                    })
                    ->rule('integer')
                    ->rule('exists:locales,loc_iCodigo')
                    ->reactive()
                    ->afterStateUpdated(function($state){
                        $this->filters['local_id']=(int)$state; $this->mostrarTabla = false; $this->prefillVacantes();
                        // Si ya existen los cargos requeridos, mostrar tabla directamente
                        if ($this->allDocenteCargosPresent()) {
                            $this->mostrarTabla = true;
                        }
                        $this->form->fill($this->filters);
                    })
                    ->required(),

                Section::make('Cargos a asignar (Docentes)')
                    ->description('Ingrese el número de vacantes para cada cargo y guarde las asignaciones.')
                    ->visible(fn()=> filled($this->filters['local_id']) && !$this->allDocenteCargosPresent())
                    ->schema([
                        Grid::make(2)->schema([
                            Placeholder::make('cargo_2')
                                ->label('Cargo')
                                ->content(fn()=> $this->cargoNombre(2)),
                            TextInput::make('vacantes.2')->label('Vacantes')->numeric()->rule('integer')->minValue(0)->default(0),
                        ]),
                        Grid::make(2)->schema([
                            Placeholder::make('cargo_3')
                                ->label('Cargo')
                                ->content(fn()=> $this->cargoNombre(3)),
                            TextInput::make('vacantes.3')->label('Vacantes')->numeric()->rule('integer')->minValue(0)->default(0),
                        ]),
                        Grid::make(2)->schema([
                            Placeholder::make('cargo_4')
                                ->label('Cargo')
                                ->content(fn()=> $this->cargoNombre(4)),
                            TextInput::make('vacantes.4')->label('Vacantes')->numeric()->rule('integer')->minValue(0)->default(0),
                        ]),
                    ]),
            ])
            ->statePath('filters');
    }

    protected function docenteCargoMaestros(): array
    {
        return [2,3,4];
    }

    // Retorna true si el local/fecha ya tiene asignados todos los cargos docentes requeridos (2,3,4)
    protected function allDocenteCargosPresent(): bool
    {
        $localId = $this->filters['local_id'] ?? null;
        $fechaId = $this->filters['proceso_fecha_id'] ?? null;
        if(!$localId || !$fechaId) return false;
        try {
            $present = $this->getTableQuery()
                ->clone()
                ->pluck('experienciaadmision.expadmma_iCodigo')
                ->unique()
                ->values();
            $needed = collect($this->docenteCargoMaestros());
            return $needed->diff($present)->isEmpty();
        } catch (\Throwable $e) {
            return false;
        }
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
        $localId = (int)($this->filters['local_id'] ?? 0);
        $fechaId = (int)($this->filters['proceso_fecha_id'] ?? 0);
        $codigos = $this->docenteCargoMaestros();
        return ExperienciaAdmision::query()
            ->select('experienciaadmision.*','localcargo.loccar_iVacante as pivot_loccar_iVacante','localcargo.loccar_iOcupado as pivot_loccar_iOcupado')
            ->join('localcargo','localcargo.expadm_iCodigo','=','experienciaadmision.expadm_iCodigo')
            ->where('localcargo.loc_iCodigo',$localId)
            ->where('experienciaadmision.profec_iCodigo',$fechaId)
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
        // Validación server-side del formulario
        $this->form->validate();

        $localId = (int)($this->filters['local_id'] ?? 0);
        $fechaId = (int)($this->filters['proceso_fecha_id'] ?? 0);
        if(!$localId || !$fechaId){
            Notification::make()->title('Selecciona local y fecha')->danger()->send();
            return;
        }
        DB::transaction(function() use ($localId, $fechaId) {
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
        });
        Notification::make()->title('Asignaciones guardadas')->success()->send();
        $this->prefillVacantes();
    // Mostrar la tabla solo después del primer guardado en esta sesión
    $this->mostrarTabla = true;
    }
}
