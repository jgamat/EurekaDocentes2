<?php

namespace App\Filament\Pages;

use App\Models\Proceso;
use App\Models\ProcesoFecha;
use App\Models\ProcesoDocente;
use App\Models\ProcesoAdministrativo;
use App\Models\ProcesoAlumno;
use Filament\Forms; 
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables; 
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Filament\Notifications\Notification;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class ReporteAsignados extends Page implements HasTable, HasForms
{
    use InteractsWithTable, InteractsWithForms;
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?string $title = 'Reporte Asignados';
    protected static string $view = 'filament.pages.reporte-asignados';
    // Ampliar ancho máximo del contenido para evitar scroll horizontal en la tabla
    // Debe ser propiedad de instancia (el padre la define como no estática)
    protected ?string $maxContentWidth = 'full';

    public array $filters = [
        'proceso_id' => null,
        'proceso_fecha_id' => null,
        'tipo' => null,
    ];

    public function mount(): void
    {
        
        $abierto = Proceso::where('pro_iAbierto', true)->first();
        if ($abierto) {
            $this->filters['proceso_id'] = $abierto->pro_iCodigo;
            $activa = $abierto->procesoFecha()->where('profec_iActivo', true)->first();
            if ($activa) {
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
                    ->options(Proceso::where('pro_iAbierto', true)->orderBy('pro_vcNombre')->pluck('pro_vcNombre', 'pro_iCodigo'))
                    ->reactive()
                    ->afterStateUpdated(function($state){
                        $this->filters['proceso_id'] = $state;
                        // Reset fecha al cambiar proceso
                        $this->filters['proceso_fecha_id'] = null;
                    })
                    ->columnSpan(12),
                Select::make('proceso_fecha_id')
                    ->label('Fecha Activa')
                    ->options(function(){
                        $pid = $this->filters['proceso_id'] ?? null;
                        if(!$pid) return [];
                        return ProcesoFecha::where('pro_iCodigo',$pid)
                            ->where('profec_iActivo', true)
                            ->orderBy('profec_dFecha')
                            ->pluck('profec_dFecha','profec_iCodigo');
                    })
                    ->reactive()
                    ->afterStateUpdated(fn($state)=> $this->filters['proceso_fecha_id'] = $state)
                    ->placeholder('Seleccione fecha')
                    ->columnSpan(12),
                Select::make('tipo')
                    ->label('Tipo de Personal')
                    ->options([
                        'administrativo' => 'Administrativo',
                        'docente' => 'Docente',
                        'alumno' => 'Alumno',
                    ])
                    ->reactive()
                    ->afterStateUpdated(fn($state) => $this->filters['tipo'] = $state)
                    ->extraAttributes(['class'=>'w-full','style'=>'min-width:460px;'])
                    ->columnSpan(['default'=>12,'md'=>6,'xl'=>6]),
                // Se elimina el select de Usuario Asignador: ahora los datos se restringen automáticamente por roles
            ])
            ->columns(12)
            ->statePath('filters');
    }

    protected function getTableQuery(): ?Builder
    {
        $procesoFecha = $this->filters['proceso_fecha_id'] ?? null;
        $tipo = $this->filters['tipo'] ?? null;
        if(!$procesoFecha || !$tipo){
            return match($tipo){
                'administrativo' => ProcesoAdministrativo::query()->whereRaw('1=0'),
                'docente' => ProcesoDocente::query()->whereRaw('1=0'),
                'alumno' => ProcesoAlumno::query()->whereRaw('1=0'),
                default => ProcesoAdministrativo::query()->whereRaw('1=0'),
            };
        }
    $user = Auth::user();
    $currentRoles = $user?->getRoleNames() ?? collect();
        $base = match($tipo){
            'administrativo' => ProcesoAdministrativo::query()
                ->with(['administrativo','experienciaAdmision.maestro','local.localesMaestro','usuario','procesoFecha'])
                ->where('procesoadministrativo.profec_iCodigo',$procesoFecha)
                ->where('proadm_iAsignacion', true),
            'docente' => ProcesoDocente::query()
                ->with(['docente','experienciaAdmision.maestro','local.localesMaestro','usuario','procesoFecha'])
                ->where('procesodocente.profec_iCodigo',$procesoFecha)
                ->where('prodoc_iAsignacion', true),
            'alumno' => ProcesoAlumno::query()
                ->with(['alumno','experienciaAdmision.maestro','local.localesMaestro','usuario','procesoFecha'])
                ->where('procesoalumno.profec_iCodigo',$procesoFecha)
                ->where('proalu_iAsignacion', true),
            default => null,
        };
        if($base){
            // super_admin ve todo, el resto se restringe a roles coincidentes
            if(!$user || !$user->hasRole('super_admin')){
                if($currentRoles->isNotEmpty()){
                    $base->whereHas('usuario.roles', fn($q) => $q->whereIn('name', $currentRoles));
                } else {
                    // Sin roles -> sin resultados
                    $base->whereRaw('1=0');
                }
            }
            $main = $base->getModel()->getTable();
            // Joins para ordenar
          $base->leftJoin('locales','locales.loc_iCodigo','=',$main.'.loc_iCodigo')
              ->leftJoin('localMaestro as lm','lm.locma_iCodigo','=','locales.locma_iCodigo')
              ->leftJoin('experienciaadmision','experienciaadmision.expadm_iCodigo','=',$main.'.expadm_iCodigo')
              ->leftJoin('experienciaadmisionMaestro as eam','eam.expadmma_iCodigo','=','experienciaadmision.expadmma_iCodigo');
            $base->select($main.'.*');
          $base->orderBy('lm.locma_vcNombre')
              ->orderBy('eam.expadmma_vcNombre');
            if($tipo==='administrativo'){
                $base->leftJoin('administrativo','administrativo.adm_vcDni','=',$main.'.adm_vcDni')
                     ->orderBy('administrativo.adm_vcNombres');
            } elseif($tipo==='docente'){
                $base->leftJoin('docente','docente.doc_vcCodigo','=',$main.'.doc_vcCodigo')
                     ->orderBy('docente.doc_vcPaterno')
                     ->orderBy('docente.doc_vcMaterno')
                     ->orderBy('docente.doc_vcNombre');
            } else {
                $base->leftJoin('alumno','alumno.alu_vcCodigo','=',$main.'.alu_vcCodigo')
                     ->orderBy('alumno.alu_vcPaterno')
                     ->orderBy('alumno.alu_vcMaterno')
                     ->orderBy('alumno.alu_vcNombre');
            }
        }
        return $base;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(fn()=> $this->buildHeading())
            ->query(fn()=> $this->getTableQuery())
            ->defaultSort(null)
            ->columns($this->getColumns())
            ->actions([])
            ->filters($this->getTableFilters())
            ->emptyStateHeading('Seleccione filtros para ver resultados')
            ->striped()
            ->paginated(true)
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25,50,100])
            ->headerActions([
                Action::make('exportar_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn()=> $this->exportExcel())
                    ->disabled(fn()=> !$this->filters['proceso_fecha_id'] || !$this->filters['tipo'])
            ]);
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('local')
                ->label('Local')
                ->form([
                    Select::make('loc')
                        ->label('Local')
                        ->options(fn()=> $this->getLocalesFilterOptions())
                        ->searchable()
                ])
                ->query(function(Builder $query, array $data){
                    $value = $data['loc'] ?? null;
                    if(!$value) return $query;
                    $table = $this->getBaseTableName();
                    return $query->where($table.'.loc_iCodigo', $value);
                })
                ->visible(fn()=> (bool)$this->filters['proceso_fecha_id']),
            Filter::make('cargo')
                ->label('Cargo')
                ->form([
                    Select::make('expadm')
                        ->label('Cargo')
                        ->options(fn()=> $this->getCargosFilterOptions())
                        ->searchable()
                ])
                ->query(function(Builder $query, array $data){
                    $value = $data['expadm'] ?? null;
                    if(!$value) return $query;
                    $table = $this->getBaseTableName();
                    return $query->where($table.'.expadm_iCodigo', $value);
                })
                ->visible(fn()=> (bool)$this->filters['proceso_fecha_id']),
        ];
    }

    
    protected function getLocalesFilterOptions(): array
    {
        $pf = $this->filters['proceso_fecha_id'] ?? null;
        $tipo = $this->filters['tipo'] ?? null;
        if(!$pf || !$tipo) return [];
        $main = $this->getBaseTableName();
        if(!$main) return [];
    $user = Auth::user();
    $roleNames = $user?->getRoleNames() ?? collect();

        $asignacionFlag = match($tipo){
            'administrativo' => 'proadm_iAsignacion',
            'docente' => 'prodoc_iAsignacion',
            'alumno' => 'proalu_iAsignacion',
            default => null,
        };
        if(!$asignacionFlag) return [];

        // Construcción base
        $rowsQuery = DB::table($main)
            ->select($main.'.loc_iCodigo','lm.locma_vcNombre')
            ->leftJoin('locales','locales.loc_iCodigo','=',$main.'.loc_iCodigo')
            ->leftJoin('localMaestro as lm','lm.locma_iCodigo','=','locales.locma_iCodigo')
            ->where($main.'.profec_iCodigo',$pf)
            ->where($asignacionFlag, true)
            ->whereNotNull($main.'.loc_iCodigo');
        if(!$user || !$user->hasRole('super_admin')){
            if($roleNames->isNotEmpty()){
                $rowsQuery->whereExists(function($q) use ($main,$roleNames){
                    $q->select(DB::raw(1))
                      ->from('users')
                      ->join('model_has_roles','model_has_roles.model_id','=','users.id')
                      ->join('roles','roles.id','=','model_has_roles.role_id')
                      ->whereColumn('users.id',$main.'.user_id')
                      ->where('model_has_roles.model_type', User::class)
                      ->whereIn('roles.name',$roleNames->toArray());
                });
            } else {
                return []; // sin roles visibles
            }
        }
        $rows = $rowsQuery->distinct()->orderBy('lm.locma_vcNombre')->get();
        return $rows->mapWithKeys(fn($r)=> [ $r->loc_iCodigo => ($r->locma_vcNombre ?: ('Local #'.$r->loc_iCodigo)) ])->toArray();
    }

    
    protected function getCargosFilterOptions(): array
    {
        $pf = $this->filters['proceso_fecha_id'] ?? null;
        $tipo = $this->filters['tipo'] ?? null;
        if(!$pf || !$tipo) return [];
        $main = $this->getBaseTableName();
        if(!$main) return [];
    $user = Auth::user();
    $roleNames = $user?->getRoleNames() ?? collect();

        $asignacionFlag = match($tipo){
            'administrativo' => 'proadm_iAsignacion',
            'docente' => 'prodoc_iAsignacion',
            'alumno' => 'proalu_iAsignacion',
            default => null,
        };
        if(!$asignacionFlag) return [];

        $rowsQuery = DB::table($main)
            ->select($main.'.expadm_iCodigo','eam.expadmma_vcNombre')
            ->leftJoin('experienciaadmision','experienciaadmision.expadm_iCodigo','=',$main.'.expadm_iCodigo')
            ->leftJoin('experienciaadmisionMaestro as eam','eam.expadmma_iCodigo','=','experienciaadmision.expadmma_iCodigo')
            ->where($main.'.profec_iCodigo',$pf)
            ->where($asignacionFlag, true)
            ->whereNotNull($main.'.expadm_iCodigo');
        if(!$user || !$user->hasRole('super_admin')){
            if($roleNames->isNotEmpty()){
                $rowsQuery->whereExists(function($q) use ($main,$roleNames){
                    $q->select(DB::raw(1))
                      ->from('users')
                      ->join('model_has_roles','model_has_roles.model_id','=','users.id')
                      ->join('roles','roles.id','=','model_has_roles.role_id')
                      ->whereColumn('users.id',$main.'.user_id')
                      ->where('model_has_roles.model_type', User::class)
                      ->whereIn('roles.name',$roleNames->toArray());
                });
            } else { return []; }
        }
        $rows = $rowsQuery->distinct()->orderBy('eam.expadmma_vcNombre')->get();
        return $rows->mapWithKeys(fn($r)=> [ $r->expadm_iCodigo => ($r->expadmma_vcNombre ?: ('Cargo #'.$r->expadm_iCodigo)) ])->toArray();
    }

    protected function getBaseTableName(): ?string
    {
        return match($this->filters['tipo'] ?? null){
            'administrativo' => 'procesoadministrativo',
            'docente' => 'procesodocente',
            'alumno' => 'procesoalumno',
            default => null,
        };
    }

    protected function getLocalesOptions(): array
    {
        $fecha = $this->filters['proceso_fecha_id'] ?? null;
        if(!$fecha) return [];
        return \App\Models\Locales::where('profec_iCodigo',$fecha)
            ->with('localesMaestro')
            ->orderBy('loc_iCodigo')
            ->get()
            ->mapWithKeys(fn($l)=> [$l->loc_iCodigo => $l->localesMaestro?->locma_vcNombre ?? ('Local '.$l->loc_iCodigo)])
            ->toArray();
    }

    protected function getCargosOptions(): array
    {
        $fecha = $this->filters['proceso_fecha_id'] ?? null;
        if(!$fecha) return [];
        $items = \App\Models\ExperienciaAdmision::where('profec_iCodigo',$fecha)
            ->with('maestro')
            ->get();
        // Ordenar en memoria por nombre maestro (o fallback id)
        $sorted = $items->sortBy(fn($c)=> $c->maestro?->expadmma_vcNombre ?? $c->expadm_iCodigo);
        return $sorted->mapWithKeys(fn($c)=> [
            $c->expadm_iCodigo => $c->maestro?->expadmma_vcNombre ?? ('Cargo #'.$c->expadm_iCodigo)
        ])->toArray();
    }

    protected function buildHeading(): string
    {
        $tipo = $this->filters['tipo'] ? ucfirst($this->filters['tipo']) : '';
        $hora = now()->format('H:i:s');
        return "Reporte de Personal {$tipo} asignado a la hora {$hora}";
    }

    protected function getColumns(): array
    {
        return [
            // Administrativos
            TextColumn::make('administrativo.adm_vcCodigo')
                ->label('Código')
                ->visible(fn() => $this->filters['tipo'] === 'administrativo')
                ->sortable()
                ->searchable(),
            TextColumn::make('administrativo.adm_vcDni')
                ->label('DNI')
                ->visible(fn() => $this->filters['tipo'] === 'administrativo')
                ->sortable()
                ->searchable(),
            TextColumn::make('administrativo.adm_vcNombres')
                ->label('Nombre Completo')
                ->visible(fn() => $this->filters['tipo'] === 'administrativo')
                ->wrap()
                ->searchable(),
            // Docentes
            TextColumn::make('docente.doc_vcCodigo')
                ->label('Código')
                ->visible(fn() => $this->filters['tipo'] === 'docente')
                ->sortable()
                ->searchable(),
            TextColumn::make('docente.doc_vcDni')
                ->label('DNI')
                ->visible(fn() => $this->filters['tipo'] === 'docente')
                ->sortable()
                ->searchable(),
            TextColumn::make('docente.nombre_completo')
                ->label('Nombre Completo')
                ->visible(fn() => $this->filters['tipo'] === 'docente')
                ->wrap()
                ->searchable(['docente.doc_vcNombre','docente.doc_vcPaterno','docente.doc_vcMaterno']),
            // Alumnos
            TextColumn::make('alumno.alu_vcCodigo')
                ->label('Código')
                ->visible(fn() => $this->filters['tipo'] === 'alumno')
                ->sortable()
                ->searchable(),
            TextColumn::make('alumno.alu_vcDni')
                ->label('DNI')
                ->visible(fn() => $this->filters['tipo'] === 'alumno')
                ->sortable()
                ->searchable(),
            TextColumn::make('alumno.nombre_completo')
                ->label('Nombre Completo')
                ->visible(fn() => $this->filters['tipo'] === 'alumno')
                ->wrap()
                ->searchable(['alumno.alu_vcNombre','alumno.alu_vcPaterno','alumno.alu_vcMaterno']),
            // Comunes
            TextColumn::make('local.localesMaestro.locma_vcNombre')->label('Local')->sortable()->searchable(),
            TextColumn::make('experienciaAdmision.maestro.expadmma_vcNombre')->label('Cargo')->sortable()->searchable(),
            TextColumn::make('usuario.name')->label('Usuario Asignador')->sortable(),
    ];
    }

   

    protected function exportExcel()
    {
        $query = $this->getTableQuery();
        if(!$query){
            Notification::make()->title('Filtros incompletos')->warning()->send();
            return null;
        }
        $records = $query->get();
        if($records->isEmpty()){
            Notification::make()->title('No hay datos para exportar')->info()->send();
            return null;
        }
    $tipo = $this->filters['tipo'];
    $fechaLabel = optional(ProcesoFecha::find($this->filters['proceso_fecha_id']))->profec_dFecha;
    $exportData = $records->values()->map(function($rec, $idx) use ($tipo,$fechaLabel){
            $local = $rec->local?->localesMaestro?->locma_vcNombre;
            $cargo = $rec->experienciaAdmision?->maestro?->expadmma_vcNombre;
            $usuario = $rec->usuario?->name;
            $fechaAsignacion = $rec->proadm_dtFechaAsignacion ?? $rec->prodoc_dtFechaAsignacion ?? $rec->proalu_dtFechaAsignacion ?? null;
            if($tipo==='administrativo'){
                return [
                    'Nro'=>$idx+1,
                    'Codigo'=>$rec->administrativo?->adm_vcCodigo ?? '',
                    'Dni'=>$rec->administrativo?->adm_vcDni ?? '',
                    'Nombres_Completos'=>$rec->administrativo?->adm_vcNombres ?? '',
                    'Dependencia'=>$rec->administrativo?->dependencia?->dep_vcNombre ?? '',
                    'Categoria'=>$rec->administrativo?->categoria?->cat_vcNombre ?? '',
                    'Condicion'=>$rec->administrativo?->condicion?->con_vcNombre ?? '',
                    'Celular'=>$rec->administrativo?->adm_vcCelular ?? '',
                    'Email'=>$rec->administrativo?->adm_vcEmailUNMSM ?? ($rec->administrativo?->adm_vcEmailPersonal ?? ''),
                    'Fecha_Examen'=>$fechaLabel,
                    'Local'=>$local,
                    'Cargo'=>$cargo,
                    'Fecha_Asignacion'=>$fechaAsignacion,
                    'Usuario_Asignador'=>$usuario,
                ];
            }
            if($tipo==='docente'){
                return [
                    'Nro'=>$idx+1,
                    'Codigo'=>$rec->docente?->doc_vcCodigo ?? '',
                    'Dni'=>$rec->docente?->doc_vcDni ?? '',
                    'Nombres_Completos'=>$rec->docente?->nombre_completo ?? '',
                    'Dependencia'=>$rec->docente?->dependencia?->dep_vcNombre ?? '',
                    'Categoria'=>$rec->docente?->categoria?->cat_vcNombre ?? '',
                    'Condicion'=>$rec->docente?->condicion?->con_vcNombre ?? '',
                    'Celular'=>$rec->docente?->doc_vcCelular ?? '',
                    'Email'=>$rec->docente?->doc_vcEmailUNMSM ?? ($rec->docente?->doc_vcEmail ?? ''),
                    'Fecha_Examen'=>$fechaLabel,
                    'Local'=>$local,
                    'Cargo'=>$cargo,
                    'Fecha_Asignacion'=>$fechaAsignacion,
                    'Usuario_Asignador'=>$usuario,
                ];
            }
            return [
                'Nro'=>$idx+1,
                'Codigo'=>$rec->alumno?->alu_vcCodigo ?? '',
                'Dni'=>$rec->alumno?->alu_vcDni ?? '',
                'Nombres_Completos'=>$rec->alumno?->nombre_completo ?? '',
                'Facultad'=>$rec->alumno?->fac_vcNombre ?? '',
                'Celular'=>$rec->alumno?->alu_vcCelular ?? '',
                'Email'=>$rec->alumno?->alu_vcEmail ?? ($rec->alumno?->alu_vcEmailPer ?? ''),
                'Fecha_Examen'=>$fechaLabel,
                'Local'=>$local,
                'Cargo'=>$cargo,
                'Fecha_Asignacion'=>$fechaAsignacion,
                'Usuario_Asignador'=>$usuario,
            ];
        });

        $now = now();
        $filename = 'reporte_asignados_'.$tipo.'_'.$now->format('Ymd_His').'.xlsx';
        $title = 'Lista del Personal '.ucfirst($tipo).' al '.$now->format('d/m/Y H:i:s');
        return Excel::download(new class($exportData, $title) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithEvents, \Maatwebsite\Excel\Concerns\ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithStyles {
            public function __construct(private Collection $rows, private string $title){}
            public function collection(){ return $this->rows; }
            public function headings(): array {
                if($this->rows->isEmpty()) return [];
                $keys = array_keys($this->rows->first());
                $titleRow = [$this->title];
                for($i=1;$i<count($keys);$i++){ $titleRow[]=''; }
                $blank = array_fill(0,count($keys),'');
                return [ $titleRow, $blank, $keys ];
            }
            public function registerEvents(): array {
                return [
                    \Maatwebsite\Excel\Events\AfterSheet::class => function(\Maatwebsite\Excel\Events\AfterSheet $event){
                        $sheet = $event->sheet->getDelegate();
                        $rowCount = $this->rows->count();
                        if($rowCount === 0) return;
                        $keys = array_keys($this->rows->first());
                        $lastColLetter = Coordinate::stringFromColumnIndex(count($keys));
                       
                        $sheet->mergeCells("A1:{$lastColLetter}1");
                        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                       
                        $sheet->getStyle("A3:{$lastColLetter}3")->getFont()->setBold(true);
                       
                        $endRow = 3 + $rowCount; 
                        $sheet->getStyle("A3:{$lastColLetter}{$endRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    }
                ];
            }
            public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array { return []; }
        }, $filename);
    }
}
