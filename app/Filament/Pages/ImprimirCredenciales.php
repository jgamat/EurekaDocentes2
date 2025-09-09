<?php

namespace App\Filament\Pages;

use App\Models\ProcesoDocente;
use App\Models\ProcesoAdministrativo;
use App\Models\ProcesoAlumno;
use App\Models\ProcesoFecha;
use App\Models\Proceso;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle as ToggleField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class ImprimirCredenciales extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-printer';
    protected static string $view = 'filament.pages.imprimir-credenciales';
    protected static ?string $title = 'Impresión de Credenciales';
    protected static ?string $navigationGroup = 'Credenciales';

    // Estado del formulario reactivo
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('proceso_id')
                    ->label('Proceso')
                    ->options(Proceso::query()->where('pro_iAbierto', true)->orderBy('pro_vcNombre')->pluck('pro_vcNombre','pro_iCodigo'))
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated(function(){
                        $this->data['proceso_fecha_id'] = null; $this->clearFiltersContext();
                    })
                    ->required(),
                Select::make('proceso_fecha_id')
                    ->label('Fecha')
                    ->options(function(){
                        $pid = data_get($this->data,'proceso_id');
                        if(!$pid) return [];
                        return ProcesoFecha::where('pro_iCodigo',$pid)
                            ->where('profec_iActivo', true)
                            ->orderBy('profec_dFecha')
                            ->get()
                            ->mapWithKeys(fn($f)=> [ $f->profec_iCodigo => ($f->profec_dFecha ? $f->profec_dFecha : ('ID '.$f->profec_iCodigo)) ])
                            ->toArray();
                    })
                    ->reactive()
                    ->afterStateUpdated(fn()=> $this->clearFiltersContext())
                    ->required(),
                Select::make('tipo_personal_id')
                    ->label('Tipo')
                    ->options([1=>'Docentes',2=>'Administrativos',3=>'Alumnos'])
                    ->reactive()
                    ->afterStateUpdated(fn()=> $this->clearFiltersContext())
                    ->required(),
                Section::make('Ajustes de impresión')
                    ->collapsible()
                    ->collapsed()
                    ->hidden()
                    ->description('Slot inicial y offsets milimétricos; se puede contraer para más espacio de tabla.')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 5,
                        ])->schema([
                            Select::make('inicio_slot')
                                ->label('Inicio')
                                ->options([1=>1,2=>2,3=>3,4=>4])
                                ->default(1)
                                ->reactive()
                                ->columnSpan(1)
                                ->extraAttributes(['class'=>'max-w-[80px]']),
                            TextInput::make('offset_x_front')->label('X Frente')->numeric()->default(0)->reactive()->suffix('mm')->columnSpan(1)->extraAttributes(['class'=>'max-w-[90px]']),
                            TextInput::make('offset_y_front')->label('Y Frente')->numeric()->default(0)->reactive()->suffix('mm')->columnSpan(1)->extraAttributes(['class'=>'max-w-[90px]']),
                            TextInput::make('offset_x_back')->label('X Reverso')->numeric()->default(0)->reactive()->suffix('mm')->columnSpan(1)->extraAttributes(['class'=>'max-w-[100px]']),
                            TextInput::make('offset_y_back')->label('Y Reverso')->numeric()->default(0)->reactive()->suffix('mm')->columnSpan(1)->extraAttributes(['class'=>'max-w-[100px]']),
                        ]),
                        ToggleField::make('debug_grid')->label('Ver rejilla')->default(false)->reactive()->inline(false),
                    ]),
            ])
            ->statePath('data');
    }
    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => $this->buildBaseQuery())
            ->striped()
            ->searchable()
            ->searchPlaceholder('Buscar por código, DNI o nombre')
            ->headerActions([
                TableAction::make('imprimir_pendientes')
                    ->label('Imprimir Pendientes')
                    ->icon('heroicon-o-printer')
                    ->visible(fn () => $this->isOnlyPendingActive())
                    ->action(function(){
                        $tipo = $this->getTipoSeleccionado();
                        if (!$this->canUserPrintType($tipo)) {
                            Notification::make()->title('Acción no permitida')->body('No tiene permisos para imprimir este tipo.')->danger()->send();
                            return;
                        }
                        $records = $this->getPendingQuery()->get();
                        return $this->handleImpresionForRecords($records);
                    }),
            ])
            ->columns([
                // Docentes
                TextColumn::make('docente.doc_vcCodigo')->label('Código')->sortable()->searchable()->visible(fn () => (int) (data_get($this->data,'tipo_personal_id') ?? 0) === 1)->extraAttributes(['class'=>'whitespace-nowrap w-20']),
                TextColumn::make('docente.doc_vcDni')->label('DNI')->sortable()->searchable()->visible(fn () => (int) (data_get($this->data,'tipo_personal_id') ?? 0) === 1)->extraAttributes(['class'=>'whitespace-nowrap w-24']),
                TextColumn::make('docente.nombre_completo')
                    ->label('Nombre')
                    ->searchable(['docente.doc_vcNombre', 'docente.doc_vcPaterno', 'docente.doc_vcMaterno'])
                    ->wrap()
                    ->limit(40)
                    ->visible(fn () => (int) (data_get($this->data,'tipo_personal_id') ?? 0) === 1)
                    ->extraAttributes(['class'=>'max-w-[240px]']),
                // Administrativos
                TextColumn::make('administrativo.adm_vcCodigo')->label('Código')->sortable()->searchable()->visible(fn () => (int) (data_get($this->data,'tipo_personal_id') ?? 0) === 2)->extraAttributes(['class'=>'whitespace-nowrap w-20']),
                TextColumn::make('administrativo.adm_vcDni')->label('DNI')->sortable()->searchable()->visible(fn () => (int) (data_get($this->data,'tipo_personal_id') ?? 0) === 2)->extraAttributes(['class'=>'whitespace-nowrap w-24']),
                TextColumn::make('administrativo.adm_vcNombres')->label('Nombre')->searchable()->wrap()->limit(40)->visible(fn () => (int) (data_get($this->data,'tipo_personal_id') ?? 0) === 2)->extraAttributes(['class'=>'max-w-[240px]']),

                // Comunes
                TextColumn::make('experienciaAdmision.maestro.expadmma_vcNombre')->label('Cargo')->wrap()->limit(35)->extraAttributes(['class'=>'max-w-[200px]']),
                TextColumn::make('local.localesMaestro.locma_vcNombre')->label('Local')->wrap()->limit(25)->extraAttributes(['class'=>'max-w-[160px]']),

                // Toggles por tipo
                ToggleColumn::make('prodoc_iCredencial')
                    ->label('Impresa')
                    ->visible(fn () => (int) (data_get($this->data,'tipo_personal_id') ?? 0) === 1)
                    ->afterStateUpdated(function ($record, $state) {
                        if (!$this->canUserPrintType(1)) {
                            Notification::make()->title('Acción no permitida')->body('No puede marcar como impresa para este tipo.')->danger()->send();
                            $record->refresh();
                            return;
                        }
                        $record->prodoc_dtFechaImpresion = $state ? now() : null;
                        $record->save();
                    }),
                ToggleColumn::make('proadm_iCredencial')
                    ->label('Impresa')
                    ->visible(fn () => (int) (data_get($this->data,'tipo_personal_id') ?? 0) === 2)
                    ->afterStateUpdated(function ($record, $state) {
                        if (!$this->canUserPrintType(2)) {
                            Notification::make()->title('Acción no permitida')->body('No puede marcar como impresa para este tipo.')->danger()->send();
                            $record->refresh();
                            return;
                        }
                        $record->proadm_dtFechaImpresion = $state ? now() : null;
                        $record->save();
                    }),
                ToggleColumn::make('proalu_iCredencial')
                    ->label('Impresa')
                    ->visible(fn () => (int) (data_get($this->data,'tipo_personal_id') ?? 0) === 3)
                    ->afterStateUpdated(function ($record, $state) {
                        if (!$this->canUserPrintType(3)) {
                            Notification::make()->title('Acción no permitida')->body('No puede marcar como impresa para este tipo.')->danger()->send();
                            $record->refresh();
                            return;
                        }
                        $record->proalu_dtFechaImpresion = $state ? now() : null;
                        $record->save();
                    }),
            ])
            ->filters([
                \Filament\Tables\Filters\Filter::make('solo_pendientes')
                    ->label('Solo Pendientes')
                    ->query(function(Builder $query){
                        $tipo = $this->getTipoSeleccionado();
                        return match($tipo){
                            1 => $query->where(function($q){ $q->whereNull('prodoc_iCredencial')->orWhere('prodoc_iCredencial', false); }),
                            2 => $query->where(function($q){ $q->whereNull('proadm_iCredencial')->orWhere('proadm_iCredencial', false); }),
                            3 => $query->where(function($q){ $q->whereNull('proalu_iCredencial')->orWhere('proalu_iCredencial', false); }),
                            default => $query,
                        };
                    }),
                \Filament\Tables\Filters\Filter::make('local_filter')
                    ->label('Local')
                    ->form([
                        \Filament\Forms\Components\Select::make('loc_iCodigo')
                            ->label('Local')
                            ->options(fn () => $this->getLocalesOptions())
                            ->searchable()
                            ->live(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['loc_iCodigo'] ?? null;
                        if (!filled($value)) return $query;
                        return $query->where('loc_iCodigo', $value);
                    }),
                \Filament\Tables\Filters\Filter::make('cargo_filter')
                    ->label('Cargo')
                    ->form([
                        \Filament\Forms\Components\Select::make('expadm_iCodigo')
                            ->label('Cargo')
                            ->options(fn () => $this->getCargosOptions())
                            ->searchable()
                            ->live(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['expadm_iCodigo'] ?? null;
                        if (!filled($value)) return $query;
                        return $query->where('expadm_iCodigo', $value);
                    }),
            ])
            ->bulkActions([
                BulkAction::make('imprimir')
                    ->label('Imprimir Seleccionados')
                    ->icon('heroicon-o-printer')
                    ->action(function (Collection $records) {
                        $tipo = $this->getTipoSeleccionado();
                        if (!$this->canUserPrintType($tipo)) {
                            Notification::make()->title('Acción no permitida')->body('No tiene permisos para imprimir este tipo.')->danger()->send();
                            return;
                        }
                        if ($this->isOnlyPendingActive()) {
                            $records = $this->getPendingQuery()->get();
                        }
                        return $this->handleImpresionForRecords($records);
                    }),
                BulkAction::make('reimprimir')
                    ->label('Forzar Reimpresión')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->action(function(Collection $records){
                        $tipo = $this->getTipoSeleccionado();
                        if (!$this->canUserPrintType($tipo)) {
                            Notification::make()->title('Acción no permitida')->body('No tiene permisos para reimprimir este tipo.')->danger()->send();
                            return;
                        }
                        foreach($records as $record){
                            if ($record instanceof ProcesoDocente){
                                $record->prodoc_iCredencial = true; $record->prodoc_dtFechaImpresion = now();
                            } elseif ($record instanceof ProcesoAdministrativo){
                                $record->proadm_iCredencial = true; $record->proadm_dtFechaImpresion = now();
                            } elseif ($record instanceof ProcesoAlumno){
                                $record->proalu_iCredencial = true; $record->proalu_dtFechaImpresion = now();
                            }
                            $record->save();
                        }
                        $this->dispatch('$refresh');
                        Notification::make()->title('Reimpresión forzada')->body('Registros actualizados. Vuelva a presionar Imprimir para el PDF.')->success()->send();
                    }),
            ]);
    }

    protected function getTipoSeleccionado(): int
    {
        return (int) (data_get($this->data,'tipo_personal_id') ?? 0);
    }

    protected function getTipoTablaLabel(): string
    {
        return match ($this->getTipoSeleccionado()) {
            1 => 'Docentes para impresión',
            2 => 'Administrativos para impresión',
            3 => 'Alumnos para impresión',
            default => 'Registros para impresión',
        };
    }

    protected function getImpresionCount(): int
    {
    $procesoFechaId = (int) (data_get($this->data,'proceso_fecha_id') ?? 0);
        $tipo = $this->getTipoSeleccionado();
        if (!$procesoFechaId || !in_array($tipo, [1, 2, 3], true)) {
            return 0;
        }

        $user = Auth::user();
        switch ($tipo) {
            case 1:
                $query = ProcesoDocente::query()
                    ->where('profec_iCodigo', $procesoFechaId)
                    ->where('prodoc_iAsignacion', true);
                break;
            case 2:
                $query = ProcesoAdministrativo::query()
                    ->where('profec_iCodigo', $procesoFechaId)
                    ->where('proadm_iAsignacion', true);
                break;
            case 3:
                $query = ProcesoAlumno::query()
                    ->where('profec_iCodigo', $procesoFechaId)
                    ->where('proalu_iAsignacion', true);
                break;
            default:
                return 0;
        }

        // Solo Economía e Info ven todo; resto restringido por roles del usuario asignador
        if (!$user->hasAnyRole(['Economia', 'Info'])) {
            $currentRoles = $user->getRoleNames();
            $query->whereHas('usuario.roles', fn ($q) => $q->whereIn('name', $currentRoles));
        }

        return (int) $query->count();
    }

    protected function canUserPrintType(int $tipo): bool
    {
        $user = Auth::user();
        // Economía e Info pueden imprimir cualquier credencial
        if ($user->hasAnyRole(['Economia', 'Info'])) {
            return true;
        }
        return match ($tipo) {
            1 => $user->hasAnyRole(['Direccion', 'DocentesLocales', 'ControlCalidad', 'Oprad', 'EconomiaDocentes', 'super_admin']),
            2 => $user->hasAnyRole(['EconomiaAdministrativos','super_admin']),
            3 => $user->hasAnyRole(['EconomiaAlumnos']),
            default => false,
        };
    }

    // Construye la misma consulta base que usa la tabla, sin filtros adicionales, para derivar opciones únicas
    protected function buildBaseQuery(): Builder
    {
        $user = Auth::user();
    $procesoFechaId = data_get($this->data,'proceso_fecha_id') ?? null;
    $tipoPersonalId = (int) (data_get($this->data,'tipo_personal_id') ?? 0);

        if (empty($procesoFechaId) || !in_array($tipoPersonalId, [1, 2, 3], true) || !$this->canUserPrintType($tipoPersonalId)) {
            return ProcesoDocente::query()->whereRaw('1 = 0');
        }

        switch ($tipoPersonalId) {
            case 1:
                $query = ProcesoDocente::query()
                    ->with(['local.localesMaestro', 'experienciaAdmision.maestro'])
                    ->where('profec_iCodigo', $procesoFechaId)
                    ->where('prodoc_iAsignacion', true);
                break;
            case 2:
                $query = ProcesoAdministrativo::query()
                    ->with(['local.localesMaestro', 'experienciaAdmision.maestro'])
                    ->where('profec_iCodigo', $procesoFechaId)
                    ->where('proadm_iAsignacion', true);
                break;
            case 3:
                $query = ProcesoAlumno::query()
                    ->with(['local.localesMaestro', 'experienciaAdmision.maestro'])
                    ->where('profec_iCodigo', $procesoFechaId)
                    ->where('proalu_iAsignacion', true);
                break;
        }

        if (!$user->hasAnyRole(['Economia', 'Info'])) {
            $currentRoles = $user->getRoleNames();
            $query->whereHas('usuario.roles', fn ($q) => $q->whereIn('name', $currentRoles));
        }

        return $query;
    }

    protected function getLocalesOptions(): array
    {
        $rows = $this->buildBaseQuery()->get();
        if ($rows->isEmpty()) return [];
        $options = [];
        foreach ($rows as $row) {
            $id = $row->loc_iCodigo;
            if (!$id) continue;
            $options[$id] = $row->local?->localesMaestro?->locma_vcNombre ?? (string) $id;
        }
        // Uniques por clave ya se mantienen al sobrescribir; ordenar por nombre
        asort($options);
        return $options;
    }

    protected function getCargosOptions(): array
    {
        $rows = $this->buildBaseQuery()->get();
        if ($rows->isEmpty()) return [];
        $options = [];
        foreach ($rows as $row) {
            $id = $row->expadm_iCodigo;
            if (!$id) continue;
            $options[$id] = $row->experienciaAdmision?->maestro?->expadmma_vcNombre ?? (string) $id;
        }
        asort($options);
        return $options;
    }

    protected function clearFiltersContext(): void
    {
        // Limpia filtros y fuerza refresco de tabla al cambiar contexto (Proceso/Fecha/Tipo)
        if (property_exists($this, 'tableFilters')) {
            $this->tableFilters = [];
        }
        if (property_exists($this, 'tableFiltersFormData')) {
            $this->tableFiltersFormData = [];
        }
        // Buscar método interno de Filament si existe
        if (method_exists($this, 'resetTableFiltersForm')) {
            $this->resetTableFiltersForm([]);
        }
        $this->dispatch('$refresh');
    }

    // Determina si el único filtro activo es "Solo Pendientes"
    protected function isOnlyPendingActive(): bool
    {
        $filters = [];
        if (property_exists($this, 'tableFilters')) {
            $filters = $this->tableFilters ?? [];
        } elseif (property_exists($this, 'tableFiltersFormData')) {
            $filters = $this->tableFiltersFormData ?? [];
        }
        if (!is_array($filters)) $filters = [];

        // Normaliza claves con valor lleno
        $active = [];
        foreach ($filters as $key => $val) {
            if (is_array($val)) {
                if (array_filter($val, fn ($v) => $v !== null && $v !== '' && $v !== false) !== []) {
                    $active[] = $key;
                }
            } elseif ($val) {
                $active[] = $key;
            }
        }
        // true si solo está activo 'solo_pendientes'
        return count($active) === 1 && in_array('solo_pendientes', $active, true);
    }

    // Construye una consulta que devuelve TODOS los pendientes con el contexto actual (proceso/fecha/tipo) y respetando Local/Cargo si están activos
    protected function getPendingQuery(): Builder
    {
        $base = $this->buildBaseQuery();
        $tipo = $this->getTipoSeleccionado();

        // Aplica condición de pendientes
        $base = match ($tipo) {
            1 => $base->where(function ($q) { $q->whereNull('prodoc_iCredencial')->orWhere('prodoc_iCredencial', false); }),
            2 => $base->where(function ($q) { $q->whereNull('proadm_iCredencial')->orWhere('proadm_iCredencial', false); }),
            3 => $base->where(function ($q) { $q->whereNull('proalu_iCredencial')->orWhere('proalu_iCredencial', false); }),
            default => $base,
        };

        // Si además el usuario dejó activos Local o Cargo, respetarlos también
        $filters = [];
        if (property_exists($this, 'tableFiltersFormData')) {
            $filters = $this->tableFiltersFormData ?? [];
        }
        if (is_array($filters)) {
            $loc = data_get($filters, 'local_filter.loc_iCodigo');
            if ($loc) { $base->where('loc_iCodigo', $loc); }
            $cargo = data_get($filters, 'cargo_filter.expadm_iCodigo');
            if ($cargo) { $base->where('expadm_iCodigo', $cargo); }
        }
        return $base;
    }

    // Extrae la lógica de impresión para reutilizar en acciones
    protected function handleImpresionForRecords(Collection $records)
    {
        if ($records->isEmpty()) {
            Notification::make()->title('Sin selección')->body('Seleccione al menos un registro.')->warning()->send();
            return null;
        }

        if (class_exists('Barryvdh\\Debugbar\\Facades\\Debugbar')) {
            try { \Barryvdh\Debugbar\Facades\Debugbar::disable(); } catch (\Throwable $e) {}
        }

        $batchSize = 40;
        $total = $records->count();
        $more = $total > $batchSize ? $total - $batchSize : 0;
        $processRecords = $more ? $records->take($batchSize) : $records;

        $items = [];
        foreach ($processRecords as $record) {
            $dni = null; $codigo = null; $nombres = null; $cargo = null; $local = null; $flagCol = null; $fechaCol = null; $credencialNumero = null;
            if ($record instanceof ProcesoDocente) { $dni = $record->docente?->doc_vcDni; $codigo = $record->docente?->doc_vcCodigo; $nombres = $record->docente?->nombre_completo; $flagCol='prodoc_iCredencial'; $fechaCol='prodoc_dtFechaImpresion'; $credencialNumero=$record->prodoc_iCodigo ?? null; }
            elseif ($record instanceof ProcesoAdministrativo) { $dni = $record->administrativo?->adm_vcDni; $codigo = $record->administrativo?->adm_vcCodigo; $nombres = $record->administrativo?->adm_vcNombres; $flagCol='proadm_iCredencial'; $fechaCol='proadm_dtFechaImpresion'; $credencialNumero=$record->proadm_iCodigo ?? null; }
            elseif ($record instanceof ProcesoAlumno) { $dni = $record->alumno?->alu_vcDni; $codigo = $record->alumno?->alu_vcCodigo; $nombres = $record->alumno?->nombre_completo; $flagCol='proalu_iCredencial'; $fechaCol='proalu_dtFechaImpresion'; $credencialNumero=$record->proalu_iCodigo ?? null; }
            $cargo = $record->experienciaAdmision?->maestro?->expadmma_vcNombre; $local = $record->local?->localesMaestro?->locma_vcNombre;
            $cargoLine1=$cargo; $cargoLine2=null; if($cargo){ $max=28; if(mb_strlen($cargo)>$max){ $words=preg_split('/\s+/u',$cargo); $l1=''; foreach($words as $w){ if(mb_strlen(trim($l1.' '.$w)) <= $max){ $l1=trim($l1.' '.$w);} else { $rest=trim(mb_substr($cargo, mb_strlen($l1))); $cargoLine1=$l1; $cargoLine2=$rest; break; } } } }
            $dniFile = $dni ? $dni.'.jpg' : null; $publicFoto = $dniFile && file_exists(public_path('storage/fotos/'.$dniFile)) ? public_path('storage/fotos/'.$dniFile) : public_path('storage/fotos/sinfoto.jpg');
            $items[] = [
                'dni'=>$dni,'codigo'=>$codigo,'nombres'=>$nombres,
                'nombres_line1'=>(function($name){ if(!$name)return null; $max=28; if(mb_strlen($name)<= $max) return $name; $words=preg_split('/\s+/u',$name); $l1=''; $rest=''; foreach($words as $w){ if(mb_strlen(trim($l1.' '.$w)) <= $max){ $l1=trim($l1.' '.$w);} else { $rest=trim(mb_substr($name, mb_strlen($l1))); break; } } return $l1 ?: mb_substr($name,0,$max);} )($nombres),
                'nombres_line2'=>(function($name){ if(!$name)return null; $max=28; if(mb_strlen($name)<= $max) return null; $words=preg_split('/\s+/u',$name); $l1=''; $rest=''; foreach($words as $w){ if(mb_strlen(trim($l1.' '.$w)) <= $max){ $l1=trim($l1.' '.$w);} else { $rest=trim(mb_substr($name, mb_strlen($l1))); break; } } return $rest ?: null;} )($nombres),
                'cargo'=>$cargo,'cargo_line1'=>$cargoLine1,'cargo_line2'=>$cargoLine2,'local'=>$local,
                'foto_path'=>$publicFoto,'flagCol'=>$flagCol,'fechaCol'=>$fechaCol,'credencial'=>$credencialNumero,'model'=>$record,
            ];
        }

        $inicio = (int) (data_get($this->data,'inicio_slot') ?? 1); $inicio = $inicio<1?1:($inicio>4?4:$inicio);
        if ($inicio>1) { $placeholders = array_fill(0,$inicio-1,null); $items = array_merge($placeholders,$items); }
        $pages = array_chunk($items,4);
        $fecha = ProcesoFecha::find($procesoFechaId = (data_get($this->data,'proceso_fecha_id') ?? null));
        $anvPath = $fecha?->profec_vcUrlAnverso ? storage_path('app/public/'.$fecha->profec_vcUrlAnverso) : public_path('storage/templates/anverso.jpg');
        $revPath = $fecha?->profec_vcUrlReverso ? storage_path('app/public/'.$fecha->profec_vcUrlReverso) : public_path('storage/templates/reverso.jpg');
        $sessionKeyAnv='plantilla_anv_'.$procesoFechaId; $sessionKeyRev='plantilla_rev_'.$procesoFechaId;
        $anvB64=session()->get($sessionKeyAnv); $revB64=session()->get($sessionKeyRev);
        if(!$anvB64 && file_exists($anvPath)){ $anvB64=@base64_encode(file_get_contents($anvPath)); session()->put($sessionKeyAnv,$anvB64);} 
        if(!$revB64 && file_exists($revPath)){ $revB64=@base64_encode(file_get_contents($revPath)); session()->put($sessionKeyRev,$revB64);} 
        $html = view('credenciales.bulk',[ 'pages'=>$pages,'generado'=>now(),'anverso'=>$anvB64,'reverso'=>$revB64,'offsets'=>[
            'front'=>['x'=>(float)(data_get($this->data,'offset_x_front') ?? 0),'y'=>(float)(data_get($this->data,'offset_y_front') ?? 0)],
            'back'=>['x'=>(float)(data_get($this->data,'offset_x_back') ?? 0),'y'=>(float)(data_get($this->data,'offset_y_back') ?? 0)],
        ],'debug'=>(bool)(data_get($this->data,'debug_grid') ?? false),])->render();
    $pdf = Pdf::loadHTML($html)->setPaper('a4','portrait'); $pdfBinary = $pdf->output();

        $updated=0; foreach($items as $it){ if(!$it || !isset($it['model'])) continue; $it['model']->{$it['flagCol']}=true; $it['model']->{$it['fechaCol']}=now(); if(property_exists($it['model'],'user_idImpresion') || \Schema::hasColumn($it['model']->getTable(),'user_idImpresion')){ $it['model']->user_idImpresion=auth()->id(); } if(property_exists($it['model'],'IpImpresion') || \Schema::hasColumn($it['model']->getTable(),'IpImpresion')){ $it['model']->IpImpresion=request()->ip(); } $it['model']->save(); $updated++; }

        $this->dispatch('$refresh');
        $msg = 'Se imprimieron '.$updated.' credenciales.'; if($more){ $msg .= ' Quedan '.$more.' por imprimir (repita la acción).'; }

        // Guardar el PDF en disco público y abrir en una nueva pestaña
        $dir = 'credenciales/tmp';
        $filename = 'credenciales_lote_'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(3)).'.pdf';
        $path = $dir.'/'.$filename;
        try {
            Storage::disk('public')->put($path, $pdfBinary);
            $url = Storage::url($path);
            // Dispara un evento de navegador para abrir en una nueva pestaña
            $this->dispatch('open-pdf', url: $url);
            Notification::make()->title('PDF generado')->body($msg)->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error al generar PDF')->body($e->getMessage())->danger()->send();
        }
        return null;
    }

    public function clearPlantillaCache(): void
    {
    $procesoFechaId = (int) (data_get($this->data,'proceso_fecha_id') ?? 0);
        if ($procesoFechaId) {
            $keys = ['plantilla_anv_' . $procesoFechaId, 'plantilla_rev_' . $procesoFechaId];
            foreach ($keys as $k) {
                session()->forget($k);
            }
            Notification::make()
                ->title('Caché limpiada')
                ->body('Se limpiaron las plantillas base64 para la fecha seleccionada.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Sin fecha seleccionada')
                ->body('Seleccione una fecha antes de limpiar la caché de plantillas.')
                ->warning()
                ->send();
        }
        $this->dispatch('$refresh');
    }

    public function clearPlantillaCacheAllActive(): void
    {
        $procesoId = (int) (data_get($this->data, 'proceso_id') ?? $this->proceso_id ?? 0);
        if (!$procesoId) {
            Notification::make()->title('Sin proceso seleccionado')->body('Seleccione un proceso para limpiar caché de todas sus fechas activas.')->warning()->send();
            return;
        }
        $fechas = ProcesoFecha::where('pro_iCodigo', $procesoId)->where('profec_iActivo', true)->pluck('profec_iCodigo');
        $total = 0;
        foreach ($fechas as $fid) {
            foreach (['plantilla_anv_' . $fid, 'plantilla_rev_' . $fid] as $k) {
                if (session()->has($k)) {
                    session()->forget($k); $total++;
                }
            }
        }
        Notification::make()
            ->title('Caché global limpiada')
            ->body('Entradas eliminadas: ' . $total)
            ->success()
            ->send();
        $this->dispatch('$refresh');
    }
}
