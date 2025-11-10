<?php

namespace App\Filament\Pages;

use App\Models\ProcesoDocente;
use App\Models\ProcesoAdministrativo;
use App\Models\ProcesoAlumno;
use App\Models\ProcesoFecha;
use App\Support\CurrentContext;
use App\Support\Traits\UsesGlobalContext;
use Carbon\Carbon;
use Filament\Forms\Components\Placeholder;
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
    use UsesGlobalContext;

    protected static ?string $navigationIcon = 'heroicon-o-printer';
    protected static string $view = 'filament.pages.imprimir-credenciales';
    protected static ?string $title = 'Impresión de Credenciales';
    protected static ?string $navigationGroup = 'Credenciales';

    // Estado del formulario reactivo
    public array $data = [];
    // Canal secundario para pasar URL al front si el evento no fuese capturado
    public ?string $pendingPdfUrl = null;

    public function mount(): void
    {
        // Inicializar usando el contexto global (Proceso y Fecha)
        $this->fillContextDefaults(['proceso_id','proceso_fecha_id']);
        // Asegurar integridad inmediata si el form aún no está hidratado
        $ctx = app(CurrentContext::class);
        $this->form?->fill([
            'proceso_id' => $ctx->procesoId(),
            'proceso_fecha_id' => $ctx->fechaId(),
        ]);
    }

    #[\Livewire\Attributes\On('context-changed')]
    public function onContextChanged(): void
    {
        $this->applyContextFromGlobal(['proceso_id','proceso_fecha_id']);
        // Limpiar filtros/estado dependiente y URL pendiente de PDF
        $this->clearFiltersContext();
        $this->pendingPdfUrl = null;
        Notification::make()
            ->title('Contexto actualizado')
            ->body('Se aplicó la Fecha y Proceso globales y se reiniciaron filtros.')
            ->info()
            ->send();
    }

    // Helper público para obtener el ID Livewire sin exponer $this->id directamente en blade
    public function getLivewireId(): string
    {
        return $this->getId();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // 0. Mostrar la fecha del proceso (global) en solo lectura
                $this->fechaActualPlaceholder('proceso_fecha_id'),
                // 1. Proceso (global, oculto)
                Select::make('proceso_id')
                    ->label('Proceso')
                    ->options(Proceso::query()->where('pro_iAbierto', true)->orderBy('pro_vcNombre')->pluck('pro_vcNombre','pro_iCodigo'))
                    ->searchable()
                    ->reactive()
                    ->hidden()
                    ->afterStateUpdated(function(){
                        $this->data['proceso_fecha_id'] = null; $this->clearFiltersContext();
                    })
                    ->required()
                    ->dehydrated(true),
                // 2. Fecha (global, oculta)
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
                    ->hidden()
                    ->required()
                    ->dehydrated(true),
                // 3. Tipo de personal
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
            ->heading(function(){
                $base = $this->getTipoTablaLabel();
                $total = $this->getImpresionCount();
                $pend = $this->getPendingCount();
                $impresas = max($total - $pend, 0);
                $active = $this->isSoloPendientesFilterActive();
                // Badges: pendientes (amarillo si >0, verde si 0), impresas (azul), total (gris)
                $pendColorClasses = $pend > 0 ? 'bg-amber-500/15 text-amber-600 ring-1 ring-amber-500/30' : 'bg-emerald-500/15 text-emerald-600 ring-1 ring-emerald-500/30';
                $impColorClasses = 'bg-blue-500/15 text-blue-600 ring-1 ring-blue-500/30';
                $totColorClasses = 'bg-slate-500/15 text-slate-600 ring-1 ring-slate-500/30';
                $styleBase = 'inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full ring-1';
                $pendBadge = "<span class=\"{$styleBase} {$pendColorClasses}\">Pendientes: {$pend}</span>";
                $impBadge  = "<span class=\"{$styleBase} {$impColorClasses}\">Impresas: {$impresas}</span>";
                $totBadge  = "<span class=\"{$styleBase} {$totColorClasses}\">Total: {$total}</span>";
                $order = $active
                    ? [$pendBadge, $impBadge, $totBadge]
                    : [$totBadge, $pendBadge, $impBadge];
                $badges = implode(' ', $order);
                return new HtmlString('<div class="flex flex-wrap items-center gap-2">'
                    .'<span class="font-semibold">'.$base.'</span>'
                    .$badges
                    .'</div>');
            })
            ->headerActions([
                TableAction::make('imprimir_pendientes')
                    ->label('Imprimir Pendientes')
                    ->icon('heroicon-o-printer')
                    // Siempre visible para evitar saltos de layout; se habilita / deshabilita dinámicamente
                    ->color(fn() => $this->isSoloPendientesFilterActive() ? 'primary' : 'gray')
                    ->disabled(fn() => ! $this->isSoloPendientesFilterActive())
                    ->tooltip(fn() => $this->isSoloPendientesFilterActive()
                        ? 'Imprimir todas las credenciales pendientes del listado filtrado.'
                        : 'Active el filtro "Solo pendientes" para habilitar esta acción.')
                    ->action(function(){
                        if (! $this->isSoloPendientesFilterActive()) {
                            // Defensa adicional si el estado llegó desincronizado en el cliente
                            Notification::make()->title('Activar filtro')->body('Active el filtro "Solo pendientes" para usar esta acción.')->warning()->send();
                            return; }
                        $records = $this->getPendingRecordsFromFilter();
                        if ($records->isEmpty()) {
                            Notification::make()->title('Sin registros')->body('No hay pendientes en el contexto actual.')->warning()->send();
                            return; }
                        return $this->handleImpresionForRecords($records);
                    }),
            ])
            // Definimos todas las columnas y controlamos visibilidad según el tipo seleccionado
            ->columns([
                // Docentes
                TextColumn::make('docente.doc_vcCodigo')
                    ->label('Código')
                    ->sortable()->searchable()
                    ->extraAttributes(['class' => 'whitespace-nowrap w-20'])
                    ->visible(fn () => $this->getTipoSeleccionado() === 1),
                TextColumn::make('docente.doc_vcDni')
                    ->label('DNI')
                    ->sortable()->searchable()
                    ->extraAttributes(['class' => 'whitespace-nowrap w-24'])
                    ->visible(fn () => $this->getTipoSeleccionado() === 1),
                TextColumn::make('docente.nombre_completo')
                    ->label('Nombre')
                    ->searchable(['docente.doc_vcNombre','docente.doc_vcPaterno','docente.doc_vcMaterno'])
                    ->wrap()->limit(40)
                    ->extraAttributes(['class' => 'max-w-[240px]'])
                    ->visible(fn () => $this->getTipoSeleccionado() === 1),
                // Administrativos
                TextColumn::make('administrativo.adm_vcCodigo')
                    ->label('Código')
                    ->sortable()->searchable()
                    ->extraAttributes(['class' => 'whitespace-nowrap w-20'])
                    ->visible(fn () => $this->getTipoSeleccionado() === 2),
                TextColumn::make('administrativo.adm_vcDni')
                    ->label('DNI')
                    ->sortable()->searchable()
                    ->extraAttributes(['class' => 'whitespace-nowrap w-24'])
                    ->visible(fn () => $this->getTipoSeleccionado() === 2),
                TextColumn::make('administrativo.adm_vcNombres')
                    ->label('Nombre')
                    ->searchable()
                    ->wrap()->limit(40)
                    ->extraAttributes(['class' => 'max-w-[240px]'])
                    ->visible(fn () => $this->getTipoSeleccionado() === 2),
                // Alumnos
                TextColumn::make('alumno.alu_vcCodigo')
                    ->label('Código')
                    ->sortable()->searchable()
                    ->extraAttributes(['class' => 'whitespace-nowrap w-20'])
                    ->visible(fn () => $this->getTipoSeleccionado() === 3),
                TextColumn::make('alumno.alu_vcDni')
                    ->label('DNI')
                    ->sortable()->searchable()
                    ->extraAttributes(['class' => 'whitespace-nowrap w-24'])
                    ->visible(fn () => $this->getTipoSeleccionado() === 3),
                TextColumn::make('alumno.nombre_completo')
                    ->label('Nombre')
                    ->searchable(['alumno.alu_vcNombre','alumno.alu_vcPaterno','alumno.alu_vcMaterno'])
                    ->wrap()->limit(40)
                    ->extraAttributes(['class' => 'max-w-[240px]'])
                    ->visible(fn () => $this->getTipoSeleccionado() === 3),
                // Comunes
                TextColumn::make('experienciaAdmision.maestro.expadmma_vcNombre')
                    ->label('Cargo')
                    ->wrap()->limit(35)
                    ->extraAttributes(['class' => 'max-w-[200px]']),
                TextColumn::make('local.localesMaestro.locma_vcNombre')
                    ->label('Local')
                    ->wrap()->limit(25)
                    ->extraAttributes(['class' => 'max-w-[160px]']),
                // Toggles de Impresa por tipo
                ToggleColumn::make('prodoc_iCredencial')
                    ->label('Impresa')
                    ->afterStateUpdated(function ($record, $state) {
                        if (!$this->canUserPrintType(1)) { Notification::make()->title('Acción no permitida')->body('No puede marcar como impresa para este tipo.')->danger()->send(); $record->refresh(); return; }
                        $record->prodoc_dtFechaImpresion = $state ? now() : null; $record->save();
                    })
                    ->visible(fn () => $this->getTipoSeleccionado() === 1),
                ToggleColumn::make('proadm_iCredencial')
                    ->label('Impresa')
                    ->afterStateUpdated(function ($record, $state) {
                        if (!$this->canUserPrintType(2)) { Notification::make()->title('Acción no permitida')->body('No puede marcar como impresa para este tipo.')->danger()->send(); $record->refresh(); return; }
                        $record->proadm_dtFechaImpresion = $state ? now() : null; $record->save();
                    })
                    ->visible(fn () => $this->getTipoSeleccionado() === 2),
                ToggleColumn::make('proalu_iCredencial')
                    ->label('Impresa')
                    ->afterStateUpdated(function ($record, $state) {
                        if (!$this->canUserPrintType(3)) { Notification::make()->title('Acción no permitida')->body('No puede marcar como impresa para este tipo.')->danger()->send(); $record->refresh(); return; }
                        $record->proalu_dtFechaImpresion = $state ? now() : null; $record->save();
                    })
                    ->visible(fn () => $this->getTipoSeleccionado() === 3),
            ])
            ->filters([
                \Filament\Tables\Filters\Filter::make('solo_pendientes')
                    ->label('Solo pendientes')
                    ->toggle()
                    ->default(true)
                    ->query(function(Builder $query, array $data): Builder {
                        // Filament pasa el estado del toggle en $data (puede venir como ['isActive'=>true] o ['value'=>true] o boolean directo)
                        $active = false;
                        if (array_key_exists('value', $data)) {
                            $active = (bool)$data['value'];
                        } elseif (array_key_exists('isActive', $data)) {
                            $active = (bool)$data['isActive'];
                        } elseif (array_key_exists('state', $data)) {
                            $active = (bool)$data['state'];
                        }
                        if (!$active) return $query;
                        $tipo = $this->getTipoSeleccionado();
                        return match ($tipo) {
                            1 => $query->where(function($q){ $q->where(function($w){ $w->whereNull('prodoc_iCredencial')->orWhere('prodoc_iCredencial', false); }); }),
                            2 => $query->where(function($q){ $q->where(function($w){ $w->whereNull('proadm_iCredencial')->orWhere('proadm_iCredencial', false); }); }),
                            3 => $query->where(function($q){ $q->where(function($w){ $w->whereNull('proalu_iCredencial')->orWhere('proalu_iCredencial', false); }); }),
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
                    // Usar un evento de ventana para abrir el placeholder en una pestaña confiable por gesto de usuario
                    // Evita depender del ciclo de vida de Alpine dentro del botón de acción
                    ->extraAttributes(['onclick' => "window.dispatchEvent(new CustomEvent('open-placeholder'))"])
                    ->action(function (Collection $records) {
                        \Log::info('Credenciales: bulk imprimir seleccionados (count='.($records?->count() ?? 0).')');
                        $tipo = $this->getTipoSeleccionado();
                        if (!$this->canUserPrintType($tipo)) {
                            $this->dispatch('close-placeholder');
                            Notification::make()->title('Acción no permitida')->body('No tiene permisos para imprimir este tipo.')->danger()->send();
                            return;
                        }
                        if ($records->isEmpty()) {
                            // cerrar pestaña placeholder si no hay nada que imprimir
                            $this->dispatch('close-placeholder');
                            Notification::make()->title('Sin selección')->body('Seleccione al menos un registro.')->warning()->send();
                            return;
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

    protected function getPendingCount(): int
    {
        $procesoFechaId = (int) (data_get($this->data,'proceso_fecha_id') ?? 0);
        $tipo = $this->getTipoSeleccionado();
        if (!$procesoFechaId || !in_array($tipo, [1,2,3], true)) return 0;
        $user = Auth::user();
        switch ($tipo) {
            case 1:
                $q = ProcesoDocente::query()->where('profec_iCodigo', $procesoFechaId)->where('prodoc_iAsignacion', true)
                    ->where(function($w){ $w->whereNull('prodoc_iCredencial')->orWhere('prodoc_iCredencial', false); });
                break;
            case 2:
                $q = ProcesoAdministrativo::query()->where('profec_iCodigo', $procesoFechaId)->where('proadm_iAsignacion', true)
                    ->where(function($w){ $w->whereNull('proadm_iCredencial')->orWhere('proadm_iCredencial', false); });
                break;
            case 3:
                $q = ProcesoAlumno::query()->where('profec_iCodigo', $procesoFechaId)->where('proalu_iAsignacion', true)
                    ->where(function($w){ $w->whereNull('proalu_iCredencial')->orWhere('proalu_iCredencial', false); });
                break;
            default:
                return 0;
        }
        if (!$user->hasAnyRole(['Economia','Info'])) {
            $currentRoles = $user->getRoleNames();
            $q->whereHas('usuario.roles', fn($r)=> $r->whereIn('name',$currentRoles));
        }
        return (int) $q->count();
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
                    ->with(['local.localesMaestro', 'experienciaAdmision.maestro', 'alumno'])
                    ->where('profec_iCodigo', $procesoFechaId)
                    ->where('proalu_iAsignacion', true);
                break;
        }

        if (!$user->hasAnyRole(['Economia', 'Info'])) {
            $currentRoles = $user->getRoleNames();
            $query->whereHas('usuario.roles', fn ($q) => $q->whereIn('name', $currentRoles));
        }

        // Ya no aplicamos aquí "solo pendientes"; se maneja con el filtro toggle.

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

    // Métodos relacionados al toggle externo eliminados (se usa filtro interno ahora).

    // Eliminados métodos dinámicos; la visibilidad se maneja con closures en cada columna
    protected function isSoloPendientesFilterActive(): bool
    {
        // Fuente 1: tableFiltersFormData (forma más común en Filament)
        $candidates = [];
        if (property_exists($this, 'tableFiltersFormData')) {
            $candidates[] = $this->tableFiltersFormData['solo_pendientes'] ?? null;
        }
        // Fuente 2: tableFilters (a veces almacena el valor directo del toggle)
        if (property_exists($this, 'tableFilters')) {
            $candidates[] = $this->tableFilters['solo_pendientes'] ?? null;
        }
        // Fuente 3: intentar método helper interno si existiera en versión Filament
        if (method_exists($this, 'getTableFilterState')) {
            try { $candidates[] = $this->getTableFilterState('solo_pendientes'); } catch (\Throwable $e) { /* ignore */ }
        }

        $active = false;
        foreach ($candidates as $node) {
            if ($active) break;
            if (is_bool($node)) { $active = $node; break; }
            if (is_array($node)) {
                foreach (['value','isActive','state'] as $k) {
                    if (array_key_exists($k, $node)) { $active = (bool)$node[$k]; break 2; }
                }
            }
        }

        // Logging de diagnóstico sólo en entorno debug para no saturar logs en producción
        if (config('app.debug')) {
            try {
                Log::debug('ImprimirCredenciales: estado solo_pendientes', [
                    'candidates' => $candidates,
                    'resolved_active' => $active,
                ]);
            } catch (\Throwable $e) {}
        }

        return $active;
    }

    protected function getPendingRecordsFromFilter(): Collection
    {
        if (!$this->isSoloPendientesFilterActive()) return collect();
        // Reaplicar condiciones manualmente para imprimir aunque el filtro se ejecute también en la tabla
        $query = $this->buildBaseQuery();
        $tipo = $this->getTipoSeleccionado();
        return match ($tipo) {
            1 => $query->where(fn($w)=> $w->whereNull('prodoc_iCredencial')->orWhere('prodoc_iCredencial', false))->get(),
            2 => $query->where(fn($w)=> $w->whereNull('proadm_iCredencial')->orWhere('proadm_iCredencial', false))->get(),
            3 => $query->where(fn($w)=> $w->whereNull('proalu_iCredencial')->orWhere('proalu_iCredencial', false))->get(),
            default => collect(),
        };
    }

    // Extrae la lógica de impresión para reutilizar en acciones
    protected function handleImpresionForRecords(Collection $records)
    {
        // Ampliar el tiempo máximo de ejecución para evitar cortes al generar PDFs grandes
        try { @set_time_limit(300); } catch (\Throwable $e) {}
        if ($records->isEmpty()) {
            Notification::make()->title('Sin selección')->body('Seleccione al menos un registro.')->warning()->send();
            return null;
        }

        if (class_exists('Barryvdh\\Debugbar\\Facades\\Debugbar')) {
            try { \Barryvdh\Debugbar\Facades\Debugbar::disable(); } catch (\Throwable $e) {}
        }

    // Se eliminó el límite de lote (antes 40) para imprimir todos los registros en una sola ejecución
    $processRecords = $records;
    $more = 0; // Mantener variable por compatibilidad con mensaje previo (ya no se usa condicionalmente)

    $items = [];
    $idsDoc = [];$idsAdm = [];$idsAlu = [];
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
            // Acumular IDs por tipo para actualización masiva posterior
            if ($record instanceof ProcesoDocente) { $idsDoc[] = $record->getKey(); }
            elseif ($record instanceof ProcesoAdministrativo) { $idsAdm[] = $record->getKey(); }
            elseif ($record instanceof ProcesoAlumno) { $idsAlu[] = $record->getKey(); }
        }

    $inicio = (int) (data_get($this->data,'inicio_slot') ?? 1); $inicio = $inicio<1?1:($inicio>4?4:$inicio);
        if ($inicio>1) { $placeholders = array_fill(0,$inicio-1,null); $items = array_merge($placeholders,$items); }
        $pages = array_chunk($items,4);
        $fecha = ProcesoFecha::find($procesoFechaId = (data_get($this->data,'proceso_fecha_id') ?? null));
        $anvPath = $fecha?->profec_vcUrlAnverso ? storage_path('app/public/'.$fecha->profec_vcUrlAnverso) : public_path('storage/templates/anverso.jpg');
        $revPath = $fecha?->profec_vcUrlReverso ? storage_path('app/public/'.$fecha->profec_vcUrlReverso) : public_path('storage/templates/reverso.jpg');
        $sessionKeyAnv='plantilla_anv_'.$procesoFechaId; $sessionKeyRev='plantilla_rev_'.$procesoFechaId;
        $cachedAnv=session()->get($sessionKeyAnv); $cachedRev=session()->get($sessionKeyRev);
        $anvB64 = null; $revB64 = null;
        // Recalcular si no existe cache, si cambió la ruta o si el archivo fue actualizado
        if (file_exists($anvPath)) {
            $anvMtime = @filemtime($anvPath) ?: 0;
            if (is_array($cachedAnv) && ($cachedAnv['path'] ?? null) === $anvPath && ($cachedAnv['mtime'] ?? 0) === $anvMtime) {
                $anvB64 = $cachedAnv['b64'] ?? null;
            }
            if (!$anvB64) {
                $anvB64 = @base64_encode(file_get_contents($anvPath));
                session()->put($sessionKeyAnv, ['path'=>$anvPath,'mtime'=>$anvMtime,'b64'=>$anvB64]);
            }
        }
        if (file_exists($revPath)) {
            $revMtime = @filemtime($revPath) ?: 0;
            if (is_array($cachedRev) && ($cachedRev['path'] ?? null) === $revPath && ($cachedRev['mtime'] ?? 0) === $revMtime) {
                $revB64 = $cachedRev['b64'] ?? null;
            }
            if (!$revB64) {
                $revB64 = @base64_encode(file_get_contents($revPath));
                session()->put($sessionKeyRev, ['path'=>$revPath,'mtime'=>$revMtime,'b64'=>$revB64]);
            }
        }
    Log::info('Credenciales: renderizando HTML de bulk');
    Log::info('Credenciales: ids por tipo', [ 'docentes' => count($idsDoc), 'administrativos' => count($idsAdm), 'alumnos' => count($idsAlu) ]);
        $html = view('credenciales.bulk',[ 'pages'=>$pages,'generado'=>now(),'anverso'=>$anvB64,'reverso'=>$revB64,'offsets'=>[
            'front'=>['x'=>(float)(data_get($this->data,'offset_x_front') ?? 0),'y'=>(float)(data_get($this->data,'offset_y_front') ?? 0)],
            'back'=>['x'=>(float)(data_get($this->data,'offset_x_back') ?? 0),'y'=>(float)(data_get($this->data,'offset_y_back') ?? 0)],
        ],'debug'=>(bool)(data_get($this->data,'debug_grid') ?? false),])->render();

        // Generar PDF con manejo de errores explícito
        try {
            Log::info('Credenciales: iniciando generación de PDF');
            $pdf = Pdf::loadHTML($html)->setPaper('a4','portrait');
            $pdfBinary = $pdf->output();
            if (empty($pdfBinary)) {
                throw new \RuntimeException('El PDF se generó vacío.');
            }
        } catch (\Throwable $e) {
            Log::error('Error generando PDF de credenciales: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            Notification::make()->title('Error al generar PDF')->body('Revise las plantillas y datos. '.$e->getMessage())->danger()->send();
            // Avisar al front para cerrar la pestaña placeholder si existe
            $this->dispatch('pdf-error');
            $this->pendingPdfUrl = null;
            return null;
        }

        // Guardar el PDF en disco público y abrir en una nueva pestaña
        $dir = 'credenciales/tmp';
        $filename = 'credenciales_lote_'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(3)).'.pdf';
        $path = $dir.'/'.$filename;
        try {
            Log::info('Credenciales: guardando PDF en '.$path);
            Storage::disk('public')->put($path, $pdfBinary);
        } catch (\Throwable $e) {
            Log::error('Error guardando PDF de credenciales: '.$e->getMessage());
            Notification::make()->title('Error al guardar PDF')->body($e->getMessage())->danger()->send();
            // Avisar al front para cerrar la pestaña placeholder
            $this->dispatch('pdf-error');
            $this->pendingPdfUrl = null;
            return null;
        }

        // Emitir primero el evento al navegador para abrir el PDF sin esperar el marcado en BD
        $url = Storage::url($path);
        Log::info('Credenciales: URL pública del PDF '.$url);
        Log::info('Credenciales: emitiendo open-pdf');
        // Asignar también a una propiedad pública como respaldo (Alpine @entangle)
        $this->pendingPdfUrl = $url;
        // Guardar además un archivo de fallback por usuario para que la pestaña placeholder pueda consultarlo si el evento falla
        try {
            $uid = auth()->id() ?: 'guest';
                $fallbackDir = 'credenciales/fallbacks';
                if (!Storage::disk('public')->exists($fallbackDir)) {
                    Storage::disk('public')->makeDirectory($fallbackDir);
                }
                $fallbackPath = $fallbackDir.'/pdf_url_user_'.$uid.'.txt';
                Storage::disk('public')->put($fallbackPath, $url);
                Log::info('Credenciales: fallback URL escrita en '.$fallbackPath);
        } catch (\Throwable $e) { Log::warning('No se pudo escribir fallback URL: '.$e->getMessage()); }
    $this->dispatch('open-pdf', url: $url);

        // Luego marcar como impreso, con actualizaciones masivas por tipo para evitar timeouts
        $updated = 0;
        try {
            if (!empty($idsDoc)) {
                $docTable = (new \App\Models\ProcesoDocente)->getTable();
                $cols = [ 'prodoc_iCredencial' => true, 'prodoc_dtFechaImpresion' => now() ];
                if (\Schema::hasColumn($docTable, 'user_idImpresion')) { $cols['user_idImpresion'] = auth()->id(); }
                if (\Schema::hasColumn($docTable, 'IpImpresion')) { $cols['IpImpresion'] = request()->ip(); }
                $updated += \App\Models\ProcesoDocente::whereIn((new \App\Models\ProcesoDocente)->getKeyName(), $idsDoc)->update($cols);
            }
            if (!empty($idsAdm)) {
                $admTable = (new \App\Models\ProcesoAdministrativo)->getTable();
                $cols = [ 'proadm_iCredencial' => true, 'proadm_dtFechaImpresion' => now() ];
                if (\Schema::hasColumn($admTable, 'user_idImpresion')) { $cols['user_idImpresion'] = auth()->id(); }
                if (\Schema::hasColumn($admTable, 'IpImpresion')) { $cols['IpImpresion'] = request()->ip(); }
                $updated += \App\Models\ProcesoAdministrativo::whereIn((new \App\Models\ProcesoAdministrativo)->getKeyName(), $idsAdm)->update($cols);
            }
            if (!empty($idsAlu)) {
                $aluTable = (new \App\Models\ProcesoAlumno)->getTable();
                $cols = [ 'proalu_iCredencial' => true, 'proalu_dtFechaImpresion' => now() ];
                if (\Schema::hasColumn($aluTable, 'user_idImpresion')) { $cols['user_idImpresion'] = auth()->id(); }
                if (\Schema::hasColumn($aluTable, 'IpImpresion')) { $cols['IpImpresion'] = request()->ip(); }
                $updated += \App\Models\ProcesoAlumno::whereIn((new \App\Models\ProcesoAlumno)->getKeyName(), $idsAlu)->update($cols);
            }
        } catch (\Throwable $e) {
            Log::error('Error en actualización masiva de marcados: '.$e->getMessage());
            Notification::make()->title('Marcado de impresas falló')->body($e->getMessage())->warning()->send();
        }

        // Refrescar tabla al final
        try { $this->dispatch('$refresh'); } catch (\Throwable $e) { Log::warning('Error refrescando tabla: '.$e->getMessage()); }
        Log::info('Credenciales: registros marcados como impresos = '.$updated);
        $msg = 'Se imprimieron '.$updated.' credenciales.';
    Notification::make()->title('PDF generado')->body($msg)->success()->send();
    // Evitar reabrir el mismo PDF si el usuario vuelve a imprimir
    $this->pendingPdfUrl = null;
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
