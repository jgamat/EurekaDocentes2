<?php

namespace App\Livewire;

use App\Models\LocalCargo;
use App\Models\ProcesoAlumno;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Component;

class AsignadosAlumnoTable extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions, InteractsWithForms, InteractsWithTable;

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
                if (! $this->procesoFechaId || ! $this->localId || ! $this->experienciaAdmisionId) {
                    return ProcesoAlumno::query()->whereRaw('1 = 0');
                }

                return ProcesoAlumno::query()
                    ->with(['alumno', 'experienciaAdmision.maestro', 'local.localesMaestro', 'procesoFecha', 'usuario'])
                    ->where('profec_iCodigo', $this->procesoFechaId)
                    ->where('loc_iCodigo', $this->localId)
                    ->where('expadm_iCodigo', $this->experienciaAdmisionId)
                    ->where('proalu_iAsignacion', true);
            })
            ->heading(fn () => 'Alumnos Ya Asignados ('.$this->getAsignadosCount().')')
            ->striped()
            ->searchable()
            ->searchPlaceholder('Buscar por nombre, DNI o código')
            ->columns([
                TextColumn::make('alumno.nombre_completo')
                    ->label('Nombre Completo')
                    ->searchable(['alumno.alu_vcNombre', 'alumno.alu_vcPaterno', 'alumno.alu_vcMaterno']),
                TextColumn::make('alumno.alu_vcDni')
                    ->label('DNI')
                    ->searchable(['alumno.alu_vcDni']),
                TextColumn::make('alumno.alu_vcCodigo')
                    ->label('Código')
                    ->searchable(['alumno.alu_vcCodigo']),
            ])
            ->filters([
                Filter::make('dni')
                    ->label('DNI')
                    ->form([
                        TextInput::make('dni')->label('DNI')->placeholder('Ej. 12345678'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! filled($data['dni'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas('alumno', function (Builder $q) use ($data) {
                            $q->where('alu_vcDni', 'like', '%'.$data['dni'].'%');
                        });
                    }),
                Filter::make('codigo')
                    ->label('Código')
                    ->form([
                        TextInput::make('codigo')->label('Código')->placeholder('Código del alumno'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! filled($data['codigo'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas('alumno', function (Builder $q) use ($data) {
                            $q->where('alu_vcCodigo', 'like', '%'.$data['codigo'].'%');
                        });
                    }),
                Filter::make('nombre')
                    ->label('Nombre')
                    ->form([
                        TextInput::make('nombre')->label('Nombre')->placeholder('Parte del nombre'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! filled($data['nombre'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas('alumno', function (Builder $q) use ($data) {
                            $q->where('alu_vcNombre', 'like', '%'.$data['nombre'].'%')
                                ->orWhere('alu_vcPaterno', 'like', '%'.$data['nombre'].'%')
                                ->orWhere('alu_vcMaterno', 'like', '%'.$data['nombre'].'%');
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
                            'alumno' => $record->alumno?->nombre_completo ?? '-',
                            'dni' => $record->alumno?->alu_vcDni ?? '-',
                            'codigo' => $record->alumno?->alu_vcCodigo ?? '-',
                            'cargo' => $record->experienciaAdmision?->maestro?->expadmma_vcNombre ?? '-',
                            'local' => $record->local?->localesMaestro?->locma_vcNombre ?? '-',
                            'fecha' => $record->procesoFecha?->profec_dFecha ?? '-',
                            'usuario' => $record->usuario?->name ?? '-',
                            'fecha_asignacion' => $record->proalu_dtFechaAsignacion ?? '-',
                        ]);
                    })
                    ->form([
                        TextInput::make('alumno')->label('Alumno')->disabled(),
                        TextInput::make('dni')->label('DNI')->disabled(),
                        TextInput::make('codigo')->label('Código')->disabled(),
                        TextInput::make('cargo')->label('Cargo')->disabled(),
                        TextInput::make('local')->label('Local')->disabled(),
                        TextInput::make('fecha')->label('Fecha')->disabled(),
                        TextInput::make('usuario')->label('Usuario Asignador')->disabled(),
                        TextInput::make('fecha_asignacion')->label('Fecha de Asignación')->disabled(),
                    ]),
                Action::make('desasignar')
                    ->label('Desasignar')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->action(function ($record, $livewire) {
                        if ($record->user_id !== auth()->id()) {
                            Notification::make()
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
                            'proalu_iAsignacion' => false,
                            'proalu_dtFechaDesasignacion' => now(),
                            'loc_iCodigo' => null,
                            'expadm_iCodigo' => null,
                            'proalu_dtFechaAsignacion' => null,
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
        if (! $this->procesoFechaId || ! $this->localId || ! $this->experienciaAdmisionId) {
            return 0;
        }

        return ProcesoAlumno::query()
            ->where('profec_iCodigo', $this->procesoFechaId)
            ->where('loc_iCodigo', $this->localId)
            ->where('expadm_iCodigo', $this->experienciaAdmisionId)
            ->where('proalu_iAsignacion', true)
            ->count();
    }

    public function render()
    {
        return view('livewire.asignados-alumno-table');
    }
}
