<?php

namespace App\Filament\Pages;

use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\DetachAction; 
use App\Models\ProcesoFecha;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use App\Models\LocalesMaestro;
use App\Models\Proceso;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;



class AsignarLocales extends Page implements HasForms, HasTable
{

    use InteractsWithForms;
    use InteractsWithTable;
    use HasPageShield;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static string $view = 'filament.pages.asignar-locales';
    protected static ?string $navigationLabel = 'Agregar Locales';
      protected static ?string $navigationGroup = 'Administración de Locales';
 

  

   public ?array $data = [];

  
    public function mount(): void
    {
        // Esto inicializa el formulario y su array 'data' con valores por defecto (vacíos).
        $this->form->fill();
    }

    public function table(Table $table): Table
{
    return $table
        // La consulta de la tabla es la parte más importante.
        // Debe obtener los locales relacionados con la fecha seleccionada en el formulario.
        ->query(function () { // <-- Opcional: hemos quitado el return type para más flexibilidad
            $data = $this->form->getState();

            // Verificamos si la clave existe y tiene un valor
            if (empty($data['proceso_fecha_id'])) {
                // SI NO HAY FECHA SELECCIONADA:
                // Devolvemos una consulta del modelo relacionado que a propósito no
                // devolverá ningún resultado. Es una forma segura de mostrar una tabla vacía.
                return LocalesMaestro::query()->whereRaw('1 = 0');
            }

            // SI HAY UNA FECHA SELECCIONADA:
            // Buscamos la fecha y devolvemos la relación como antes.
            $fecha = ProcesoFecha::find($data['proceso_fecha_id']);

            return $fecha->localesMaestro();
        })
        ->heading('Locales Ya Asignados')
        ->columns([
            TextColumn::make('locma_iCodigo')
                ->label('Código del Local'),
            TextColumn::make('locma_vcNombre')
                ->label('Nombre del Local'),
        ])
        ->actions([
           
            // Es una acción pre-construida para relaciones muchos a muchos.
            DetachAction::make(),
        ])
        // No necesitamos acciones en el header o bulk actions para este caso
        ->headerActions([])
    ->bulkActions([])
    ->defaultPaginationPageOption(25)
    ->paginationPageOptions([10,25,50,100]);
}

    // Define la estructura del formulario 
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Seleccionar Proceso y Locales')
                    ->description('Elige un proceso abierto, fecha activa y luego selecciona todos los locales que deseas asociar.')
                    ->schema([
                        Select::make('proceso_id')
                            ->label('Proceso Abierto')
                            ->options(fn()=>Proceso::where('pro_iAbierto', true)->orderBy('pro_vcNombre')->pluck('pro_vcNombre','pro_iCodigo'))
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function(callable $set){ $set('proceso_fecha_id', null); })
                            ->required(),
                        Select::make('proceso_fecha_id')
                            ->label('Fecha Activa del Proceso')
                            ->options(function(callable $get){
                                $procesoId = $get('proceso_id');
                                if(!$procesoId) return [];
                                return ProcesoFecha::where('pro_iCodigo',$procesoId)
                                    ->where('profec_iActivo', true)
                                    ->orderBy('profec_dFecha')
                                    ->pluck('profec_dFecha','profec_iCodigo');
                            })
                            ->searchable()
                            ->reactive()
                            ->required(),

                       CheckboxList::make('locales_maestro_ids')
                            ->label('Seleccione los Locales Disponibles a Asignar')
                            ->options(function (callable $get): array {
                                $fechaId = $get('proceso_fecha_id');
                                if (!$fechaId) {
                                    return [];
                                }
                                $localesYaAsignadosIds = ProcesoFecha::find($fechaId)
                                    ->localesMaestro()
                                    ->pluck('localMaestro.locma_iCodigo')
                                    ->toArray();
                                return LocalesMaestro::whereNotIn('locma_iCodigo', $localesYaAsignadosIds)
                                    ->pluck('locma_vcNombre', 'locma_iCodigo')
                                    ->toArray();
                            })
                            ->searchable()
                            ->columns(3), 
                           // ->required(),
                    ]),
            ])
           ->statePath('data');
           
           
    }

    protected function getSaveAction(): Action
    {
        return Action::make('save')
            ->label('Asignar Locales Seleccionados')
            ->submit('save'); // Esto vincula el botón a un método 'save' en esta clase
    }

    

    // Implementa la lógica de guardado
    public function save(): void
    {
        // Obtiene los datos validados del formulario
        $data = $this->form->getState();

        // --- PASO DE VALIDACIÓN MANUAL ---
    // Verificamos si el array de locales está vacío o no existe.
        if (empty($data['locales_maestro_ids'])) {
            // Si está vacío, enviamos una notificación de error y detenemos la ejecución.
            Notification::make()
                ->title('Validación Fallida')
                ->body('Debes seleccionar al menos un local para asignar.')
                ->danger()
                ->send();

            return; 
        }

        // Busca el modelo ProcesoFecha seleccionado
        $fecha = ProcesoFecha::find($data['proceso_fecha_id']);
        if (!$fecha) {
            Notification::make()->title('Error')->body('La fecha seleccionada no es válida.')->danger()->send();
            return;
        }

        // Usa syncWithoutDetaching para añadir las nuevas relaciones
        // sin eliminar las que ya existían.
        $fecha->localesMaestro()->syncWithoutDetaching($data['locales_maestro_ids']);

        // Muestra una notificación de éxito
        Notification::make()
            ->title('Locales asignados con éxito')
            ->success()
            ->send();

        // Mantener proceso y fecha seleccionados; limpiar solo lista de locales seleccionados
        $this->form->fill([
            'proceso_id' => $data['proceso_id'] ?? null,
            'proceso_fecha_id' => $data['proceso_fecha_id'] ?? null,
            'locales_maestro_ids' => [],
        ]);
    }
}


