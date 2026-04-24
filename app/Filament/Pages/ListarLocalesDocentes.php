<?php

namespace App\Filament\Pages;

use App\Models\Locales;
use App\Models\ProcesoFecha;
use App\Support\CurrentContext;
use App\Support\Traits\UsesGlobalContext;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListarLocalesDocentes extends Page implements Forms\Contracts\HasForms, Tables\Contracts\HasTable
{
    use Forms\Concerns\InteractsWithForms;
    use HasPageShield;
    use Tables\Concerns\InteractsWithTable;
    use UsesGlobalContext;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Locales Docentes';

    protected static string|\UnitEnum|null $navigationGroup = 'Docentes';

    protected static ?int $navigationSort = 35;

    protected string $view = 'filament.pages.listar-locales-docentes';

    public array $data = [];

    public ?int $proceso_fecha_id = null; // estandarizar nombre para el trait

    protected array $vacantesCache = [];

    protected array $localSummaryCache = [];

    protected array $dashboardCache = [];

    protected ?int $dashboardCacheFechaId = null;

    protected $listeners = ['context-changed' => 'onContextChanged'];

    public function mount(): void
    {
        $this->proceso_fecha_id = app(CurrentContext::class)->fechaId();
        $this->form->fill(['proceso_fecha_id' => $this->proceso_fecha_id]);
    }

    public function onContextChanged(): void
    {
        $this->proceso_fecha_id = app(CurrentContext::class)->fechaId();
        $this->form->fill(['proceso_fecha_id' => $this->proceso_fecha_id]);
        $this->dashboardCache = [];
        $this->vacantesCache = [];
        $this->localSummaryCache = [];
        $this->dashboardCacheFechaId = null;
        $this->resetTable();
        Notification::make()->title('Contexto actualizado')->body('Se aplicó la nueva fecha global.')->info()->send();
    }

    // Único botón Imprimir en el header
    protected function getHeaderActions(): array
    {
        return [
            Action::make('imprimir')
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->extraAttributes([
                    'onclick' => 'window.print()',
                ]),
        ];
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
            // Fecha actual global solo lectura
            $this->fechaActualPlaceholder('proceso_fecha_id'),
            // Campo oculto para mantener compatibilidad si fuese necesario
            Select::make('proceso_fecha_id')
                ->label('Fecha Global')
                ->options(ProcesoFecha::where('profec_iActivo', true)->orderByDesc('profec_dFecha')->pluck('profec_dFecha', 'profec_iCodigo'))
                ->hidden(),
        ])->statePath('data');
    }

    // (Se removió acción de imprimir en el header para evitar botón duplicado)

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $query = Locales::query()
                    ->with(['localesMaestro'])
                    ->when($this->proceso_fecha_id, fn ($q) => $q->where('profec_iCodigo', $this->proceso_fecha_id))
                    // Solo locales que tengan al menos uno de los cargos (maestro) 2,3,4 en la fecha seleccionada
                    ->whereHas('experienciaAdmision', function ($q) {
                        $q->whereIn('expadmma_iCodigo', [2, 3, 4]);
                        if ($this->proceso_fecha_id) {
                            $q->where('profec_iCodigo', $this->proceso_fecha_id);
                        }
                    });
                // Pre-cargar cache de vacantes/ocupados para esta fecha (evita N+1)
                $this->loadVacantesCache();

                return $query;
            })
            ->heading('Locales y Vacantes por Cargo')
            ->columns([
                TextColumn::make('localesMaestro.locma_vcNombre')
                    ->label('Local')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('aula')
                    ->label('D.A (Vacantes/Ocupados)')
                    ->getStateUsing(fn ($record) => $this->vacantesCache[$record->loc_iCodigo][4] ?? '')
                    ->sortable(false),
                TextColumn::make('coordinador')
                    ->label('C.U (Vacantes/Ocupados)')
                    ->getStateUsing(fn ($record) => $this->vacantesCache[$record->loc_iCodigo][3] ?? '')
                    ->sortable(false),
                TextColumn::make('jefe')
                    ->label('J.U (Vacantes/Ocupados)')
                    ->getStateUsing(fn ($record) => $this->vacantesCache[$record->loc_iCodigo][2] ?? '')
                    ->sortable(false),
                TextColumn::make('ocupacion_total')
                    ->label('Ocupación Total')
                    ->getStateUsing(function ($record) {
                        $summary = $this->getLocalSummary((int) $record->loc_iCodigo);

                        if ($summary['capacidad'] <= 0) {
                            return '0% (0/0)';
                        }

                        return number_format($summary['pct'], 1).'% ('.$summary['ocupados'].'/'.$summary['capacidad'].')';
                    })
                    ->badge()
                    ->color(function ($record) {
                        $summary = $this->getLocalSummary((int) $record->loc_iCodigo);

                        if ($summary['pct'] >= 100) {
                            return 'danger';
                        }

                        if ($summary['pct'] >= 90) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->sortable(false),
            ])
            ->defaultSort('localesMaestro.locma_vcNombre')
            ->searchPlaceholder('Buscar local...')
            ->filters([
                Tables\Filters\Filter::make('nombre')
                    ->form([
                        Forms\Components\TextInput::make('nombre')->label('Nombre del Local'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! filled($data['nombre'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas('localesMaestro', function (Builder $q) use ($data) {
                            $q->where('locma_vcNombre', 'like', '%'.$data['nombre'].'%');
                        });
                    }),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->striped();
    }

    protected function loadVacantesCache(): void
    {
        $this->ensureDashboardCache();
    }

    public function getDashboardData(): array
    {
        $this->ensureDashboardCache();

        return $this->dashboardCache;
    }

    protected function getLocalSummary(int $localId): array
    {
        $this->ensureDashboardCache();

        return $this->localSummaryCache[$localId] ?? [
            'vacantes' => 0,
            'ocupados' => 0,
            'capacidad' => 0,
            'pct' => 0.0,
        ];
    }

    protected function ensureDashboardCache(): void
    {
        $fechaId = (int) ($this->proceso_fecha_id ?? 0);

        if (! $fechaId) {
            $this->dashboardCache = [
                'kpis' => [
                    'locales' => 0,
                    'vacantes' => 0,
                    'ocupados' => 0,
                    'capacidad' => 0,
                    'ocupacion_pct' => 0,
                    'saturados' => 0,
                ],
                'local_bars' => [],
                'top_saturados' => [],
                'cargo_breakdown' => [
                    ['label' => 'D.A', 'value' => 0],
                    ['label' => 'C.U', 'value' => 0],
                    ['label' => 'J.U', 'value' => 0],
                ],
            ];
            $this->vacantesCache = [];
            $this->localSummaryCache = [];
            $this->dashboardCacheFechaId = $fechaId;

            return;
        }

        if ($this->dashboardCacheFechaId === $fechaId && ! empty($this->dashboardCache)) {
            return;
        }

        $rows = DB::table('localcargo as lc')
            ->join('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'lc.expadm_iCodigo')
            ->join('locales as l', function ($join) use ($fechaId) {
                $join->on('l.loc_iCodigo', '=', 'lc.loc_iCodigo')
                    ->where('l.profec_iCodigo', '=', $fechaId);
            })
            ->join('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
            ->selectRaw('lc.loc_iCodigo, lm.locma_vcNombre as local_nombre, ea.expadmma_iCodigo as maestro_id, SUM(lc.loccar_iVacante) as vac, SUM(lc.loccar_iOcupado) as ocu')
            ->where('ea.profec_iCodigo', $fechaId)
            ->whereIn('ea.expadmma_iCodigo', [2, 3, 4])
            ->groupBy('lc.loc_iCodigo', 'lm.locma_vcNombre', 'ea.expadmma_iCodigo')
            ->get();

        $vacantesCache = [];
        $localStats = [];
        $cargoOcupados = [4 => 0, 3 => 0, 2 => 0];

        foreach ($rows as $row) {
            $locId = (int) $row->loc_iCodigo;
            $maestroId = (int) $row->maestro_id;
            $vac = (int) ($row->vac ?? 0);
            $ocu = (int) ($row->ocu ?? 0);

            $vacantesCache[$locId][$maestroId] = $vac.' / '.$ocu;

            if (! isset($localStats[$locId])) {
                $localStats[$locId] = [
                    'local_id' => $locId,
                    'local_nombre' => (string) $row->local_nombre,
                    'vacantes' => 0,
                    'ocupados' => 0,
                ];
            }

            $localStats[$locId]['vacantes'] += $vac;
            $localStats[$locId]['ocupados'] += $ocu;
            $cargoOcupados[$maestroId] = ($cargoOcupados[$maestroId] ?? 0) + $ocu;
        }

        $localBars = [];
        $topSaturados = [];
        $localSummaryCache = [];
        $totalVacantes = 0;
        $totalOcupados = 0;
        $saturados = 0;

        foreach ($localStats as $local) {
            $capacidad = (int) $local['vacantes'];
            $pct = $capacidad > 0 ? ($local['ocupados'] / $capacidad) * 100 : 0;
            $ocupadoPct = $capacidad > 0 ? min(100, ($local['ocupados'] / $capacidad) * 100) : 0;
            $disponiblePct = max(0, 100 - $ocupadoPct);
            $disponibles = max(0, $capacidad - (int) $local['ocupados']);

            $totalVacantes += (int) $local['vacantes'];
            $totalOcupados += (int) $local['ocupados'];

            if ($pct >= 90) {
                $saturados++;
            }

            $item = [
                'local_id' => $local['local_id'],
                'local_nombre' => $local['local_nombre'],
                'vacantes' => (int) $local['vacantes'],
                'ocupados' => (int) $local['ocupados'],
                'disponibles' => $disponibles,
                'capacidad' => $capacidad,
                'pct' => round($pct, 1),
                'disp_pct' => round($disponiblePct, 1),
                'ocu_pct' => round($ocupadoPct, 1),
            ];

            $localBars[] = $item;
            $topSaturados[] = $item;
            $localSummaryCache[(int) $local['local_id']] = [
                'vacantes' => (int) $local['vacantes'],
                'ocupados' => (int) $local['ocupados'],
                'capacidad' => $capacidad,
                'pct' => round($pct, 1),
            ];
        }

        usort($localBars, fn (array $a, array $b) => strcmp($a['local_nombre'], $b['local_nombre']));
        usort($topSaturados, fn (array $a, array $b) => $b['pct'] <=> $a['pct']);
        $topSaturados = array_slice($topSaturados, 0, 10);

        $totalCapacidad = $totalVacantes;
        $ocupacionGlobalPct = $totalCapacidad > 0 ? round(($totalOcupados / $totalCapacidad) * 100, 1) : 0;

        $this->vacantesCache = $vacantesCache;
        $this->localSummaryCache = $localSummaryCache;
        $this->dashboardCache = [
            'kpis' => [
                'locales' => count($localStats),
                'vacantes' => $totalVacantes,
                'ocupados' => $totalOcupados,
                'capacidad' => $totalCapacidad,
                'ocupacion_pct' => $ocupacionGlobalPct,
                'saturados' => $saturados,
            ],
            'local_bars' => array_slice($localBars, 0, 14),
            'top_saturados' => $topSaturados,
            'cargo_breakdown' => [
                ['label' => 'D.A', 'value' => (int) ($cargoOcupados[4] ?? 0)],
                ['label' => 'C.U', 'value' => (int) ($cargoOcupados[3] ?? 0)],
                ['label' => 'J.U', 'value' => (int) ($cargoOcupados[2] ?? 0)],
            ],
        ];
        $this->dashboardCacheFechaId = $fechaId;
    }
}
