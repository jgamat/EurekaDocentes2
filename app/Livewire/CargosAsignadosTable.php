<?php

namespace App\Livewire;
use App\Models\Locales;
use App\Models\ExperienciaAdmision;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Database\Eloquent\Model;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;




class CargosAsignadosTable extends Component implements HasForms, HasTable
{

    use InteractsWithForms;
    use InteractsWithTable;

    public ?Locales $local = null; // Recibirá el modelo Local completo

    // Este listener se activa cuando la página principal emite el evento 'cargosActualizados'
    #[On('cargosActualizados')]
    public function actualizarLocalId($localId): void
    {
        $this->local = Locales::find($localId);
    }

    public function table(Table $table): Table
{
    return $table
        ->query(function () {
            
            if (!$this->local) {
                return ExperienciaAdmision::query()->whereRaw('1 = 0');
            }

            return ExperienciaAdmision::query()
                ->join('localcargo', 'experienciaadmision.expadm_iCodigo', '=', 'localcargo.expadm_iCodigo')     
                ->join('experienciaadmisionMaestro', 'experienciaadmision.expadmma_iCodigo', '=', 'experienciaadmisionMaestro.expadmma_iCodigo')
                ->where('localcargo.loc_iCodigo', $this->local->loc_iCodigo)
                
                ->select(
                  'experienciaadmision.*', 
                  'experienciaadmisionMaestro.expadmma_vcNombre', 
                  'localcargo.loccar_iVacante as pivot_loccar_iVacante',
                  'localcargo.loccar_iOcupado as pivot_loccar_iOcupado'
              );
       
        
        
        })
        ->heading('Cargos Ya Asignados a este Local')
        ->columns([
            TextColumn::make('expadmma_vcNombre')->label('Nombre del Cargo'),

            
            TextColumn::make('pivot_loccar_iVacante')->label('Vacantes'),
            TextColumn::make('pivot_loccar_iOcupado')->label('Ocupados'),
        ])
        ->actions([
            
          EditAction::make()
                ->modalHeading(fn ($record) => 'Editar Asignación para: ' . $record->maestro->expadmma_vcNombre)
                ->modalButton('Guardar Cambios')
                ->fillForm(function (Model $record): array {
                
                    return [
                        'loccar_iVacante' => $record->pivot_loccar_iVacante,
                        'loccar_iOcupado' => $record->pivot_loccar_iOcupado,
                    ];
                })
              
                ->form([
                    TextInput::make('loccar_iVacante')
                        ->label('Nuevas Vacantes')
                        ->required()
                        ->numeric(), // Quitamos la regla compleja de aquí

                    TextInput::make('loccar_iOcupado')
                        ->label('Total Ocupados (Actual)')
                        ->numeric()
                        ->disabled(),
                ])
               
                ->action(function (array $data, Model $record): void {
                    
                  
                    $ocupados = $record->pivot_loccar_iOcupado;
                    $nuevasVacantes = $data['loccar_iVacante'];

                    if ($nuevasVacantes < $ocupados) {
                        
                        Notification::make()
                            ->title('Error de Validación')
                            ->body("Las vacantes ({$nuevasVacantes}) no pueden ser menores que el total de ocupados ({$ocupados}).")
                            ->danger()
                            ->send();
                        return; // Detiene la acción aquí
                    }

                    DB::table('localcargo')
       
                      ->where('loc_iCodigo', $this->local->loc_iCodigo)
        
                      ->where('expadm_iCodigo', $record->expadm_iCodigo)
                      ->update([
                         'loccar_iVacante' => $nuevasVacantes,
                          'updated_at' => now(),
                     ]);

                    Notification::make()->title('Asignación actualizada')->success()->send();
                }),
           
                DetachAction::make()
                 ->action(function ($record): void {            
                $this->local->experienciaAdmision()->detach($record);
             }),
        ]);
}
    public function render()
    {
        return view('livewire.cargos-asignados-table');
    }
}
