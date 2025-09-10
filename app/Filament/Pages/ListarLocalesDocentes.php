<?php

namespace App\Filament\Pages;

use App\Models\ProcesoFecha;
use App\Models\Locales;
use Filament\Pages\Page;
use Filament\Tables; 
use Filament\Tables\Table; 
use Filament\Tables\Columns\TextColumn; 
use Filament\Forms; 
use Filament\Forms\Components\Select; 
use Illuminate\Database\Eloquent\Builder; 
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class ListarLocalesDocentes extends Page implements Tables\Contracts\HasTable, Forms\Contracts\HasForms
{
    use Tables\Concerns\InteractsWithTable; 
    use Forms\Concerns\InteractsWithForms; 
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationLabel = 'Locales Docentes';
    protected static ?string $navigationGroup = 'Docentes';
    protected static ?int $navigationSort = 35;
    protected static string $view = 'filament.pages.listar-locales-docentes';

    public ?int $procesoFechaId = null;
    protected array $vacantesCache = [];

    public function mount(): void
    {
        // Preseleccionar la fecha activa si existe
        $this->procesoFechaId = ProcesoFecha::where('profec_iActivo', true)->value('profec_iCodigo');
    }

    // Control de acceso explícito (compatibilidad con Filament Shield si aún no se ha generado el permiso)
    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->hasRole('super_admin') || $user->can('page_ListarLocalesDocentes');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('procesoFechaId')
                ->label('Fecha Activa')
                ->options(ProcesoFecha::where('profec_iActivo', true)->orderByDesc('profec_dFecha')->pluck('profec_dFecha', 'profec_iCodigo'))
                ->searchable()
                ->reactive()
                ->afterStateUpdated(fn ($state) => $this->resetTable())
                ->native(false),
        ];
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema($this->getFormSchema());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $query = Locales::query()
                    ->with(['localesMaestro'])
                    ->when($this->procesoFechaId, fn ($q) => $q->where('profec_iCodigo', $this->procesoFechaId))
                    // Solo locales que tengan al menos uno de los cargos (maestro) 2,3,4 en la fecha seleccionada
                    ->whereHas('experienciaAdmision', function ($q) {
                        $q->whereIn('expadmma_iCodigo', [2,3,4]);
                        if ($this->procesoFechaId) {
                            $q->where('profec_iCodigo', $this->procesoFechaId);
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
                    ->label('Docente de Aula (Vacantes/Ocupados)')
                    ->getStateUsing(fn ($record) => $this->vacantesCache[$record->loc_iCodigo][4] ?? '')
                    ->sortable(false),
                TextColumn::make('coordinador')
                    ->label('Coordinador de Unidad (Vacantes/Ocupados)')
                    ->getStateUsing(fn ($record) => $this->vacantesCache[$record->loc_iCodigo][3] ?? '')
                    ->sortable(false),
                TextColumn::make('jefe')
                    ->label('Jefe de Unidad (Vacantes/Ocupados)')
                    ->getStateUsing(fn ($record) => $this->vacantesCache[$record->loc_iCodigo][2] ?? '')
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
                        if (!filled($data['nombre'] ?? null)) { return $query; }
                        return $query->whereHas('localesMaestro', function (Builder $q) use ($data) {
                            $q->where('locma_vcNombre', 'like', '%'.$data['nombre'].'%');
                        });
                    }),
            ])
            ->paginated([10,25,50])
            ->defaultPaginationPageOption(10)
            ->striped();
    }

    protected function loadVacantesCache(): void
    {
        $this->vacantesCache = [];
        if (!$this->procesoFechaId) {
            return;
        }
        $rows = \DB::table('localcargo as lc')
            ->join('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'lc.expadm_iCodigo')
            ->selectRaw('lc.loc_iCodigo, ea.expadmma_iCodigo as maestro_id, SUM(lc.loccar_iVacante) as vac, SUM(lc.loccar_iOcupado) as ocu')
            ->where('ea.profec_iCodigo', $this->procesoFechaId)
            ->whereIn('ea.expadmma_iCodigo', [2,3,4])
            ->groupBy('lc.loc_iCodigo', 'ea.expadmma_iCodigo')
            ->get();
        foreach ($rows as $r) {
            $this->vacantesCache[$r->loc_iCodigo][$r->maestro_id] = ($r->vac ?? 0) . ' / ' . ($r->ocu ?? 0);
        }
    }
}
