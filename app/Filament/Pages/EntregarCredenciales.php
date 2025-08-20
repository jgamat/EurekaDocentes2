<?php

namespace App\Filament\Pages;

use App\Models\Proceso;
use App\Models\ProcesoFecha;
use App\Models\ProcesoDocente;
use App\Models\EntregaCredencialRow;
use App\Models\ProcesoAdministrativo;
use App\Models\ProcesoAlumno;
use App\Models\Credencial;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn; // (quitar si ya no se usa)
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class EntregarCredenciales extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Credenciales';
    protected static ?string $title = 'Entrega de Credenciales';
    protected static string $view = 'filament.pages.entregar-credenciales';

    public array $filters = [
        'proceso_id' => null,
        'proceso_fecha_id' => null,
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
                    ->afterStateUpdated(function ($state) {
                        $this->filters['proceso_id'] = $state;
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
                    ->columnSpan(12),
            ])
            ->columns(12)
            ->statePath('filters');
    }

    protected function getRoleRestrictedUserId(): ?int
    {
        $user = Auth::user();
        if(!$user) return null;
        if($user->hasRole('super_admin')) return null; // no restricción
        if(collect($user->getRoleNames())->filter(fn($r)=> str_starts_with($r,'economía'))->isNotEmpty()) return null; // roles economía sin restricción
        $restrictedRoles = ['DocentesLocales','ControlCalidad','Oprad','Info','Direccion'];
        if($user->hasAnyRole($restrictedRoles)){
            return $user->id;
        }
        return null;
    }

    protected function baseUnionQuery(): Builder
    {
        $fecha = $this->filters['proceso_fecha_id'] ?? null;
        $userRestrict = $this->getRoleRestrictedUserId();
        if(!$fecha){
            return ProcesoDocente::query()->whereRaw('1=0');
        }

        $doc = ProcesoDocente::query()
            ->select([
                DB::raw("'Docente' as tipo"),
                'procesodocente.prodoc_iCodigo as prodoc_id',
                DB::raw("CONCAT('doc-', procesodocente.prodoc_iCodigo) as row_key"),
                'procesodocente.prodoc_iCodigo as cred_codigo',
                'docente.doc_vcCodigo as codigo',
                'docente.doc_vcDni as dni',
                DB::raw("CONCAT(docente.doc_vcPaterno,' ',docente.doc_vcMaterno,' ',docente.doc_vcNombre) as nombres"),
                'procesodocente.expadm_iCodigo','procesodocente.loc_iCodigo','procesodocente.user_id',
                DB::raw("COALESCE(credencial.cred_iEntregado, procesodocente.prodoc_iCredencial) as entregado_flag")
            ])
            ->join('docente','docente.doc_vcCodigo','=','procesodocente.doc_vcCodigo')
            ->leftJoin('credencial','credencial.cred_iCodigo','=','procesodocente.prodoc_iCodigo')
            ->where('procesodocente.profec_iCodigo',$fecha)
            ->where('prodoc_iAsignacion',true);
        if($userRestrict){ $doc->where('procesodocente.user_id',$userRestrict); }

        $adm = ProcesoAdministrativo::query()
            ->select([
                DB::raw("'Administrativo' as tipo"),
                'procesoadministrativo.proadm_iCodigo as prodoc_id',
                DB::raw("CONCAT('adm-', procesoadministrativo.proadm_iCodigo) as row_key"),
                'procesoadministrativo.proadm_iCodigo as cred_codigo',
                'administrativo.adm_vcCodigo as codigo',
                'administrativo.adm_vcDni as dni',
                'administrativo.adm_vcNombres as nombres',
                'procesoadministrativo.expadm_iCodigo','procesoadministrativo.loc_iCodigo','procesoadministrativo.user_id',
                DB::raw("COALESCE(credencial.cred_iEntregado, procesoadministrativo.proadm_iCredencial) as entregado_flag")
            ])
            ->join('administrativo','administrativo.adm_vcDni','=','procesoadministrativo.adm_vcDni')
            ->leftJoin('credencial','credencial.cred_iCodigo','=','procesoadministrativo.proadm_iCodigo')
            ->where('procesoadministrativo.profec_iCodigo',$fecha)
            ->where('proadm_iAsignacion',true);
        if($userRestrict){ $adm->where('procesoadministrativo.user_id',$userRestrict); }

        $alu = ProcesoAlumno::query()
            ->select([
                DB::raw("'Alumno' as tipo"),
                'procesoalumno.proalu_iCodigo as prodoc_id',
                DB::raw("CONCAT('alu-', procesoalumno.proalu_iCodigo) as row_key"),
                'procesoalumno.proalu_iCodigo as cred_codigo',
                'alumno.alu_vcCodigo as codigo',
                'alumno.alu_vcDni as dni',
                DB::raw("CONCAT(alumno.alu_vcPaterno,' ',alumno.alu_vcMaterno,' ',alumno.alu_vcNombre) as nombres"),
                'procesoalumno.expadm_iCodigo','procesoalumno.loc_iCodigo','procesoalumno.user_id',
                DB::raw("COALESCE(credencial.cred_iEntregado, procesoalumno.proalu_iCredencial) as entregado_flag")
            ])
            ->join('alumno','alumno.alu_vcCodigo','=','procesoalumno.alu_vcCodigo')
            ->leftJoin('credencial','credencial.cred_iCodigo','=','procesoalumno.proalu_iCodigo')
            ->where('procesoalumno.profec_iCodigo',$fecha)
            ->where('proalu_iAsignacion',true);
        if($userRestrict){ $alu->where('procesoalumno.user_id',$userRestrict); }

        // build union on underlying query builders
    $baseQuery = $doc->getQuery();
    $baseQuery->unionAll($adm->getQuery());
    $baseQuery->unionAll($alu->getQuery());
    // Limpia posibles orderBy heredados para evitar referenciar columnas inexistentes
    $baseQuery->orders = null;
    // Usamos un modelo fantasma para evitar que Eloquent intente aplicar el primaryKey original (prodoc_id)
    return EntregaCredencialRow::query()->fromSub($baseQuery,'u')->select('u.*');
    }

    protected function getTableQuery(): Builder
    {
        return $this->baseUnionQuery();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn()=> $this->getTableQuery())
                ->columns([
                TextColumn::make('tipo')->label('Tipo')->sortable(),
                TextColumn::make('codigo')->label('Código')->searchable(),
                TextColumn::make('dni')->label('DNI')->searchable(),
                TextColumn::make('nombres')->label('Nombre Completo')->searchable()->wrap(),
                TextColumn::make('cred_codigo')->label('# Credencial'),
                TextColumn::make('entregado_flag')
                    ->label('Entregado')
                    ->badge()
                    ->color(fn($state)=> $state ? 'success':'secondary')
                    ->formatStateUsing(fn($state)=> $state ? 'Sí':'No'),
            ])
            ->defaultSort('tipo')
            ->defaultSort('cred_codigo')
            ->actions([
                Action::make('toggleEntrega')
                    ->label(fn($record)=> $record->entregado_flag ? 'Anular' : 'Entregar')
                    ->color(fn($record)=> $record->entregado_flag ? 'danger':'success')
                    ->icon(fn($record)=> $record->entregado_flag ? 'heroicon-o-x-circle':'heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->action(function($record){
                        $new = ! (bool)$record->entregado_flag;
                        $this->handleEntregaToggle($record, $new);
                        $record->entregado_flag = $new; // refleja visualmente
                    }),
            ])
            ->filters([
                Filter::make('local')->form([
                    Select::make('loc')->label('Local')->options(fn()=> $this->getDistinctOptions('loc_iCodigo'))
                ])->query(function(Builder $q, array $data){
                    if(!$data['loc']) return $q;
                    return $q->where('loc_iCodigo',$data['loc']);
                }),
                Filter::make('cargo')->form([
                    Select::make('exp')->label('Cargo')->options(fn()=> $this->getDistinctOptions('expadm_iCodigo'))
                ])->query(function(Builder $q, array $data){
                    if(!$data['exp']) return $q;
                    return $q->where('expadm_iCodigo',$data['exp']);
                }),
            ])
            ->paginated(true)
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25,50,100]);
    }

    public function getTableRecordKey($record): string
    {
        // Usa row_key único (tipo-prefijo + id original) para evitar colisiones entre tablas distintas
    return (string)($record->row_key ?? $record->getKey());
    }

    protected function getDistinctOptions(string $column): array
    {
        $base = $this->baseUnionQuery();
        if(!$this->filters['proceso_fecha_id']) return [];
        return $base->clone()->select($column)->distinct()->orderBy($column)->pluck($column,$column)->toArray();
    }

    protected function handleEntregaToggle($record, $state): void
    {
        $user = Auth::user();
        if(!$user){ return; }
        $ip = request()->ip();
        $codigoCred = $record['cred_codigo'] ?? null;
        if(!$codigoCred) return;
        $cred = Credencial::firstOrNew(['cred_iCodigo'=>$codigoCred]);
        if($state){
            $cred->fill([
                'cred_vcDni' => $record['dni'] ?? null,
                'user_id' => $user->id,
                'cred_vcIp' => $ip,
                'cred_iEntregado' => true,
                'cred_dtFechaEntrega' => now(),
            ]);
            $cred->save();
        } else {
            if($cred->exists){
                $cred->cred_iEntregado = false;
                $cred->cred_dtFechaEntrega = null;
                $cred->save();
            }
        }
    // Reflejar estado en el record in-memory para que el toggle se actualice visualmente
    $record->entregado_flag = $cred->cred_iEntregado ?? false;
        Notification::make()->title($state ? 'Credencial entregada' : 'Entrega anulada')->success()->send();
    }
}
