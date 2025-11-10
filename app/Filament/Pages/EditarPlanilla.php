<?php

namespace App\Filament\Pages;

use App\Models\Proceso;
use App\Models\ProcesoFecha;
use App\Models\Tipo;
use App\Models\Planilla;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action as RowAction;
use Filament\Actions\Action; // header action
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Str;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use App\Support\CurrentContext;
use App\Support\Traits\UsesGlobalContext;

class EditarPlanilla extends Page implements Forms\Contracts\HasForms, HasTable
{
    use Forms\Concerns\InteractsWithForms, Tables\Concerns\InteractsWithTable;
    use HasPageShield;
    use UsesGlobalContext;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Planillas';
    protected static ?string $title = 'Reimprimir / Editar Planilla';

    protected static string $view = 'filament.pages.editar-planilla';

    public array $filters = [
        'proceso_id' => null,
        'proceso_fecha_id' => null,
        'tipo_id' => null,
        'planilla_id' => null,
    ];

    // Sincronizar con contexto global
    protected $listeners = ['context-changed' => 'onGlobalContextChanged'];

    public function mount(): void
    {
        $ctx = app(CurrentContext::class);
        $ctx->ensureLoaded();
        $ctx->ensureValid();
        $this->filters['proceso_id'] = $ctx->procesoId();
        $this->filters['proceso_fecha_id'] = $ctx->fechaId();
        $this->form->fill($this->filters);
    }

    public function onGlobalContextChanged(): void
    {
        $ctx = app(CurrentContext::class);
        $this->filters['proceso_id'] = $ctx->procesoId();
        $this->filters['proceso_fecha_id'] = $ctx->fechaId();
        // Al cambiar el contexto, reiniciar selección de tipo/planilla
        $this->filters['tipo_id'] = null;
        $this->filters['planilla_id'] = null;
        $this->form->fill($this->filters);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Mostrar fecha actual como solo lectura; proceso/fecha vendrán del contexto global
                $this->fechaActualPlaceholder('filters.proceso_fecha_id'),
                Select::make('proceso_id')
                    ->label('Proceso activo')
                    ->options(fn () => Proceso::where('pro_iAbierto', true)->orderBy('pro_vcNombre')->pluck('pro_vcNombre', 'pro_iCodigo'))
                    ->hidden()
                    ->required(),
                Select::make('proceso_fecha_id')
                    ->label('Fecha abierta')
                    ->options(function () {
                        $pid = $this->filters['proceso_id'] ?? null;
                        if (!$pid) return [];
                        return ProcesoFecha::where('pro_iCodigo', $pid)
                            ->where('profec_iActivo', true)
                            ->orderBy('profec_dFecha')
                            ->pluck('profec_dFecha', 'profec_iCodigo');
                    })
                    ->hidden()
                    ->required(),
                Select::make('tipo_id')
                    ->label('Tipo de planilla')
                    ->options(fn () => Tipo::orderBy('tipo_vcNombre')->pluck('tipo_vcNombre', 'tipo_iCodigo'))
                    ->reactive()
                    ->afterStateUpdated(function ($state) {
                        $this->filters['tipo_id'] = $state;
                        $this->filters['planilla_id'] = null;
                        $this->form->fill($this->filters);
                    })
                    ->required(),
                Select::make('planilla_id')
                    ->label('Número de planilla')
                    ->options(function () {
                        $pid = $this->filters['proceso_id'] ?? null;
                        $fid = $this->filters['proceso_fecha_id'] ?? null;
                        $tid = $this->filters['tipo_id'] ?? null;
                        if (!$pid || !$fid || !$tid) return [];
                        return Planilla::query()
                            ->where('pro_iCodigo', $pid)
                            ->where('profec_iCodigo', $fid)
                            ->where('tipo_iCodigo', $tid)
                            ->orderBy('pla_iNumero')
                            ->get()
                            ->mapWithKeys(function ($p) {
                                $label = 'N° '.$p->pla_iNumero.' (p. '.$p->pla_iPaginaInicio.'-'.$p->pla_IPaginaFin.')';
                                return [$p->pla_id => $label];
                            })->toArray();
                    })
                    ->reactive()
                    ->afterStateUpdated(function ($state) {
                        $this->filters['planilla_id'] = $state;
                        $this->form->fill($this->filters);
                    })
                    ->required(),
            ])
            ->statePath('filters');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reimprimir')
                ->label('Reimprimir planilla')
                ->icon('heroicon-o-printer')
                ->visible(fn () => ($this->filters['planilla_id'] && $this->filters['proceso_id'] && $this->filters['proceso_fecha_id'] && $this->filters['tipo_id']))
                ->url(fn () => $this->filters['planilla_id'] ? route('planillas.reimprimir', ['pla' => $this->filters['planilla_id']]) : '#')
                ->openUrlInNewTab(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->columns([
                TextColumn::make('orden')->label('N°')->sortable(),
                TextColumn::make('codigo')->label('Código')->searchable(),
                TextColumn::make('dni')->label('Documento')->searchable(),
                TextColumn::make('nombres')->label('Nombres')->wrap()->searchable(),
                TextColumn::make('local_nombre')->label('Local')->sortable(),
                TextColumn::make('cargo_nombre')->label('Cargo')->sortable(),
                TextColumn::make('monto')->label('Monto')->money('PEN', divideBy: false)->sortable(),
            ])
            ->actions([
                RowAction::make('eliminar')
                    ->label('Eliminar')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->action(function ($record) {
                        $plaId = (int) ($this->filters['planilla_id'] ?? 0);
                        $tipoId = (int) ($this->filters['tipo_id'] ?? 0);
                        if (!$plaId || !$tipoId) return;

                        // Detect type name to choose detail table
                        $tipoNombre = optional(Tipo::find($tipoId))->tipo_vcNombre ?? '';
                        $tipoNombreLower = Str::lower($tipoNombre);
                        try {
                            if (Str::contains($tipoNombreLower, ['docente'])) {
                                DB::table('planillaDocente')
                                    ->where('pla_id', $plaId)
                                    ->where('doc_vcCodigo', $record->codigo)
                                    ->delete();
                                // Resequenciar orden
                                $rows = DB::table('planillaDocente')
                                    ->where('pla_id', $plaId)
                                    ->orderBy('pladoc_iOrden')
                                    ->get(['doc_vcCodigo']);
                                $i = 1;
                                foreach ($rows as $r) {
                                    DB::table('planillaDocente')
                                        ->where('pla_id', $plaId)
                                        ->where('doc_vcCodigo', $r->doc_vcCodigo)
                                        ->update(['pladoc_iOrden' => $i++]);
                                }
                            } elseif (Str::contains($tipoNombreLower, ['admin', 'tercero', 'cas'])) {
                                DB::table('planillaAdministrativo')
                                    ->where('pla_id', $plaId)
                                    ->where('adm_vcDni', $record->dni)
                                    ->delete();
                                $rows = DB::table('planillaAdministrativo')
                                    ->where('pla_id', $plaId)
                                    ->orderBy('plaadm_iOrden')
                                    ->get(['adm_vcDni']);
                                $i = 1;
                                foreach ($rows as $r) {
                                    DB::table('planillaAdministrativo')
                                        ->where('pla_id', $plaId)
                                        ->where('adm_vcDni', $r->adm_vcDni)
                                        ->update(['plaadm_iOrden' => $i++]);
                                }
                            } elseif (Str::contains($tipoNombreLower, ['alumno'])) {
                                DB::table('planillaAlumno')
                                    ->where('pla_id', $plaId)
                                    ->where('alu_vcCodigo', $record->codigo)
                                    ->delete();
                                $rows = DB::table('planillaAlumno')
                                    ->where('pla_id', $plaId)
                                    ->orderBy('plaalu_iOrden')
                                    ->get(['alu_vcCodigo']);
                                $i = 1;
                                foreach ($rows as $r) {
                                    DB::table('planillaAlumno')
                                        ->where('pla_id', $plaId)
                                        ->where('alu_vcCodigo', $r->alu_vcCodigo)
                                        ->update(['plaalu_iOrden' => $i++]);
                                }
                            }
                            Notification::make()->title('Eliminado')->success()->body('Registro eliminado de la planilla.')->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Error')->danger()->body($e->getMessage())->send();
                        }
                    }),
            ])
            ->defaultSort('orden')
            ->paginated(false);
    }

    protected function getTableQuery(): EloquentBuilder
    {
        $plaId = (int) ($this->filters['planilla_id'] ?? 0);
        $tipoId = (int) ($this->filters['tipo_id'] ?? 0);
        $fechaId = (int) ($this->filters['proceso_fecha_id'] ?? 0);
        if (!$plaId || !$tipoId || !$fechaId) {
            // Use a harmless subquery that yields no rows; include row_key to tolerate persisted sorts
            $empty = DB::query()->selectRaw('1 as orden, NULL as row_key, NULL as codigo, NULL as dni, NULL as nombres, NULL as local_nombre, NULL as cargo_nombre, 0 as monto')->whereRaw('1=0');
            return \App\Models\EntregaCredencialRow::query()->fromSub($empty, 'u')->select('u.*');
        }

        $tipoNombre = optional(Tipo::find($tipoId))->tipo_vcNombre ?? '';
        $tipoNombreLower = Str::lower($tipoNombre);

        if (Str::contains($tipoNombreLower, ['docente'])) {
            $q = DB::table('planillaDocente as pd')
                ->join('planilla as p', 'p.pla_id', '=', 'pd.pla_id')
                ->join('docente as d', 'd.doc_vcCodigo', '=', 'pd.doc_vcCodigo')
                ->join('procesodocente as prd', function ($j) use ($fechaId) {
                    $j->on('prd.doc_vcCodigo', '=', 'd.doc_vcCodigo')
                      ->where('prd.profec_iCodigo', '=', $fechaId);
                })
                ->join('locales as l', 'l.loc_iCodigo', '=', 'prd.loc_iCodigo')
                ->join('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
                ->join('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'prd.expadm_iCodigo')
                ->join('experienciaadmisionMaestro as em', 'em.expadmma_iCodigo', '=', 'ea.expadmma_iCodigo')
                ->where('pd.pla_id', $plaId)
                ->orderBy('pd.pladoc_iOrden')
                ->selectRaw("CONCAT('doc-', d.doc_vcCodigo) as row_key, pd.pladoc_iOrden as orden, d.doc_vcCodigo as codigo, d.doc_vcDni as dni, CONCAT(d.doc_vcPaterno, ' ', d.doc_vcMaterno, ' ', d.doc_vcNombre) as nombres, lm.locma_vcNombre as local_nombre, em.expadmma_vcNombre as cargo_nombre, COALESCE(ea.expadm_fMonto,0) as monto");
        } elseif (Str::contains($tipoNombreLower, ['admin', 'tercero', 'cas'])) {
            $q = DB::table('planillaAdministrativo as pa')
                ->join('planilla as p', 'p.pla_id', '=', 'pa.pla_id')
                ->join('administrativo as a', 'a.adm_vcDni', '=', 'pa.adm_vcDni')
                ->join('procesoadministrativo as pra', function ($j) use ($fechaId) {
                    $j->on('pra.adm_vcDni', '=', 'a.adm_vcDni')
                      ->where('pra.profec_iCodigo', '=', $fechaId);
                })
                ->join('locales as l', 'l.loc_iCodigo', '=', 'pra.loc_iCodigo')
                ->join('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
                ->join('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'pra.expadm_iCodigo')
                ->join('experienciaadmisionMaestro as em', 'em.expadmma_iCodigo', '=', 'ea.expadmma_iCodigo')
                ->where('pa.pla_id', $plaId)
                ->orderBy('pa.plaadm_iOrden')
                ->selectRaw("CONCAT('adm-', a.adm_vcDni) as row_key, pa.plaadm_iOrden as orden, a.adm_vcCodigo as codigo, a.adm_vcDni as dni, a.adm_vcNombres as nombres, lm.locma_vcNombre as local_nombre, em.expadmma_vcNombre as cargo_nombre, COALESCE(ea.expadm_fMonto,0) as monto");
        } else { // alumnos
            $q = DB::table('planillaAlumno as pl')
                ->join('planilla as p', 'p.pla_id', '=', 'pl.pla_id')
                ->join('alumno as al', 'al.alu_vcCodigo', '=', 'pl.alu_vcCodigo')
                ->join('procesoalumno as pral', function ($j) use ($fechaId) {
                    $j->on('pral.alu_vcCodigo', '=', 'al.alu_vcCodigo')
                      ->where('pral.profec_iCodigo', '=', $fechaId);
                })
                ->join('locales as l', 'l.loc_iCodigo', '=', 'pral.loc_iCodigo')
                ->join('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
                ->join('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'pral.expadm_iCodigo')
                ->join('experienciaadmisionMaestro as em', 'em.expadmma_iCodigo', '=', 'ea.expadmma_iCodigo')
                ->where('pl.pla_id', $plaId)
                ->orderBy('pl.plaalu_iOrden')
                ->selectRaw("CONCAT('alu-', al.alu_vcCodigo) as row_key, pl.plaalu_iOrden as orden, al.alu_vcCodigo as codigo, al.alu_vcDni as dni, CONCAT(al.alu_vcPaterno, ' ', al.alu_vcMaterno, ' ', al.alu_vcNombre) as nombres, lm.locma_vcNombre as local_nombre, em.expadmma_vcNombre as cargo_nombre, COALESCE(ea.expadm_fMonto,0) as monto");
        }

        // Wrap raw builder as Eloquent builder using fromSub
        return \App\Models\EntregaCredencialRow::query()->fromSub($q, 'u')->select('u.*');
    }

    public function reimprimirPlanilla(): void
    {
        $plaId = (int) ($this->filters['planilla_id'] ?? 0);
        if (!$plaId) {
            Notification::make()->title('Seleccione una planilla')->warning()->send();
            return;
        }
        try {
            $pla = Planilla::find($plaId);
            if (!$pla) {
                Notification::make()->title('Planilla no encontrada')->danger()->send();
                return;
            }

            // Reusar la consulta de la tabla y construir páginas en el orden guardado (13 por página)
            $rows = $this->getTableQuery()->clone()->get();
            if ($rows->isEmpty()) {
                Notification::make()->title('Sin datos')->warning()->body('La planilla no tiene registros.')->send();
                return;
            }

            $pageNo = (int) $pla->pla_iPaginaInicio;
            $pages = [];
            $chunks = $rows->chunk(15);
            foreach ($chunks as $chunk) {
                $pages[] = [
                    'type' => 'detail',
                    'local_id' => null,
                    'local_nombre' => optional($rows->first())->local_nombre,
                    'cargo_id' => null,
                    'cargo_nombre' => null,
                    'monto_cargo' => null,
                    'planilla_numero' => (int) $pla->pla_iNumero,
                    'page_no' => $pageNo++,
                    'rows' => $chunk->map(function ($r) {
                        return [
                            'orden' => (int) $r->orden,
                            'codigo' => $r->codigo,
                            'dni' => $r->dni,
                            'nombres' => $r->nombres,
                            'local_nombre' => $r->local_nombre,
                            'cargo_nombre' => $r->cargo_nombre,
                            'monto' => (float) $r->monto,
                            'cred_numero' => null,
                        ];
                    })->toArray(),
                ];
            }

            // Datos de encabezado
            $proceso = Proceso::find($pla->pro_iCodigo);
            $fecha = ProcesoFecha::find($pla->profec_iCodigo);
            $tipo = Tipo::find($pla->tipo_iCodigo);
            $tituloPlanilla = $tipo?->tipo_vcNombrePlanilla ?? 'PLANILLA';

            $data = [
                'numero_planilla' => (int) $pla->pla_iNumero,
                'proceso_nombre' => $proceso?->pro_vcNombre,
                'fecha_proceso' => optional($fecha)->profec_dFecha,
                'impresion_fecha' => now(),
                'titulo_planilla' => $tituloPlanilla,
                'pages' => $pages,
                'total_pages' => count($pages),
            ];

            // Usar la misma vista de detalle (con fondos si existen)
            $detailBgUrl = $this->findTemplateImageUrl('docentes');
            $data['bg_detail_url'] = $detailBgUrl;
            $data['bg_summary_url'] = null; // reimpresión simple sin resumen

            $pdf = \PDF::loadView('pdf.planilla_docentes_compilado', $data)->setPaper('a4', 'landscape');
            $content = $pdf->output();
            $downloadName = 'reimpresion_planilla_'.$pla->pla_iNumero.'_'.now()->format('Ymd_His').'.pdf';

            response()->streamDownload(function () use ($content) { echo $content; }, $downloadName, [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ])->send();
        } catch (\Throwable $e) {
            \Log::error('Error reimprimiendo planilla', ['ex' => $e]);
            Notification::make()->title('Error al reimprimir')->danger()->body($e->getMessage())->send();
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
                $path = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$baseName.'.'.$ext;
                if (is_file($path)) {
                    $rel = str_replace(public_path(), '', $path);
                    return asset(ltrim($rel, '/\\'));
                }
            }
        }
        return null;
    }
}
