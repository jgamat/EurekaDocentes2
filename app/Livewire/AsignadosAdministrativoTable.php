<?php

namespace App\Livewire;

use App\Models\ProcesoAdministrativo;
use App\Models\LocalCargo;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Component;

class AsignadosAdministrativoTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

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
                if (!$this->procesoFechaId || !$this->localId || !$this->experienciaAdmisionId) {
                    return ProcesoAdministrativo::query()->whereRaw('1 = 0');
                }

                return ProcesoAdministrativo::query()
                    ->with(['administrativo', 'experienciaAdmision.maestro', 'local.localesMaestro', 'procesoFecha', 'usuario'])
                    ->where('profec_iCodigo', $this->procesoFechaId)
                    ->where('loc_iCodigo', $this->localId)
                    ->where('expadm_iCodigo', $this->experienciaAdmisionId)
                    ->where('proadm_iAsignacion', true);
            })
            ->heading(fn () => 'Administrativos Ya Asignados ('.$this->getAsignadosCount().')')
            ->striped()
            ->searchable()
            ->searchPlaceholder('Buscar por nombre o DNI')
            ->columns([
                TextColumn::make('administrativo.adm_vcNombres')
                    ->label('Nombres')
                    ->searchable(['administrativo.adm_vcNombres']),
                TextColumn::make('administrativo.adm_vcDni')
                    ->label('DNI')
                    ->searchable(['administrativo.adm_vcDni']),
                TextColumn::make('administrativo.adm_vcCodigo')->label('Código'),
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
                        return $query->whereHas('administrativo', function (Builder $q) use ($data) {
                            $q->where('adm_vcDni', 'like', '%'.$data['dni'].'%');
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
                        return $query->whereHas('administrativo', function (Builder $q) use ($data) {
                            $q->where('adm_vcNombres', 'like', '%'.$data['nombre'].'%');
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
                            'nombres' => $record->administrativo?->adm_vcNombres ?? '-',
                            'dni' => $record->administrativo?->adm_vcDni ?? '-',
                            'codigo' => $record->administrativo?->adm_vcCodigo ?? '-',
                            'cargo' => $record->experienciaAdmision?->maestro?->expadmma_vcNombre ?? '-',
                            'local' => $record->local?->localesMaestro?->locma_vcNombre ?? '-',
                            'fecha' => $record->procesoFecha?->profec_dFecha ?? '-',
                            'usuario' => $record->usuario?->name ?? '-',
                            'fecha_asignacion' => $record->proadm_dtFechaAsignacion ?? '-',
                        ]);
                    })
                    ->form([
                        \Filament\Forms\Components\TextInput::make('nombres')->label('Administrativo')->disabled(),
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

                        $localCargo = LocalCargo::where('loc_iCodigo', $record->loc_iCodigo)
                            ->where('expadm_iCodigo', $record->expadm_iCodigo)
                            ->first();

                        $record->update([
                            'proadm_iAsignacion' => false,
                            'proadm_dtFechaDesasignacion' => now(),
                            'loc_iCodigo' => null,
                            'expadm_iCodigo' => null,
                            'proadm_dtFechaAsignacion' => null,
                        ]);

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
        return ProcesoAdministrativo::query()
            ->where('profec_iCodigo', $this->procesoFechaId)
            ->where('loc_iCodigo', $this->localId)
            ->where('expadm_iCodigo', $this->experienciaAdmisionId)
            ->where('proadm_iAsignacion', true)
            ->count();
    }

    public function render()
    {
        return view('livewire.asignados-administrativo-table');
    }
}
