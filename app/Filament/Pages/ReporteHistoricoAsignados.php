<?php

namespace App\Filament\Pages;

use App\Exports\GenericSimpleArrayExport;
use App\Models\EntregaCredencialRow;
use App\Models\Proceso;
use App\Models\ProcesoAdministrativo;
use App\Models\ProcesoAlumno;
use App\Models\ProcesoDocente;
use App\Models\ProcesoFecha;
use App\Support\CurrentContext;
use App\Support\Traits\UsesGlobalContext;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ReporteHistoricoAsignados extends Page implements HasForms, HasTable
{
    use HasPageShield;
    use InteractsWithForms;
    use InteractsWithTable;
    use UsesGlobalContext;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Reportes';

    protected static ?string $title = 'Reporte Historico Asignados';

    protected string $view = 'filament.pages.reporte-historico-asignados';

    public array $filters = [
        'proceso_id' => null,
        'proceso_fecha_id' => null,
        'tipo' => null,
    ];

    protected $listeners = ['context-changed' => 'onContextChanged'];

    public function mount(): void
    {
        $ctx = app(CurrentContext::class);
        $this->filters['proceso_id'] = $ctx->procesoId();
        $this->filters['proceso_fecha_id'] = $ctx->fechaId();
        $this->form->fill($this->filters);
    }

    public function onContextChanged(): void
    {
        $ctx = app(CurrentContext::class);
        $this->filters['proceso_id'] = $ctx->procesoId();
        $this->filters['proceso_fecha_id'] = $ctx->fechaId();
        $this->form->fill($this->filters);

        if (method_exists($this, 'resetTable')) {
            $this->resetTable();
        }
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('proceso_id')
                    ->label('Proceso')
                    ->options(fn () => Proceso::query()->orderBy('pro_vcNombre')->pluck('pro_vcNombre', 'pro_iCodigo'))
                    ->columnSpan(['default' => 12, 'md' => 4])
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state): void {
                        $this->filters['proceso_id'] = $state;
                        $this->filters['proceso_fecha_id'] = null;
                        $this->form->fill($this->filters);
                    }),
                Select::make('proceso_fecha_id')
                    ->label('Fecha del Proceso')
                    ->columnSpan(['default' => 12, 'md' => 4])
                    ->options(function (): array {
                        $procesoId = $this->filters['proceso_id'] ?? null;

                        if (! $procesoId) {
                            return [];
                        }

                        return ProcesoFecha::query()
                            ->where('pro_iCodigo', $procesoId)
                            ->orderByDesc('profec_dFecha')
                            ->pluck('profec_dFecha', 'profec_iCodigo')
                            ->toArray();
                    })
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state): void {
                        $this->filters['proceso_fecha_id'] = $state;

                        if (method_exists($this, 'resetTable')) {
                            $this->resetTable();
                        }
                    }),
                Select::make('tipo')
                    ->label('Tipo de Personal')
                    ->columnSpan(['default' => 12, 'md' => 4])
                    ->options([
                        'docente' => 'Docente',
                        'alumno' => 'Alumno',
                        'administrativo' => 'Administrativo',
                    ])
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state): void {
                        $this->filters['tipo'] = $state;

                        if (method_exists($this, 'resetTable')) {
                            $this->resetTable();
                        }
                    }),
            ])
            ->columns(12)
            ->statePath('filters');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generar_reporte')
                ->label('Generar Reporte')
                ->icon('heroicon-o-arrow-down-tray')
                ->action('generateReport')
                ->disabled(fn (): bool => ! ($this->filters['proceso_id'] && $this->filters['proceso_fecha_id'] && $this->filters['tipo'])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getBaseQuery())
            ->columns([
                TextColumn::make('codigo')
                    ->label('Codigo')
                    ->searchable(),
                TextColumn::make('documento')
                    ->label('Documento')
                    ->searchable(),
                TextColumn::make('apellidos_nombres')
                    ->label('Apellidos y Nombres')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('local')
                    ->label('Local')
                    ->searchable(),
                TextColumn::make('cargo')
                    ->label('Cargo')
                    ->searchable(),
            ])
            ->defaultSort('apellidos_nombres')
            ->searchPlaceholder('Buscar por codigo, documento, nombre, local o cargo...')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25);
    }

    protected function getBaseQuery(): Builder
    {
        $procesoId = (int) ($this->filters['proceso_id'] ?? 0);
        $fechaId = (int) ($this->filters['proceso_fecha_id'] ?? 0);
        $tipo = $this->filters['tipo'] ?? null;

        if (! $procesoId || ! $fechaId || ! $tipo) {
            return $this->buildEmptyQuery();
        }

        return match ($tipo) {
            'docente' => $this->buildDocenteQuery($procesoId, $fechaId),
            'alumno' => $this->buildAlumnoQuery($procesoId, $fechaId),
            'administrativo' => $this->buildAdministrativoQuery($procesoId, $fechaId),
            default => $this->buildEmptyQuery(),
        };
    }

    protected function buildEmptyQuery(): Builder
    {
        $empty = DB::query()->fromSub(
            DB::table('procesodocente')
                ->selectRaw("'' as row_key, 0 as row_id, '' as tipo_personal, '' as codigo, '' as documento, '' as apellidos_nombres, '' as local, '' as cargo, '' as dependencia, '' as celular, '' as correo_electronico, '' as tipo_administrativo, null as fecha")
                ->whereRaw('1 = 0'),
            'u'
        )->select('u.*');

        $model = (new EntregaCredencialRow)->newQuery();
        $model->fromSub($empty, 'u');

        return $model;
    }

    protected function buildDocenteQuery(int $procesoId, int $fechaId): Builder
    {
        $sub = ProcesoDocente::query()
            ->from('procesodocente')
            ->join('procesofecha as pf', 'pf.profec_iCodigo', '=', 'procesodocente.profec_iCodigo')
            ->join('docente as d', 'd.doc_vcCodigo', '=', 'procesodocente.doc_vcCodigo')
            ->leftJoin('dependencia as dep', 'dep.dep_iCodigo', '=', 'd.dep_iCodigo')
            ->leftJoin('locales as l', function ($join) use ($fechaId) {
                $join->on('l.loc_iCodigo', '=', 'procesodocente.loc_iCodigo')
                    ->where('l.profec_iCodigo', '=', $fechaId);
            })
            ->leftJoin('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
            ->leftJoin('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'procesodocente.expadm_iCodigo')
            ->leftJoin('experienciaadmisionMaestro as em', 'em.expadmma_iCodigo', '=', 'ea.expadmma_iCodigo')
            ->select([
                DB::raw("CONCAT('doc-', procesodocente.prodoc_iCodigo) as row_key"),
                DB::raw('procesodocente.prodoc_iCodigo as row_id'),
                DB::raw("'DOC' as tipo_personal"),
                'd.doc_vcCodigo as codigo',
                'd.doc_vcDni as documento',
                DB::raw("CONCAT_WS(' ', d.doc_vcPaterno, d.doc_vcMaterno, d.doc_vcNombre) as apellidos_nombres"),
                DB::raw('COALESCE(lm.locma_vcNombre, "") as local'),
                DB::raw('COALESCE(em.expadmma_vcNombre, "") as cargo'),
                DB::raw('COALESCE(dep.dep_vcNombre, "") as dependencia'),
                DB::raw('COALESCE(d.doc_vcCelular, "") as celular'),
                DB::raw('COALESCE(NULLIF(d.doc_vcEmailUNMSM, ""), d.doc_vcEmail, "") as correo_electronico'),
                DB::raw('"" as tipo_administrativo'),
                'pf.profec_dFecha as fecha',
            ])
            ->where('procesodocente.prodoc_iAsignacion', true)
            ->where('procesodocente.profec_iCodigo', $fechaId)
            ->where('pf.pro_iCodigo', $procesoId);

        return EntregaCredencialRow::query()->fromSub($sub, 'u')->select('u.*');
    }

    protected function buildAlumnoQuery(int $procesoId, int $fechaId): Builder
    {
        $sub = ProcesoAlumno::query()
            ->from('procesoalumno')
            ->join('procesofecha as pf', 'pf.profec_iCodigo', '=', 'procesoalumno.profec_iCodigo')
            ->join('alumno as a', 'a.alu_vcCodigo', '=', 'procesoalumno.alu_vcCodigo')
            ->leftJoin('locales as l', function ($join) use ($fechaId) {
                $join->on('l.loc_iCodigo', '=', 'procesoalumno.loc_iCodigo')
                    ->where('l.profec_iCodigo', '=', $fechaId);
            })
            ->leftJoin('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
            ->leftJoin('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'procesoalumno.expadm_iCodigo')
            ->leftJoin('experienciaadmisionMaestro as em', 'em.expadmma_iCodigo', '=', 'ea.expadmma_iCodigo')
            ->select([
                DB::raw("CONCAT('alu-', procesoalumno.proalu_iCodigo) as row_key"),
                DB::raw('procesoalumno.proalu_iCodigo as row_id'),
                DB::raw("'ALU' as tipo_personal"),
                'a.alu_vcCodigo as codigo',
                'a.alu_vcDni as documento',
                DB::raw("CONCAT_WS(' ', a.alu_vcPaterno, a.alu_vcMaterno, a.alu_vcNombre) as apellidos_nombres"),
                DB::raw('COALESCE(lm.locma_vcNombre, "") as local'),
                DB::raw('COALESCE(em.expadmma_vcNombre, "") as cargo'),
                DB::raw('"" as dependencia'),
                DB::raw('"" as celular'),
                DB::raw('"" as correo_electronico'),
                DB::raw('"" as tipo_administrativo'),
                'pf.profec_dFecha as fecha',
            ])
            ->where('procesoalumno.proalu_iAsignacion', true)
            ->where('procesoalumno.profec_iCodigo', $fechaId)
            ->where('pf.pro_iCodigo', $procesoId);

        return EntregaCredencialRow::query()->fromSub($sub, 'u')->select('u.*');
    }

    protected function buildAdministrativoQuery(int $procesoId, int $fechaId): Builder
    {
        $sub = ProcesoAdministrativo::query()
            ->from('procesoadministrativo')
            ->join('procesofecha as pf', 'pf.profec_iCodigo', '=', 'procesoadministrativo.profec_iCodigo')
            ->join('administrativo as a', 'a.adm_vcDni', '=', 'procesoadministrativo.adm_vcDni')
            ->leftJoin('tipo as t', 't.tipo_iCodigo', '=', 'a.tipo_iCodigo')
            ->leftJoin('locales as l', function ($join) use ($fechaId) {
                $join->on('l.loc_iCodigo', '=', 'procesoadministrativo.loc_iCodigo')
                    ->where('l.profec_iCodigo', '=', $fechaId);
            })
            ->leftJoin('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
            ->leftJoin('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'procesoadministrativo.expadm_iCodigo')
            ->leftJoin('experienciaadmisionMaestro as em', 'em.expadmma_iCodigo', '=', 'ea.expadmma_iCodigo')
            ->select([
                DB::raw("CONCAT('adm-', procesoadministrativo.proadm_iCodigo) as row_key"),
                DB::raw('procesoadministrativo.proadm_iCodigo as row_id'),
                DB::raw("'ADM' as tipo_personal"),
                DB::raw('COALESCE(a.adm_vcCodigo, "") as codigo'),
                'a.adm_vcDni as documento',
                DB::raw('COALESCE(a.adm_vcNombres, "") as apellidos_nombres'),
                DB::raw('COALESCE(lm.locma_vcNombre, "") as local'),
                DB::raw('COALESCE(em.expadmma_vcNombre, "") as cargo'),
                DB::raw('"" as dependencia'),
                DB::raw('"" as celular'),
                DB::raw('"" as correo_electronico'),
                DB::raw('COALESCE(t.tipo_vcNombre, "") as tipo_administrativo'),
                'pf.profec_dFecha as fecha',
            ])
            ->where('procesoadministrativo.proadm_iAsignacion', true)
            ->where('procesoadministrativo.profec_iCodigo', $fechaId)
            ->where('pf.pro_iCodigo', $procesoId);

        return EntregaCredencialRow::query()->fromSub($sub, 'u')->select('u.*');
    }

    public function generateReport()
    {
        $procesoId = (int) ($this->filters['proceso_id'] ?? 0);
        $fechaId = (int) ($this->filters['proceso_fecha_id'] ?? 0);
        $tipo = (string) ($this->filters['tipo'] ?? '');

        if (! $procesoId || ! $fechaId || $tipo === '') {
            Notification::make()
                ->title('Complete los filtros')
                ->warning()
                ->body('Debe seleccionar proceso, fecha del proceso y tipo de personal.')
                ->send();

            return null;
        }

        $records = $this->getBaseQuery()->get();

        if ($records->isEmpty()) {
            Notification::make()
                ->title('Sin datos para exportar')
                ->warning()
                ->body('No hay personal asignado para los filtros seleccionados.')
                ->send();

            return null;
        }

        $rows = $this->buildExportRows($records, $tipo);
        $procesoNombre = Proceso::query()->where('pro_iCodigo', $procesoId)->value('pro_vcNombre') ?? ('Proceso '.$procesoId);
        $fechaProceso = ProcesoFecha::query()->where('profec_iCodigo', $fechaId)->value('profec_dFecha');
        $fechaTitulo = $this->formatTitleDate($fechaProceso);
        $title = 'Reporte Historico Asignados - '.ucfirst($tipo).' - '.$procesoNombre.' - '.$fechaTitulo;
        $fileName = 'reporte_historico_asignados_'.$tipo.'_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new GenericSimpleArrayExport($rows, $title), $fileName);
    }

    protected function buildExportRows(Collection $records, string $tipo): Collection
    {
        return match ($tipo) {
            'docente' => $records->map(fn ($row) => [
                'codigo' => (string) ($row->codigo ?? ''),
                'Documento' => (string) ($row->documento ?? ''),
                'apellidos y nombres' => (string) ($row->apellidos_nombres ?? ''),
                'dependencia' => (string) ($row->dependencia ?? ''),
                'celular' => (string) ($row->celular ?? ''),
                'correo electronico' => (string) ($row->correo_electronico ?? ''),
                'local' => (string) ($row->local ?? ''),
                'cargo' => (string) ($row->cargo ?? ''),
                'fecha' => $this->formatExportDate($row->fecha ?? null),
            ]),
            'alumno' => $records->map(fn ($row) => [
                'codigo' => (string) ($row->codigo ?? ''),
                'Documento' => (string) ($row->documento ?? ''),
                'apellidos y nombres' => (string) ($row->apellidos_nombres ?? ''),
                'local' => (string) ($row->local ?? ''),
                'cargo' => (string) ($row->cargo ?? ''),
                'fecha' => $this->formatExportDate($row->fecha ?? null),
            ]),
            'administrativo' => $records->map(fn ($row) => [
                'codigo' => (string) ($row->codigo ?? ''),
                'Documento' => (string) ($row->documento ?? ''),
                'apellidos y nombres' => (string) ($row->apellidos_nombres ?? ''),
                'local' => (string) ($row->local ?? ''),
                'cargo' => (string) ($row->cargo ?? ''),
                'fecha' => $this->formatExportDate($row->fecha ?? null),
                'tipo de administrativo' => (string) ($row->tipo_administrativo ?? ''),
            ]),
            default => collect(),
        };
    }

    protected function formatExportDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Throwable $e) {
                return $value;
            }
        }

        return '';
    }

    protected function formatTitleDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value)->format('d/m/Y');
            } catch (\Throwable $e) {
                return $value;
            }
        }

        return '-';
    }
}
