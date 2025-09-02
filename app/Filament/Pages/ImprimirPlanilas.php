<?php

namespace App\Filament\Pages;

use App\Models\Proceso;
use App\Models\ProcesoFecha;
use App\Models\Tipo;
use App\Models\ProcesoDocente;
use App\Models\ProcesoAdministrativo;
use App\Models\ProcesoAlumno;
use App\Models\EntregaCredencialRow;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Models\Planilla;
use App\Models\PlanillaDocente;
use App\Models\PlanillaAdministrativo;
use App\Models\PlanillaAlumno;
use Filament\Notifications\Notification;

class ImprimirPlanilas extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Planillas';
    protected static ?string $title = 'Imprimir Planillas';
    protected static string $view = 'filament.pages.imprimir-planilas';

    public array $filters = [
        'proceso_id' => null,
        'proceso_fecha_id' => null,
        'tipo_id' => null,
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
                    ->options(fn() => Proceso::where('pro_iAbierto', true)->orderBy('pro_vcNombre')->pluck('pro_vcNombre', 'pro_iCodigo'))
                    ->reactive()
                    ->afterStateUpdated(function ($state) {
                        $this->filters['proceso_id'] = $state;
                        $this->filters['proceso_fecha_id'] = null;
                        $this->filters['tipo_id'] = null;
                        $this->form->fill($this->filters);
                    })
                    ->required(),
                Select::make('proceso_fecha_id')
                    ->label('Fecha Activa')
                    ->options(function () {
                        $pid = $this->filters['proceso_id'] ?? null;
                        if (!$pid) return [];
                        return ProcesoFecha::where('pro_iCodigo', $pid)
                            ->where('profec_iActivo', true)
                            ->orderBy('profec_dFecha')
                            ->pluck('profec_dFecha', 'profec_iCodigo');
                    })
                    ->reactive()
                    ->afterStateUpdated(function ($state) {
                        $this->filters['proceso_fecha_id'] = $state;
                        $this->filters['tipo_id'] = null;
                        $this->form->fill($this->filters);
                    })
                    ->required(),
                Select::make('tipo_id')
                    ->label('Tipo de Planilla')
                    ->options(fn() => Tipo::orderBy('tipo_vcNombre')->pluck('tipo_vcNombre', 'tipo_iCodigo'))
                    ->reactive()
                    ->afterStateUpdated(function ($state) {
                        $this->filters['tipo_id'] = $state;
                        $this->form->fill($this->filters);
                    })
                    ->required(),
            ])
            ->statePath('filters');
    }

    protected function baseUnionQuery(): Builder
    {
    $procesoId = $this->filters['proceso_id'] ?? null;
    $fecha = $this->filters['proceso_fecha_id'] ?? null;
        $tipoId = $this->filters['tipo_id'] ?? null;
        if (!$fecha || !$tipoId) {
            return ProcesoDocente::query()->whereRaw('1=0');
        }

        // DOCENTES
        $doc = ProcesoDocente::query()
            ->select([
                'procesodocente.prodoc_iCodigo as row_id',
                DB::raw("CONCAT('doc-', procesodocente.prodoc_iCodigo) as row_key"),
                'docente.doc_vcCodigo as codigo',
                'docente.doc_vcDni as dni',
                DB::raw("CONCAT(docente.doc_vcPaterno,' ',docente.doc_vcMaterno,' ',docente.doc_vcNombre) as nombres"),
                'procesodocente.loc_iCodigo',
                'lm.locma_iCodigo as locma_iCodigo',
                'lm.locma_vcNombre as local_nombre',
                'procesodocente.expadm_iCodigo',
                'em.expadmma_vcNombre as cargo_nombre',
                DB::raw('COALESCE(ea.expadm_fMonto, 0) as monto'),
            ])
            ->join('docente', 'docente.doc_vcCodigo', '=', 'procesodocente.doc_vcCodigo')
            ->join('locales as l', 'l.loc_iCodigo', '=', 'procesodocente.loc_iCodigo')
            ->join('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
            ->join('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'procesodocente.expadm_iCodigo')
            ->join('experienciaadmisionMaestro as em', 'em.expadmma_iCodigo', '=', 'ea.expadmma_iCodigo')
            ->where('procesodocente.profec_iCodigo', $fecha)
            ->where('procesodocente.prodoc_iAsignacion', true)
            ->where('docente.tipo_iCodigo', $tipoId)
            // Excluir docentes ya planillados en esta fecha/proceso
            ->whereNotExists(function ($q) use ($fecha, $procesoId) {
                $q->select(DB::raw('1'))
                    ->from('planillaDocente as pd')
                    ->join('planilla as p', 'p.pla_id', '=', 'pd.pla_id')
                    ->whereColumn('pd.doc_vcCodigo', 'docente.doc_vcCodigo')
                    ->where('p.profec_iCodigo', $fecha)
                    ->when($procesoId, fn($qq) => $qq->where('p.pro_iCodigo', $procesoId));
            });

        // ADMINISTRATIVOS
        $adm = ProcesoAdministrativo::query()
            ->select([
                'procesoadministrativo.proadm_iCodigo as row_id',
                DB::raw("CONCAT('adm-', procesoadministrativo.proadm_iCodigo) as row_key"),
                'administrativo.adm_vcCodigo as codigo',
                'administrativo.adm_vcDni as dni',
                'administrativo.adm_vcNombres as nombres',
                'procesoadministrativo.loc_iCodigo',
                'lm.locma_iCodigo as locma_iCodigo',
                'lm.locma_vcNombre as local_nombre',
                'procesoadministrativo.expadm_iCodigo',
                'em.expadmma_vcNombre as cargo_nombre',
                DB::raw('COALESCE(ea.expadm_fMonto, 0) as monto'),
            ])
            ->join('administrativo', 'administrativo.adm_vcDni', '=', 'procesoadministrativo.adm_vcDni')
            ->join('locales as l', 'l.loc_iCodigo', '=', 'procesoadministrativo.loc_iCodigo')
            ->join('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
            ->join('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'procesoadministrativo.expadm_iCodigo')
            ->join('experienciaadmisionMaestro as em', 'em.expadmma_iCodigo', '=', 'ea.expadmma_iCodigo')
            ->where('procesoadministrativo.profec_iCodigo', $fecha)
            ->where('procesoadministrativo.proadm_iAsignacion', true)
            ->where('administrativo.tipo_iCodigo', $tipoId)
            // Excluir administrativos ya planillados en esta fecha/proceso
            ->whereNotExists(function ($q) use ($fecha, $procesoId) {
                $q->select(DB::raw('1'))
                    ->from('planillaAdministrativo as pa')
                    ->join('planilla as p', 'p.pla_id', '=', 'pa.pla_id')
                    ->whereColumn('pa.adm_vcDni', 'administrativo.adm_vcDni')
                    ->where('p.profec_iCodigo', $fecha)
                    ->when($procesoId, fn($qq) => $qq->where('p.pro_iCodigo', $procesoId));
            });

        // ALUMNOS
        $alu = ProcesoAlumno::query()
            ->select([
                'procesoalumno.proalu_iCodigo as row_id',
                DB::raw("CONCAT('alu-', procesoalumno.proalu_iCodigo) as row_key"),
                'alumno.alu_vcCodigo as codigo',
                'alumno.alu_vcDni as dni',
                DB::raw("CONCAT(alumno.alu_vcPaterno,' ',alumno.alu_vcMaterno,' ',alumno.alu_vcNombre) as nombres"),
                'procesoalumno.loc_iCodigo',
                'lm.locma_iCodigo as locma_iCodigo',
                'lm.locma_vcNombre as local_nombre',
                'procesoalumno.expadm_iCodigo',
                'em.expadmma_vcNombre as cargo_nombre',
                DB::raw('COALESCE(ea.expadm_fMonto, 0) as monto'),
            ])
            ->join('alumno', 'alumno.alu_vcCodigo', '=', 'procesoalumno.alu_vcCodigo')
            ->join('locales as l', 'l.loc_iCodigo', '=', 'procesoalumno.loc_iCodigo')
            ->join('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
            ->join('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'procesoalumno.expadm_iCodigo')
            ->join('experienciaadmisionMaestro as em', 'em.expadmma_iCodigo', '=', 'ea.expadmma_iCodigo')
            ->where('procesoalumno.profec_iCodigo', $fecha)
            ->where('procesoalumno.proalu_iAsignacion', true)
            ->where('alumno.tipo_iCodigo', $tipoId)
            // Excluir alumnos ya planillados en esta fecha/proceso
            ->whereNotExists(function ($q) use ($fecha, $procesoId) {
                $q->select(DB::raw('1'))
                    ->from('planillaAlumno as pl')
                    ->join('planilla as p', 'p.pla_id', '=', 'pl.pla_id')
                    ->whereColumn('pl.alu_vcCodigo', 'alumno.alu_vcCodigo')
                    ->where('p.profec_iCodigo', $fecha)
                    ->when($procesoId, fn($qq) => $qq->where('p.pro_iCodigo', $procesoId));
            });

        // Unir subconsultas
        $baseQuery = $doc->getQuery();
        $baseQuery->unionAll($adm->getQuery());
        $baseQuery->unionAll($alu->getQuery());
        $baseQuery->orders = null; // limpiar orders heredados

        return EntregaCredencialRow::query()->fromSub($baseQuery, 'u')->select('u.*');
    }

    protected function getTableQuery(): Builder
    {
        return $this->baseUnionQuery();
    }

    // Aplica los filtros activos de la tabla al query unificado
    protected function getFilteredUnionQuery(): Builder
    {
        $query = $this->baseUnionQuery();
        try {
            // Intentar leer estado de filtros del componente de tabla
            $state = method_exists($this, 'getTableFiltersForm') ? ($this->getTableFiltersForm()?->getState() ?? []) : [];
            // Filtro de Locales
            $loc = $state['locales'] ?? [];
            $raw = $loc['loc_ids'] ?? [];
            $ids = collect(is_array($raw) ? $raw : [])
                ->flatMap(function ($v, $k) {
                    if (is_bool($v)) { return $v ? [$k] : []; }
                    return [$v];
                })
                ->filter(fn($v) => $v !== null && $v !== '' && $v !== false)
                ->map(fn($v) => (int) $v)
                ->unique()->values()->all();
            if (!empty($ids)) {
                $excluir = filter_var($loc['excluir'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if ($excluir) {
                    $query->whereNotIn('locma_iCodigo', $ids)
                          ->whereNotIn('loc_iCodigo', $ids);
                } else {
                    $query->where(function ($qq) use ($ids) {
                        $qq->whereIn('locma_iCodigo', $ids)
                           ->orWhereIn('loc_iCodigo', $ids);
                    });
                }
            }
            // Filtro de Cargo
            $cargo = $state['cargo']['exp'] ?? null;
            if (filled($cargo)) {
                $query->where('expadm_iCodigo', $cargo);
            }
        } catch (\Throwable $e) {
            // En caso de que la API de filtros no esté disponible, continuar sin filtros adicionales
        }
        return $query;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => $this->getTableQuery())
            ->columns([
                TextColumn::make('codigo')->label('Código')->searchable(),
                TextColumn::make('dni')->label('Documento')->searchable(),
                TextColumn::make('nombres')->label('Nombres completos')->searchable()->wrap(),
                TextColumn::make('local_nombre')->label('Local asignado')->sortable()->toggleable(),
                TextColumn::make('cargo_nombre')->label('Cargo')->sortable()->toggleable(),
                TextColumn::make('monto')->label('Monto')->money('PEN', divideBy: false)->sortable(),
            ])
            ->filters([
                Filter::make('locales')->form([
                    CheckboxList::make('loc_ids')
                        ->label('Locales (Maestro)')
                        ->options(fn() => $this->getDistinctOptions('locma_iCodigo', 'local_nombre'))
                        ->columns(2),
                    Toggle::make('excluir')
                        ->label('Excluir seleccionados')
                        ->inline(false)
                        ->default(false),
                ])->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) {
                    $raw = $data['loc_ids'] ?? [];
                    // Normalizar IDs desde distintas formas: ['12'=>true], ['12'=>'12'], ['12','15']
                    $ids = collect(is_array($raw) ? $raw : [])
                        ->flatMap(function ($v, $k) {
                            // Si el estado viene como mapa ['12' => true, '15' => false], usar la clave cuando es booleano true
                            if (is_bool($v)) {
                                return $v ? [$k] : [];
                            }
                            // Caso usual: arreglo de valores ['12', '15']
                            return [$v];
                        })
                        ->filter(fn($v) => $v !== null && $v !== '' && $v !== false)
                        ->map(fn($v) => (int) $v)
                        ->unique()
                        ->values()
                        ->all();
                    if (empty($ids)) {
                        return $query; // sin selección, no aplica filtro
                    }
                    $excluir = filter_var($data['excluir'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    // Aplicar filtro inclusivo/exclusivo simple (sin closure anidado)
                    if ($excluir) {
                        $query->whereNotIn('locma_iCodigo', $ids)
                              ->whereNotIn('loc_iCodigo', $ids);
                        return $query;
                    }
                    $query->where(function ($qq) use ($ids) {
                        $qq->whereIn('locma_iCodigo', $ids)
                           ->orWhereIn('loc_iCodigo', $ids);
                    });
                    return $query;
                }),
                Filter::make('cargo')->form([
                    Select::make('exp')->label('Cargo')->options(fn() => $this->getDistinctOptions('expadm_iCodigo', 'cargo_nombre')),
                ])->query(function (Builder $q, array $data) {
                    if (!filled($data['exp'] ?? null)) return $q;
                    return $q->where('expadm_iCodigo', $data['exp']);
                }),
            ])
            ->defaultSort('local_nombre')
            ->paginated(true)
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50, 100])
            ->bulkActions([
                BulkAction::make('generarSeleccionados')
                    ->label('Generar planilla (seleccionados)')
                    ->icon('heroicon-o-printer')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $this->generatePlanillaFromSelection($records)),
            ]);
    }

    protected function getDistinctOptions(string $idColumn, string $labelColumn): array
    {
        $base = $this->baseUnionQuery();
        if (!($this->filters['proceso_fecha_id'] ?? null) || !($this->filters['tipo_id'] ?? null)) return [];
        return $base->clone()
            ->select([$idColumn, $labelColumn])
            ->distinct()
            ->orderBy($labelColumn)
            ->pluck($labelColumn, $idColumn)
            ->toArray();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generarPlanilla')
                ->label('Generar Planilla')
                ->icon('heroicon-o-printer')
                ->disabled(fn() => !($this->filters['proceso_id'] && $this->filters['proceso_fecha_id'] && $this->filters['tipo_id']))
                ->action('generatePlanilla'),
        ];
    }

    public function generatePdf(?array $codigosFilter = null)
    {
        try {
            // Fuerza recarga de código en entornos con OPcache (desarrollo)
            if (function_exists('opcache_reset')) { @opcache_reset(); }
            $procesoId = (int)($this->filters['proceso_id'] ?? 0);
            $fechaId = (int)($this->filters['proceso_fecha_id'] ?? 0);
            $tipoId = (int)($this->filters['tipo_id'] ?? 0);
            if (!$procesoId || !$fechaId || !$tipoId) {
                Notification::make()
                    ->title('Complete los filtros')
                    ->warning()
                    ->body('Seleccione Proceso, Fecha activa y Tipo de planilla.')
                    ->send();
                return;
            }

            $user = Auth::user();
            $ip = request()->ip();

            // Datos de encabezado
            $proceso = Proceso::find($procesoId);
            $fecha = ProcesoFecha::find($fechaId);
            $tipo = Tipo::find($tipoId);
            $tituloPlanilla = $tipo?->tipo_vcNombrePlanilla
                ?: 'PLANILLA DE ASIGNACIÓN DE PERSONAL DOCENTE PERMANENTE, ASISTENCIA Y PAGO DE SUBVENCIÓN';

            // Construir dataset de docentes asignados agrupado por local y cargo
            $rows = ProcesoDocente::query()
            ->select([
                'procesodocente.prodoc_iCodigo as cred_numero',
                'docente.doc_vcCodigo as codigo',
                'docente.doc_vcDni as dni',
                DB::raw("CONCAT(docente.doc_vcPaterno,' ',docente.doc_vcMaterno,' ',docente.doc_vcNombre) as nombres"),
                'l.loc_iCodigo',
                'lm.locma_vcNombre as local_nombre',
                'ea.expadm_iCodigo',
                'em.expadmma_vcNombre as cargo_nombre',
                'ea.expadm_fMonto as monto',
            ])
            ->join('docente', 'docente.doc_vcCodigo', '=', 'procesodocente.doc_vcCodigo')
            ->join('locales as l', 'l.loc_iCodigo', '=', 'procesodocente.loc_iCodigo')
            ->join('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
            ->join('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'procesodocente.expadm_iCodigo')
            ->join('experienciaadmisionMaestro as em', 'em.expadmma_iCodigo', '=', 'ea.expadmma_iCodigo')
            ->where('procesodocente.profec_iCodigo', $fechaId)
            ->where('procesodocente.prodoc_iAsignacion', true)
            ->where('docente.tipo_iCodigo', $tipoId)
            ->when(!empty($codigosFilter), fn($q) => $q->whereIn('docente.doc_vcCodigo', $codigosFilter))
            ->orderBy('lm.locma_vcNombre')
            ->orderBy('em.expadmma_vcNombre')
            ->orderBy('nombres')
            ->get();
            if ($rows->isEmpty()) {
                Notification::make()->title('Sin datos para imprimir')->warning()->body('No hay docentes asignados con los filtros seleccionados.')->send();
                return;
            }

        // Agrupar por local, luego por cargo
        $porLocal = $rows->groupBy('loc_iCodigo');

    // Determinar últimos correlativos de planilla y de página final
    $lastNumero = (int) Planilla::where('pro_iCodigo', $procesoId)
            ->where('profec_iCodigo', $fechaId)
            ->where('tipo_iCodigo', $tipoId)
            ->max('pla_iNumero');
        $lastPaginaFin = (int) Planilla::where('pro_iCodigo', $procesoId)
            ->where('profec_iCodigo', $fechaId)
            ->where('tipo_iCodigo', $tipoId)
            ->max('pla_IPaginaFin');

    // Número base mostrado en el encabezado (primera planilla a generar)
    $numeroPlanillaBase = $lastNumero + 1;

    $pages = [];
    $persistLocals = [];

        // Recolectar páginas y persistir por local
        foreach ($porLocal as $locId => $coleccionLocal) {
            $numeroPlanillaLocal = $lastNumero + 1;
            $paginaInicioLocal = $lastPaginaFin + 1;
            $paginaActual = $paginaInicioLocal;

            $localNombre = optional($coleccionLocal->first())->local_nombre;

        // Páginas de detalle por cargo (numeración por cargo dentro de cada planilla/local)
            $porCargo = $coleccionLocal->groupBy('expadm_iCodigo');
            $pagesLocal = [];
            foreach ($porCargo as $expId => $coleccionCargo) {
                $cargoNombre = optional($coleccionCargo->first())->cargo_nombre;
                $montoCargo = (float) optional($coleccionCargo->first())->monto;
        $ordenCargo = 1; // numeración por cargo que continúa entre páginas
                $chunks = $coleccionCargo->values()->chunk(13); // 13 por hoja
        foreach ($chunks as $chunk) {
                    $page = [
                        'type' => 'detail',
                        'local_id' => $locId,
                        'local_nombre' => $localNombre,
                        'cargo_id' => $expId,
                        'cargo_nombre' => $cargoNombre,
                        'monto_cargo' => $montoCargo,
                        'planilla_numero' => $numeroPlanillaLocal,
                        'page_no' => $paginaActual,
            'rows' => $chunk->map(function ($r) use ($locId, $expId) {
                            return [
                                'codigo' => $r->codigo,
                                'dni' => $r->dni,
                                'nombres' => $r->nombres,
                                'local_nombre' => $r->local_nombre,
                                'cargo_nombre' => $r->cargo_nombre,
                                'monto' => (float) $r->monto,
                                'cred_numero' => $r->cred_numero,
                                'loc_id' => $locId,
                                'exp_id' => $expId,
                            ];
                        })->toArray(),
                    ];
                    $pages[] = $page;
                    $pagesLocal[] = $page;
                    $paginaActual++;
                }
            }

            // Página de resumen por local
            $resumen = $coleccionLocal
                ->groupBy('expadm_iCodigo')
                ->map(function ($g) {
                    $cant = $g->count();
                    $monto = (float) optional($g->first())->monto;
                    return [
                        'cargo_nombre' => optional($g->first())->cargo_nombre,
                        'cantidad' => $cant,
                        'monto' => $monto,
                        'subtotal' => $cant * $monto,
                    ];
                })
                ->values();
            $granTotal = $resumen->sum('subtotal');
            $summaryPage = [
                'type' => 'summary',
                'local_id' => $locId,
                'local_nombre' => $localNombre,
                'resumen' => $resumen->toArray(),
                'gran_total' => $granTotal,
                'planilla_numero' => $numeroPlanillaLocal,
                'page_no' => $paginaActual,
            ];
            $pages[] = $summaryPage;
            $pagesLocal[] = $summaryPage;
            $paginaFinLocal = $paginaActual;

            // Guardar payload para persistencia diferida
            $persistLocals[] = [
                'numero' => $numeroPlanillaLocal,
                'pagina_inicio' => $paginaInicioLocal,
                'pagina_fin' => $paginaFinLocal,
                'pages_local' => $pagesLocal,
            ];

            // Avanzar correlativos globales para el siguiente local
            $lastNumero = $numeroPlanillaLocal;
            $lastPaginaFin = $paginaFinLocal;
        }

        $totalPages = count($pages);

        // Renderizar PDF con DOMPDF
        $data = [
            'numero_planilla' => $numeroPlanillaBase,
            'proceso_nombre' => $proceso?->pro_vcNombre,
            'fecha_proceso' => optional($fecha)->profec_dFecha,
            'impresion_fecha' => now(),
            'titulo_planilla' => $tituloPlanilla,
            'pages' => $pages,
            'total_pages' => $totalPages,
        ];

    // Si existe al menos uno de los templates PDF, usar FPDI (para páginas sin template, se dibuja sin fondo pero con header/pie nuevos)
    $tplDirA = public_path('storage/templates_planilla');
    $tplDirB = public_path('storage/templates_planillas');
    // Buscar cualquier coincidencia tipo docentes*.pdf o resumen_doc*.pdf en ambos directorios
    $tplDetalle = $this->findTemplatePdf('docentes', [$tplDirA, $tplDirB]);
    $tplResumen = $this->findTemplatePdf('resumen_doc', [$tplDirA, $tplDirB]);

    // Generar contenido PDF primero; solo si tiene éxito, persistir
    $downloadName = 'planilla_docentes_'.$numeroPlanillaBase.'_'.now()->format('Ymd_His').'.pdf';
    if ($tplDetalle || $tplResumen) {
            $header = [
                'numero_planilla' => null,
                'proceso_nombre' => $proceso?->pro_vcNombre,
                'fecha_proceso' => optional($fecha)->profec_dFecha,
                'impresion_fecha' => now()->toDateTimeString(),
                'titulo_planilla' => $tituloPlanilla,
            ];
            $generator = new \App\Services\PlanillaPdfGenerator();
            $content = $generator->buildDocentesPdf($pages, $header, $tplDetalle, $tplResumen);
        } else {
            // Fallback a DOMPDF + backgrounds de imagen si no hay templates PDF
            $detailBgUrl = $this->findTemplateImageUrl('docentes');
            $summaryBgUrl = $this->findTemplateImageUrl('resumen_doc');
            $data['bg_detail_url'] = $detailBgUrl;
            $data['bg_summary_url'] = $summaryBgUrl;
            $pdf = PDF::loadView('pdf.planilla_docentes_compilado', $data)->setPaper('a4', 'landscape');
            $content = $pdf->output();
        }

        if (empty($content)) {
            Notification::make()->title('Error al generar PDF')->danger()->body('No se pudo generar contenido del PDF.')->send();
            return;
        }

        // Persistir por local (una planilla por local) en transacción
        DB::beginTransaction();
        try {
            $hasNumCol = Schema::hasColumn('planillaDocente', 'pladoc_iNumero');
            $hasPagCol = Schema::hasColumn('planillaDocente', 'pladoc_iPaginaFin');
            foreach ($persistLocals as $pl) {
                $planilla = Planilla::create([
                    'pro_iCodigo' => $procesoId,
                    'profec_iCodigo' => $fechaId,
                    'tipo_iCodigo' => $tipoId,
                    'pla_iNumero' => $pl['numero'],
                    'pla_iPaginaInicio' => $pl['pagina_inicio'],
                    'pla_IPaginaFin' => $pl['pagina_fin'],
                    'pla_iAdicional' => false,
                    'pla_iVersion' => 1,
                    'pla_bActivo' => true,
                    'user_id' => $user?->id,
                    'pla_vcIp' => $ip,
                ]);
                $orden = 1;
                foreach ($pl['pages_local'] as $pLocal) {
                    if (($pLocal['type'] ?? null) !== 'detail') continue;
                    foreach ($pLocal['rows'] as $row) {
                        $insert = [
                            'pla_id' => $planilla->pla_id,
                            'doc_vcCodigo' => $row['codigo'],
                            'pladoc_iImpreso' => 1,
                            'pladoc_iOrden' => $orden++,
                            'pladoc_dtFechaImpresion' => now(),
                            'user_id' => $user?->id,
                            'pladoc_vcIp' => $ip,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        if ($hasNumCol) { $insert['pladoc_iNumero'] = $pl['numero']; }
                        if ($hasPagCol) { $insert['pladoc_iPaginaFin'] = $pl['pagina_fin']; }
                        DB::table('planillaDocente')->insert($insert);
                    }
                }
            }
            DB::commit();
        } catch (\Throwable $txe) {
            DB::rollBack();
            Notification::make()->title('Error al guardar planillas')->danger()->body($txe->getMessage())->send();
            return;
        }

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $downloadName, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
        } catch (\Throwable $e) {
            \Log::error('Error generando planilla docentes', ['ex' => $e]);
            Notification::make()->title('Error al generar PDF')->danger()->body($e->getMessage())->send();
            return;
        }
    }

    public function generatePlanilla()
    {
        $procesoId = (int)($this->filters['proceso_id'] ?? 0);
        $fechaId = (int)($this->filters['proceso_fecha_id'] ?? 0);
        $tipoId = (int)($this->filters['tipo_id'] ?? 0);
        if (!$procesoId || !$fechaId || !$tipoId) {
            Notification::make()->title('Complete los filtros')->warning()->body('Seleccione Proceso, Fecha activa y Tipo de planilla.')->send();
            return;
        }
        try {
            // Tomar solo los registros actualmente visibles (filtros activos de la tabla)
            $rows = $this->getFilteredUnionQuery()->get(['row_key', 'codigo', 'dni']);
            if ($rows->isEmpty()) {
                Notification::make()->title('Sin datos para generar')->warning()->body('No hay registros con los filtros actuales.')->send();
                return;
            }

            // Determinar tipo por prefijo del row_key
            $prefixes = $rows->pluck('row_key')->map(function ($k) {
                return strpos($k, '-') !== false ? explode('-', $k, 2)[0] : null;
            })->unique()->values();
            if ($prefixes->count() !== 1) {
                Notification::make()->title('Selección ambigua')
                    ->danger()->body('Los filtros actuales mezclan tipos distintos. Ajuste el filtro de Tipo.')->send();
                return;
            }
            $prefix = $prefixes->first();
            if ($prefix === 'doc') {
                $codes = $rows->pluck('codigo')->filter()->unique()->values()->all();
                return $this->generatePdf($codes);
            }
            if ($prefix === 'adm') {
                $dnis = $rows->pluck('dni')->filter()->unique()->values()->all();
                return $this->generatePdfAdministrativos($dnis);
            }
            if ($prefix === 'alu') {
                $codes = $rows->pluck('codigo')->filter()->unique()->values()->all();
                return $this->generatePdfAlumnos($codes);
            }

            Notification::make()->title('Tipo desconocido')->danger()->body('No se pudo identificar el tipo de registros.')->send();
            return;
        } catch (\Throwable $e) {
            \Log::error('Error al decidir tipo de planilla', ['ex' => $e]);
            Notification::make()->title('Error')->danger()->body($e->getMessage())->send();
            return;
        }
    }

    public function generatePlanillaFromSelection(Collection $records)
    {
        if ($records->isEmpty()) {
            Notification::make()->title('Sin selección')->warning()->body('Seleccione al menos un registro.')->send();
            return;
        }

        // Determinar tipo por prefijo de row_key: doc-, adm-, alu-
        $keys = $records->pluck('row_key');
        $prefixes = $keys->map(fn($k) => strpos($k, '-') !== false ? explode('-', $k, 2)[0] : null)->unique()->values();
        if ($prefixes->count() !== 1) {
            Notification::make()->title('Selección inválida')->danger()->body('Seleccione registros de un solo tipo (Docentes, Administrativos/CAS, o Alumnos).')->send();
            return;
        }
        $prefix = $prefixes->first();

        try {
            if ($prefix === 'doc') {
                $codes = $records->pluck('codigo')->filter()->unique()->values()->all();
                return $this->generatePdf($codes);
            }
            if ($prefix === 'adm') {
                $dnis = $records->pluck('dni')->filter()->unique()->values()->all();
                return $this->generatePdfAdministrativos($dnis);
            }
            if ($prefix === 'alu') {
                $codes = $records->pluck('codigo')->filter()->unique()->values()->all();
                return $this->generatePdfAlumnos($codes);
            }
            Notification::make()->title('Tipo desconocido')->danger()->body('No se pudo identificar el tipo de registros seleccionados.')->send();
            return;
        } catch (\Throwable $e) {
            \Log::error('Error en generación por selección', ['ex' => $e]);
            Notification::make()->title('Error')->danger()->body($e->getMessage())->send();
            return;
        }
    }

    public function generatePdfAdministrativos(?array $dniFilter = null)
    {
        try {
            if (function_exists('opcache_reset')) { @opcache_reset(); }
            $procesoId = (int)($this->filters['proceso_id'] ?? 0);
            $fechaId = (int)($this->filters['proceso_fecha_id'] ?? 0);
            $tipoId = (int)($this->filters['tipo_id'] ?? 0);
            if (!$procesoId || !$fechaId || !$tipoId) {
                Notification::make()->title('Complete los filtros')->warning()->body('Seleccione Proceso, Fecha activa y Tipo de planilla.')->send();
                return;
            }

            $user = Auth::user();
            $ip = request()->ip();

            $proceso = Proceso::find($procesoId);
            $fecha = ProcesoFecha::find($fechaId);
            $tipo = Tipo::find($tipoId);
            $tituloPlanilla = $tipo?->tipo_vcNombrePlanilla ?: 'PLANILLA DE ASIGNACIÓN DE PERSONAL ADMINISTRATIVO, ASISTENCIA Y PAGO DE SUBVENCIÓN';
            $tipoNombre = mb_strtolower($tipo?->tipo_vcNombre ?? '');
            $isTerceroCas = str_contains($tipoNombre, 'tercero') || str_contains($tipoNombre, 'cas');

            // Dataset de administrativos asignados
            $rows = ProcesoAdministrativo::query()
                ->select([
                    'procesoadministrativo.proadm_iCodigo as cred_numero',
                    'administrativo.adm_vcCodigo as codigo',
                    'administrativo.adm_vcDni as dni',
                    'administrativo.adm_vcNombres as nombres',
                    'l.loc_iCodigo',
                    'lm.locma_vcNombre as local_nombre',
                    'ea.expadm_iCodigo',
                    'em.expadmma_vcNombre as cargo_nombre',
                    'ea.expadm_fMonto as monto',
                ])
                ->join('administrativo', 'administrativo.adm_vcDni', '=', 'procesoadministrativo.adm_vcDni')
                ->join('locales as l', 'l.loc_iCodigo', '=', 'procesoadministrativo.loc_iCodigo')
                ->join('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
                ->join('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'procesoadministrativo.expadm_iCodigo')
                ->join('experienciaadmisionMaestro as em', 'em.expadmma_iCodigo', '=', 'ea.expadmma_iCodigo')
                ->where('procesoadministrativo.profec_iCodigo', $fechaId)
                ->where('procesoadministrativo.proadm_iAsignacion', true)
                ->where('administrativo.tipo_iCodigo', $tipoId)
                ->when(!empty($dniFilter), fn($q) => $q->whereIn('administrativo.adm_vcDni', $dniFilter))
                ->orderBy('lm.locma_vcNombre')
                ->orderBy('em.expadmma_vcNombre')
                ->orderBy('nombres')
                ->get();
            if ($rows->isEmpty()) {
                Notification::make()->title('Sin datos para imprimir')->warning()->body('No hay administrativos asignados con los filtros seleccionados.')->send();
                return;
            }

            $porLocal = $rows->groupBy('loc_iCodigo');

            $lastNumero = (int) Planilla::where('pro_iCodigo', $procesoId)
                ->where('profec_iCodigo', $fechaId)
                ->where('tipo_iCodigo', $tipoId)
                ->max('pla_iNumero');
            $lastPaginaFin = (int) Planilla::where('pro_iCodigo', $procesoId)
                ->where('profec_iCodigo', $fechaId)
                ->where('tipo_iCodigo', $tipoId)
                ->max('pla_IPaginaFin');

            $numeroPlanillaBase = $lastNumero + 1;

            $pages = [];
            $persistLocals = [];

            foreach ($porLocal as $locId => $coleccionLocal) {
                $numeroPlanillaLocal = $lastNumero + 1;
                $paginaInicioLocal = $lastPaginaFin + 1;
                $paginaActual = $paginaInicioLocal;
                $localNombre = optional($coleccionLocal->first())->local_nombre;
                // Numeración por local para Tercero/CAS
                $ordenLocal = 1;

                $porCargo = $coleccionLocal->groupBy('expadm_iCodigo');
                $pagesLocal = [];
        foreach ($porCargo as $expId => $coleccionCargo) {
                    $cargoNombre = optional($coleccionCargo->first())->cargo_nombre;
                    $montoCargo = (float) optional($coleccionCargo->first())->monto;
                    // Asegurar orden por nombres dentro de cada local (y cargo)
                    $coleccionCargo = $coleccionCargo->sortBy('nombres')->values();
                    $ordenCargo = 1; // por cargo (para tipos distintos a Tercero/CAS)
            $chunks = $coleccionCargo->values()->chunk(13);
                    foreach ($chunks as $chunk) {
                        $page = [
                            'type' => 'detail',
                            'local_id' => $locId,
                            'local_nombre' => $localNombre,
                            'cargo_id' => $expId,
                            'cargo_nombre' => $cargoNombre,
                            'monto_cargo' => $montoCargo,
                            'planilla_numero' => $numeroPlanillaLocal,
                            'page_no' => $paginaActual,
                            'rows' => $chunk->map(function ($r) use (&$ordenCargo, &$ordenLocal, $isTerceroCas) {
                                return [
                                    // Para Tercero/CAS, numeración por local; caso contrario, por cargo
                                    'orden' => $isTerceroCas ? $ordenLocal++ : $ordenCargo++,
                                    'codigo' => $r->codigo,
                                    'dni' => $r->dni,
                                    'nombres' => $r->nombres,
                                    'local_nombre' => $r->local_nombre,
                                    'cargo_nombre' => $r->cargo_nombre,
                                    'monto' => (float) $r->monto,
                                    'cred_numero' => $r->cred_numero,
                                ];
                            })->toArray(),
                        ];
                        $pages[] = $page;
                        $pagesLocal[] = $page;
                        $paginaActual++;
                    }
                }

                // Para Tercero/CAS no se genera página de resumen
                if (!$isTerceroCas) {
                    $resumen = $coleccionLocal
                    ->groupBy('expadm_iCodigo')
                    ->map(function ($g) {
                        $cant = $g->count();
                        $monto = (float) optional($g->first())->monto;
                        return [
                            'cargo_nombre' => optional($g->first())->cargo_nombre,
                            'cantidad' => $cant,
                            'monto' => $monto,
                            'subtotal' => $cant * $monto,
                        ];
                    })
                    ->values();
                    $granTotal = $resumen->sum('subtotal');
                    $summaryPage = [
                        'type' => 'summary',
                        'local_id' => $locId,
                        'local_nombre' => $localNombre,
                        'resumen' => $resumen->toArray(),
                        'gran_total' => $granTotal,
                        'planilla_numero' => $numeroPlanillaLocal,
                        'page_no' => $paginaActual,
                    ];
                    $pages[] = $summaryPage;
                    $pagesLocal[] = $summaryPage;
                    $paginaActual++;
                }
                $paginaFinLocal = $paginaActual - 1;

                // Diferir persistencia hasta construir el PDF exitosamente
                $persistLocals[] = [
                    'loc_id' => $locId,
                    'numero' => $numeroPlanillaLocal,
                    'pagina_inicio' => $paginaInicioLocal,
                    'pagina_fin' => $paginaFinLocal,
                    'pages_local' => $pagesLocal, // para detalles (orden y dni)
                ];

                $lastNumero = $numeroPlanillaLocal;
                $lastPaginaFin = $paginaFinLocal;
            }

            $totalPages = count($pages);

            $data = [
                'numero_planilla' => $numeroPlanillaBase,
                'proceso_nombre' => $proceso?->pro_vcNombre,
                'fecha_proceso' => optional($fecha)->profec_dFecha,
                'impresion_fecha' => now(),
                'titulo_planilla' => $tituloPlanilla,
                'pages' => $pages,
                'total_pages' => $totalPages,
                'es_tercero_cas' => $isTerceroCas,
            ];

            $tplDirA = public_path('storage/templates_planilla');
            $tplDirB = public_path('storage/templates_planillas');
            $tplDetalle = $this->findTemplatePdf('docentes', [$tplDirA, $tplDirB]);
            $tplResumen = $this->findTemplatePdf('resumen_doc', [$tplDirA, $tplDirB]);

            // Construir contenido PDF primero (FPDI si aplica, DOMPDF si Tercero/CAS u opcional)
            $content = null;
            $downloadName = 'planilla_administrativos_'.$numeroPlanillaBase.'_'.now()->format('Ymd_His').'.pdf';
            if (($tplDetalle || $tplResumen) && !$isTerceroCas) {
                $header = [
                    'numero_planilla' => null,
                    'proceso_nombre' => $proceso?->pro_vcNombre,
                    'fecha_proceso' => optional($fecha)->profec_dFecha,
                    'impresion_fecha' => now()->toDateTimeString(),
                    'titulo_planilla' => $tituloPlanilla,
                ];
                $generator = new \App\Services\PlanillaPdfGenerator();
                $content = $generator->buildDocentesPdf($pages, $header, $tplDetalle, $tplResumen);
            } else {
                $detailBgUrl = $this->findTemplateImageUrl('docentes');
                $summaryBgUrl = $this->findTemplateImageUrl('resumen_doc');
                $data['bg_detail_url'] = $detailBgUrl;
                $data['bg_summary_url'] = $summaryBgUrl;
                $pdf = PDF::loadView('pdf.planilla_docentes_compilado', $data)->setPaper('a4', 'landscape');
                $content = $pdf->output();
            }

            // Si el contenido no se generó, abortar sin persistir
            if (empty($content)) {
                Notification::make()->title('Error al generar PDF')->danger()->body('No se pudo generar contenido del PDF.')->send();
                return;
            }

            // Si el contenido se generó correctamente, persistir por local dentro de una transacción
            DB::beginTransaction();
            try {
                foreach ($persistLocals as $item) {
                    $planilla = Planilla::create([
                        'pro_iCodigo' => $procesoId,
                        'profec_iCodigo' => $fechaId,
                        'tipo_iCodigo' => $tipoId,
                        'pla_iNumero' => $item['numero'],
                        'pla_iPaginaInicio' => $item['pagina_inicio'],
                        'pla_IPaginaFin' => $item['pagina_fin'],
                        'pla_iAdicional' => false,
                        'pla_iVersion' => 1,
                        'pla_bActivo' => true,
                        'user_id' => $user?->id,
                        'pla_vcIp' => $ip,
                    ]);
                    $orden = 1;
                    foreach ($item['pages_local'] as $pLocal) {
                        if (($pLocal['type'] ?? null) !== 'detail') continue;
                        foreach ($pLocal['rows'] as $row) {
                            PlanillaAdministrativo::create([
                                'pla_id' => $planilla->pla_id,
                                'adm_vcDni' => $row['dni'],
                                'plaadm_iImpreso' => 1,
                                'plaadm_iOrden' => $orden++,
                                'plaadm_dtFechaImpresion' => now(),
                                'user_id' => $user?->id,
                                'plaadm_vcIp' => $ip,
                            ]);
                        }
                    }
                }
                DB::commit();
            } catch (\Throwable $tx) {
                DB::rollBack();
                throw $tx;
            }

            return response()->streamDownload(function () use ($content) {
                echo $content;
            }, $downloadName, [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error generando planilla administrativos', ['ex' => $e]);
            Notification::make()->title('Error al generar PDF')->danger()->body($e->getMessage())->send();
            return;
        }
    }

    public function generatePdfAlumnos(?array $codigosFilter = null)
    {
        try {
            if (function_exists('opcache_reset')) { @opcache_reset(); }
            $procesoId = (int)($this->filters['proceso_id'] ?? 0);
            $fechaId = (int)($this->filters['proceso_fecha_id'] ?? 0);
            $tipoId = (int)($this->filters['tipo_id'] ?? 0);
            if (!$procesoId || !$fechaId || !$tipoId) {
                Notification::make()->title('Complete los filtros')->warning()->body('Seleccione Proceso, Fecha activa y Tipo de planilla.')->send();
                return;
            }

            $user = Auth::user();
            $ip = request()->ip();

            $proceso = Proceso::find($procesoId);
            $fecha = ProcesoFecha::find($fechaId);
            $tipo = Tipo::find($tipoId);
            $tituloPlanilla = $tipo?->tipo_vcNombrePlanilla ?: 'PLANILLA DE ASIGNACIÓN DE PERSONAL ALUMNO, ASISTENCIA Y PAGO DE SUBVENCIÓN';

            // Dataset de alumnos asignados
            $rows = ProcesoAlumno::query()
                ->select([
                    'procesoalumno.proalu_iCodigo as cred_numero',
                    'alumno.alu_vcCodigo as codigo',
                    'alumno.alu_vcDni as dni',
                    DB::raw("CONCAT(alumno.alu_vcPaterno,' ',alumno.alu_vcMaterno,' ',alumno.alu_vcNombre) as nombres"),
                    'l.loc_iCodigo',
                    'lm.locma_vcNombre as local_nombre',
                    'ea.expadm_iCodigo',
                    'em.expadmma_vcNombre as cargo_nombre',
                    'ea.expadm_fMonto as monto',
                ])
                ->join('alumno', 'alumno.alu_vcCodigo', '=', 'procesoalumno.alu_vcCodigo')
                ->join('locales as l', 'l.loc_iCodigo', '=', 'procesoalumno.loc_iCodigo')
                ->join('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
                ->join('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'procesoalumno.expadm_iCodigo')
                ->join('experienciaadmisionMaestro as em', 'em.expadmma_iCodigo', '=', 'ea.expadmma_iCodigo')
                ->where('procesoalumno.profec_iCodigo', $fechaId)
                ->where('procesoalumno.proalu_iAsignacion', true)
                ->where('alumno.tipo_iCodigo', $tipoId)
                ->when(!empty($codigosFilter), fn($q) => $q->whereIn('alumno.alu_vcCodigo', $codigosFilter))
                ->orderBy('lm.locma_vcNombre')
                ->orderBy('em.expadmma_vcNombre')
                ->orderBy('nombres')
                ->get();
            if ($rows->isEmpty()) {
                Notification::make()->title('Sin datos para imprimir')->warning()->body('No hay alumnos asignados con los filtros seleccionados.')->send();
                return;
            }

            $porLocal = $rows->groupBy('loc_iCodigo');

            $lastNumero = (int) Planilla::where('pro_iCodigo', $procesoId)
                ->where('profec_iCodigo', $fechaId)
                ->where('tipo_iCodigo', $tipoId)
                ->max('pla_iNumero');
            $lastPaginaFin = (int) Planilla::where('pro_iCodigo', $procesoId)
                ->where('profec_iCodigo', $fechaId)
                ->where('tipo_iCodigo', $tipoId)
                ->max('pla_IPaginaFin');

            $numeroPlanillaBase = $lastNumero + 1;

            $pages = [];
            $persistLocals = [];

            foreach ($porLocal as $locId => $coleccionLocal) {
                $numeroPlanillaLocal = $lastNumero + 1;
                $paginaInicioLocal = $lastPaginaFin + 1;
                $paginaActual = $paginaInicioLocal;
                $localNombre = optional($coleccionLocal->first())->local_nombre;
                $ordenLocal = 1;

                $porCargo = $coleccionLocal->groupBy('expadm_iCodigo');
                $pagesLocal = [];
                foreach ($porCargo as $expId => $coleccionCargo) {
                    $cargoNombre = optional($coleccionCargo->first())->cargo_nombre;
                    $montoCargo = (float) optional($coleccionCargo->first())->monto;
                    $coleccionCargo = $coleccionCargo->sortBy('nombres')->values();
                    $chunks = $coleccionCargo->values()->chunk(13);
                    foreach ($chunks as $chunk) {
                        $page = [
                            'type' => 'detail',
                            'local_id' => $locId,
                            'local_nombre' => $localNombre,
                            'cargo_id' => $expId,
                            'cargo_nombre' => $cargoNombre,
                            'monto_cargo' => $montoCargo,
                            'planilla_numero' => $numeroPlanillaLocal,
                            'page_no' => $paginaActual,
                            'rows' => $chunk->map(function ($r) use (&$ordenLocal) {
                                return [
                                    'orden' => $ordenLocal++,
                                    'codigo' => $r->codigo,
                                    'dni' => $r->dni,
                                    'nombres' => $r->nombres,
                                    'local_nombre' => $r->local_nombre,
                                    'cargo_nombre' => $r->cargo_nombre,
                                    'monto' => (float) $r->monto,
                                    'cred_numero' => $r->cred_numero,
                                ];
                            })->toArray(),
                        ];
                        $pages[] = $page;
                        $pagesLocal[] = $page;
                        $paginaActual++;
                    }
                }

                // Sin página de resumen para alumnos (similar a Tercero/CAS)
                $paginaFinLocal = $paginaActual - 1;

                // Diferir persistencia hasta construir el PDF exitosamente
                $persistLocals[] = [
                    'loc_id' => $locId,
                    'numero' => $numeroPlanillaLocal,
                    'pagina_inicio' => $paginaInicioLocal,
                    'pagina_fin' => $paginaFinLocal,
                    'pages_local' => $pagesLocal, // para detalles (orden y código)
                ];

                $lastNumero = $numeroPlanillaLocal;
                $lastPaginaFin = $paginaFinLocal;
            }

            $totalPages = count($pages);

            $data = [
                'numero_planilla' => $numeroPlanillaBase,
                'proceso_nombre' => $proceso?->pro_vcNombre,
                'fecha_proceso' => optional($fecha)->profec_dFecha,
                'impresion_fecha' => now(),
                'titulo_planilla' => $tituloPlanilla,
                'pages' => $pages,
                'total_pages' => $totalPages,
                'es_alumno' => true,
            ];

            // Forzar DOMPDF para columnas condicionales: generar contenido primero
            $detailBgUrl = $this->findTemplateImageUrl('docentes');
            $data['bg_detail_url'] = $detailBgUrl;
            $data['bg_summary_url'] = null; // sin resumen
            $pdf = PDF::loadView('pdf.planilla_docentes_compilado', $data)->setPaper('a4', 'landscape');
            $content = $pdf->output();

            // Persistir en transacción tras generación exitosa
            $downloadName = 'planilla_alumnos_'.$numeroPlanillaBase.'_'.now()->format('Ymd_His').'.pdf';
            DB::beginTransaction();
            try {
                foreach ($persistLocals as $item) {
                    $planilla = Planilla::create([
                        'pro_iCodigo' => $procesoId,
                        'profec_iCodigo' => $fechaId,
                        'tipo_iCodigo' => $tipoId,
                        'pla_iNumero' => $item['numero'],
                        'pla_iPaginaInicio' => $item['pagina_inicio'],
                        'pla_IPaginaFin' => $item['pagina_fin'],
                        'pla_iAdicional' => false,
                        'pla_iVersion' => 1,
                        'pla_bActivo' => true,
                        'user_id' => $user?->id,
                        'pla_vcIp' => $ip,
                    ]);
                    $orden = 1;
                    foreach ($item['pages_local'] as $pLocal) {
                        if (($pLocal['type'] ?? null) !== 'detail') continue;
                        foreach ($pLocal['rows'] as $row) {
                            PlanillaAlumno::create([
                                'pla_id' => $planilla->pla_id,
                                'alu_vcCodigo' => $row['codigo'],
                                'plaalu_iImpreso' => 1,
                                'plaalu_iOrden' => $orden++,
                                'plaalu_dtFechaImpresion' => now(),
                                'user_id' => $user?->id,
                                'plaalu_vcIp' => $ip,
                            ]);
                        }
                    }
                }
                DB::commit();
            } catch (\Throwable $tx) {
                DB::rollBack();
                throw $tx;
            }

            return response()->streamDownload(function () use ($content) { echo $content; }, $downloadName, [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error generando planilla alumnos', ['ex' => $e]);
            Notification::make()->title('Error al generar PDF')->danger()->body($e->getMessage())->send();
            return;
        }
    }

    private function findTemplateImageUrl(string $baseName): ?string
    {
        $dirs = [
            public_path('storage/templates_planilla'),
            public_path('storage/templates_planillas'),
        ];
        $exts = ['png','jpg','jpeg'];
        foreach ($dirs as $dir) {
            foreach ($exts as $ext) {
                $path = $dir.DIRECTORY_SEPARATOR.$baseName.'.'.$ext;
                if (is_file($path)) {
                    $norm = str_replace('\\','/',$path);
                    return 'file:///'.ltrim($norm,'/');
                }
            }
        }
        return null;
    }

    private function findTemplatePdf(string $baseName, array $dirs): ?string
    {
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) continue;
            $pattern = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$baseName.'*.pdf';
            $matches = glob($pattern);
            if (!empty($matches)) {
                // Devuelve el primero en orden alfabético
                sort($matches);
                return $matches[0];
            }
        }
        return null;
    }
}
