<?php

namespace App\Livewire;

use App\Models\ProcesoFecha;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\On; // <-- Importante para escuchar eventos
use Livewire\Component;

class LocalesAsignadosTable extends Component
{
    use InteractsWithForms;
    use InteractsWithTable;

    public ?ProcesoFecha $fecha = null; // Recibirá la fecha activa

    // Este listener se activará cuando la página principal envíe el evento 'fechaSeleccionada'
    #[On('fechaSeleccionada')]
    public function actualizarFecha($fechaId): void
    {
        $this->fecha = ProcesoFecha::find($fechaId);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                
                $this->fecha ? $this->fecha->localesMaestro() : LocalesMaestro::query()->whereRaw('1 = 0') // Si no hay fecha, no muestra nada
            )
            ->heading('Locales Ya Asignados')
            ->columns([
                TextColumn::make('nombre')->label('Nombre del Local'),
                TextColumn::make('direccion')->label('Dirección'),
            ])
            ->actions([
                DetachAction::make(),
            ])
            ->headerActions([])
            ->bulkActions([]);
    }

    public function render()
    {
        return view('livewire.locales-asignados-table');
    }
}

