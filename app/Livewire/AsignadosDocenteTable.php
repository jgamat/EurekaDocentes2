<?php

namespace App\Livewire;

use App\Models\ProcesoDocente;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Filament\Tables\Actions\Action;

class AsignadosDocenteTable extends Component implements HasForms, HasTable

{
    use InteractsWithForms, InteractsWithTable;

    // Propiedades para guardar el contexto de la selección
    public ?int $procesoFechaId = null;
    public ?int $localId = null;
    public ?int $experienciaAdmisionId = null;
    

   
    #[On('contextoActualizado')]
    public function actualizarContexto($procesoFechaId, $localId, $experienciaAdmisionId): void
    {
        $this->procesoFechaId = $procesoFechaId;
        $this->localId = $localId;
        $this->experienciaAdmisionId = $experienciaAdmisionId;
    }

   

   public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                // 3. La consulta ahora depende de que los 3 IDs existan
                if (!$this->procesoFechaId || !$this->localId || !$this->experienciaAdmisionId) {
                    return ProcesoDocente::query()->whereRaw('1 = 0');
                }

                // 4. La consulta ahora filtra por las 3 claves foráneas en la tabla 'proceso_docentes'
                $query = ProcesoDocente::query()
                    ->with(['docente', 'experienciaAdmision.maestro', 'local.localesMaestro', 'procesoFecha', 'usuario'])
                    ->where('profec_iCodigo', $this->procesoFechaId)
                    ->where('loc_iCodigo', $this->localId)
                    ->where('prodoc_iAsignacion', true)
                    ->where('expadm_iCodigo', $this->experienciaAdmisionId);

                // Restringir por rol del usuario asignador: solo mostrar registros asignados por usuarios
                // que comparten algún rol con el usuario actual, excepto roles privilegiados
                $user = auth()->user();
                if ($user && !$user->hasAnyRole(['Economia', 'Info', 'super_admin'])) {
                    $roles = $user->getRoleNames();
                    if ($roles && $roles->isNotEmpty()) {
                        $query->whereHas('usuario.roles', function ($q) use ($roles) {
                            $q->whereIn('name', $roles);
                        });
                    } else {
                        // Si no tiene roles, no mostrar nada por seguridad
                        $query->whereRaw('1 = 0');
                    }
                }
                return $query;
                    
            })
            ->heading(fn () => 'Docentes Ya Asignados ('.$this->getAsignadosCount().')')
            ->striped()
            ->searchable()
            ->searchPlaceholder('Buscar por nombre o DNI')
            ->columns([
                // Usamos la notación de punto para mostrar los datos del docente relacionado
                TextColumn::make('docente.nombre_completo')
                    ->label('Nombre Completo')
                    ->searchable(['docente.doc_vcNombre', 'docente.doc_vcPaterno', 'docente.doc_vcMaterno']),
                TextColumn::make('docente.doc_vcDni')
                    ->label('DNI')
                    ->searchable(['docente.doc_vcDni']),
                TextColumn::make('docente.doc_vcCodigo')
                    ->label('Código')
                    ->searchable(['docente.doc_vcCodigo']),
            ])
            ->filters([
                Filter::make('dni')
                    ->label('DNI')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('dni')->label('DNI')->placeholder('Ej. 12345678'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!filled($data['dni'] ?? null)) {
                            return $query;
                        }
                        return $query->whereHas('docente', function (Builder $q) use ($data) {
                            $q->where('doc_vcDni', 'like', '%'.$data['dni'].'%');
                        });
                    }),
                Filter::make('codigo')
                    ->label('Código')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('codigo')->label('Código')->placeholder('Código del docente'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!filled($data['codigo'] ?? null)) {
                            return $query;
                        }
                        return $query->whereHas('docente', function (Builder $q) use ($data) {
                            $q->where('doc_vcCodigo', 'like', '%'.$data['codigo'].'%');
                        });
                    }),
                Filter::make('nombre')
                    ->label('Nombre')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('nombre')->label('Nombre')->placeholder('Parte del nombre'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!filled($data['nombre'] ?? null)) {
                            return $query;
                        }
                        return $query->whereHas('docente', function (Builder $q) use ($data) {
                            $q->where('doc_vcNombre', 'like', '%'.$data['nombre'].'%')
                              ->orWhere('doc_vcPaterno', 'like', '%'.$data['nombre'].'%')
                              ->orWhere('doc_vcMaterno', 'like', '%'.$data['nombre'].'%');
                        });
                    }),
            ])
            ->actions([

                Action::make('detalle')
                ->label('Detalle')
                ->icon('heroicon-o-eye')
                ->modalHeading('Detalle de Asignación')
                ->modalButton('Cerrar')
                ->modalWidth('2xl')
                ->mountUsing(function ($form, $record) {
                    $form->fill([
                        'docente' => $record->docente?->nombre_completo ?? '-',
                        'dni' => $record->docente?->doc_vcDni ?? '-',
                        'codigo' => $record->docente?->doc_vcCodigo ?? '-',
                        'cargo' => $record->experienciaAdmision?->maestro?->expadmma_vcNombre ?? '-',
                        'local' => $record->local?->localesMaestro?->locma_vcNombre ?? '-',
                        'fecha' => $record->procesoFecha?->profec_dFecha ?? '-',
                        'usuario' => $record->usuario?->name ?? '-',
                        'fecha_asignacion' => $record->prodoc_dtFechaAsignacion ?? '-',
                    ]);
                })
                ->form([
                    \Filament\Forms\Components\TextInput::make('docente')->label('Docente')->disabled(),
                    \Filament\Forms\Components\TextInput::make('dni')->label('DNI')->disabled(),
                    \Filament\Forms\Components\TextInput::make('codigo')->label('Código')->disabled(),
                    \Filament\Forms\Components\TextInput::make('cargo')->label('Cargo')->disabled(),
                    \Filament\Forms\Components\TextInput::make('local')->label('Local')->disabled(),
                    \Filament\Forms\Components\TextInput::make('fecha')->label('Fecha')->disabled(),
                    \Filament\Forms\Components\TextInput::make('usuario')->label('Usuario Asignador')->disabled(),
                    \Filament\Forms\Components\TextInput::make('fecha_asignacion')->label('Fecha de Asignación')->disabled(),
                ]),
                Action::make('desasignar')
                    ->label('Desasignar')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn ($record) => (int) ($record->user_id ?? 0) === (int) (auth()->id() ?? 0))
                    ->requiresConfirmation()
                    ->color('danger')
                    ->action(function ($record, $livewire) {
                         if ($record->user_id !== auth()->id()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Acción no permitida')
                                ->body('Solo el usuario que realizó la asignación puede desasignar este registro.')
                                ->danger()
                                ->send();
                            return;
                        }
                         $localCargo = $record->localCargo;
                        $record->update(['prodoc_iAsignacion' => false,
                                        'prodoc_dtFechaDesasignacion' => now(),
                                        'loc_iCodigo' => null,
                                        'expadm_iCodigo' => null,
                                        'prodoc_dtFechaAsignacion' => null]);
                        
                        if ($localCargo && $localCargo->loccar_iOcupado > 0) {
                            $localCargo->decrement('loccar_iOcupado');
                           
                        }
                         $livewire->dispatch(
                            'contextoActualizado',
                            procesoFechaId: $this->procesoFechaId,
                            localId: $this->localId,
                            experienciaAdmisionId: $this->experienciaAdmisionId
                        );
                }),
            ]);
    }

    protected function getAsignadosCount(): int
    {
        if (!$this->procesoFechaId || !$this->localId || !$this->experienciaAdmisionId) {
            return 0;
        }
        $query = ProcesoDocente::query()
            ->where('profec_iCodigo', $this->procesoFechaId)
            ->where('loc_iCodigo', $this->localId)
            ->where('expadm_iCodigo', $this->experienciaAdmisionId)
            ->where('prodoc_iAsignacion', true);

        $user = auth()->user();
        if ($user && !$user->hasAnyRole(['Economia', 'Info', 'super_admin'])) {
            $roles = $user->getRoleNames();
            if ($roles && $roles->isNotEmpty()) {
                $query->whereHas('usuario.roles', function ($q) use ($roles) {
                    $q->whereIn('name', $roles);
                });
            } else {
                return 0;
            }
        }
        return $query->count();
    }

    public function render()
    {
        return view('livewire.asignados-docente-table');
    }
}
