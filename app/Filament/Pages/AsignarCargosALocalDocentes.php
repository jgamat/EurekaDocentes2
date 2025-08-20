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
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

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
    ];

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
                        $this->filters['proceso_id']=$state; $this->filters['proceso_fecha_id']=null; $this->filters['local_id']=null; $this->form->fill($this->filters);
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
                        $this->filters['proceso_fecha_id']=$state; $this->filters['local_id']=null; $this->form->fill($this->filters);
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
                        $this->filters['local_id']=$state; $this->autoEnsureDocenteCargos();
                    })
                    ->required(),
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

    protected function getTableQuery(): Builder
    {
        $localId = $this->filters['local_id'];
        $fechaId = $this->filters['proceso_fecha_id'];
        $codigos = $this->docenteCargoMaestros();
        return ExperienciaAdmision::query()
            ->where('profec_iCodigo',$fechaId ?? 0)
            ->whereIn('expadmma_iCodigo',$codigos)
            ->whereHas('locales', fn($q)=> $q->where('locales.loc_iCodigo',$localId ?? 0));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn()=> $this->getTableQuery())
            ->columns([
                TextColumn::make('expadmma_iCodigo')->label('Código Cargo')->sortable(),
                TextColumn::make('maestro.expadmma_vcNombre')->label('Cargo')->searchable(),
                TextColumn::make('locales_count')->label('Locales Asociados')->counts('locales'),
            ])
            ->paginated(false);
    }
}
