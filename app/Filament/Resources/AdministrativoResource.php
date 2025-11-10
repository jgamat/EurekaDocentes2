<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdministrativoResource\Pages;
use App\Filament\Resources\AdministrativoResource\RelationManagers;
use App\Models\Administrativo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Grid;
use Illuminate\Support\Facades\Storage;
use App\Models\Dependencia;
use App\Models\Categoria;
use App\Models\Condicion;
use App\Models\Estado;
use App\Models\Tipo;

class AdministrativoResource extends Resource
{
    protected static ?string $model = Administrativo::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
          protected static ?string $navigationGroup = 'Administrativos';
           protected static ?string $pluralModelLabel = 'Consulta de Administrativos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('adm_vcDni')
                        ->label('DNI')
                        ->required()
                        ->maxLength(15)
                        ->length(8)
                        ->numeric()
                        ->unique(table: 'administrativo', column: 'adm_vcDni', ignoreRecord: true)
                        ->validationAttribute('DNI')
                        ->helperText('Debe ser único. Ingrese 8 dígitos.')
                        ->validationMessages([
                            'unique' => 'El DNI ya está registrado.',
                            'length' => 'El DNI debe tener exactamente 8 dígitos.',
                            'numeric' => 'El DNI solo debe contener números.',
                        ]),
                    Forms\Components\TextInput::make('adm_vcCodigo')
                        ->label('Código')
                        ->maxLength(30),
                    Forms\Components\TextInput::make('adm_vcNombres')
                        ->label('Apellidos y Nombres')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('dep_iCodigo')
                        ->label('Dependencia')
                        ->options(fn()=>Dependencia::orderBy('dep_vcNombre')->pluck('dep_vcNombre','dep_iCodigo'))
                        ->searchable(),
                    Forms\Components\Select::make('cat_iCodigo')
                        ->label('Categoría')
                        ->options(fn()=>Categoria::orderBy('cat_vcNombre')->pluck('cat_vcNombre','cat_iCodigo'))
                        ->searchable(),
                    Forms\Components\Select::make('con_iCodigo')
                        ->label('Condición')
                        ->options(fn()=>Condicion::orderBy('con_vcNombre')->pluck('con_vcNombre','con_iCodigo'))
                        ->searchable(),
                    Forms\Components\Select::make('est_iCodigo')
                        ->label('Estado')
                        ->options(fn()=>Estado::orderBy('est_vcNombre')->pluck('est_vcNombre','est_iCodigo'))
                        ->searchable(),
                    Forms\Components\Select::make('tipo_iCodigo')
                        ->label('Tipo')
                        ->options(function(){
                            $permitidos = config('administrativos.tipos_permitidos_creacion');
                            return Tipo::whereIn('tipo_iCodigo',$permitidos)
                                ->orderBy('tipo_vcNombre')
                                ->pluck('tipo_vcNombre','tipo_iCodigo');
                        })
                        ->searchable()
                        ->required()
                        ->validationMessages([
                            'required' => 'El campo Tipo de Administrativo es obligatorio.',
                        ])
                        ->helperText(function(){
                           
                           
                            return 'Seleccione correctamente el tipo de administrativo para evitar errores con la planilla.';
                        }),
                    Forms\Components\TextInput::make('adm_vcCelular')
                        ->label('Celular')
                        ->maxLength(30),
                    Forms\Components\TextInput::make('adm_vcTelefono')
                        ->label('Teléfono')
                        ->maxLength(30),
                    Forms\Components\DatePicker::make('adm_dNacimiento')
                        ->label('Nacimiento')
                        ->native(false),
                    Forms\Components\TextInput::make('adm_vcEmailPersonal')
                        ->label('Email Personal')
                        ->email(),
                    Forms\Components\TextInput::make('adm_vcEmailUNMSM')
                        ->label('Email UNMSM')
                        ->email(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Administrativo::with([
                'dependencia',
                'asignaciones.procesoFecha.proceso',
                'asignaciones.local.localesMaestro',
                'asignaciones.experienciaAdmision.maestro',
            ]))
            ->columns([
                TextColumn::make('adm_vcDni')
                ->label('DNI')
                ->searchable()
                ->sortable(),

            TextColumn::make('adm_vcNombres')
                ->label('APELLIDOS Y NOMBRES')
                ->searchable()
                ->sortable(),

            TextColumn::make('dependencia.dep_vcNombre')
                ->label('DEPENDENCIA')
                ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->modalHeading('Editar Administrativo')
                    ->mutateFormDataUsing(function(array $data): array { return $data; })
                    ->successNotificationTitle('Administrativo actualizado')
                    ->form([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('adm_vcDni')->label('DNI')->disabled(),
                            Forms\Components\TextInput::make('adm_vcCodigo')->label('Código')->maxLength(30),
                            Forms\Components\TextInput::make('adm_vcNombres')->label('Apellidos y Nombres')->required(),
                            Forms\Components\Select::make('dep_iCodigo')->label('Dependencia')->options(fn()=>Dependencia::orderBy('dep_vcNombre')->pluck('dep_vcNombre','dep_iCodigo'))->searchable(),
                            Forms\Components\Select::make('cat_iCodigo')->label('Categoría')->options(fn()=>Categoria::orderBy('cat_vcNombre')->pluck('cat_vcNombre','cat_iCodigo'))->searchable(),
                            Forms\Components\Select::make('con_iCodigo')->label('Condición')->options(fn()=>Condicion::orderBy('con_vcNombre')->pluck('con_vcNombre','con_iCodigo'))->searchable(),
                            Forms\Components\Select::make('est_iCodigo')->label('Estado')->options(fn()=>Estado::orderBy('est_vcNombre')->pluck('est_vcNombre','est_iCodigo'))->searchable(),
                            Forms\Components\Select::make('tipo_iCodigo')
                                ->label('Tipo')
                                ->options(fn()=>Tipo::orderBy('tipo_vcNombre')->pluck('tipo_vcNombre','tipo_iCodigo'))
                                ->searchable()
                                ->required()
                                ->validationMessages([
                                    'required' => 'El campo Tipo de Administrativo es obligatorio.',
                                ]),
                            Forms\Components\DatePicker::make('adm_dNacimiento')->label('Nacimiento')->native(false),
                            Forms\Components\TextInput::make('adm_vcCelular')->label('Celular')->maxLength(30),
                            Forms\Components\TextInput::make('adm_vcTelefono')->label('Teléfono')->maxLength(30),
                            Forms\Components\TextInput::make('adm_vcEmailPersonal')->label('Email Personal')->email(),
                            Forms\Components\TextInput::make('adm_vcEmailUNMSM')->label('Email UNMSM')->email(),
                        ]),
                    ]),
                Tables\Actions\ViewAction::make()
                ->label('Ver')
                ->icon('heroicon-o-eye')
                ->modalHeading('Detalles del Administrativo')
                ->modalButton('Cerrar')
               ->modalWidth('2xl')
                ->modalContent(function (Administrativo $record) {
                    $possibleExtensions = ['jpg','jpeg','png','webp'];
                    $foundPath = null;
                    foreach ($possibleExtensions as $ext) {
                        $candidate = "fotos/{$record->adm_vcDni}.{$ext}";
                        if (Storage::disk('public')->exists($candidate)) { $foundPath = $candidate; break; }
                    }
                    return view('filament.administrativo-foto', [
                        'record' => $record,
                        'fotoPath' => $foundPath ? Storage::url($foundPath) : null,
                    ]);
                })
                ->mountUsing(function ($form, $record) {
                    return $form->fill([
                        'nombre' => $record->adm_vcNombres,
                       
                        'dni' => $record->adm_vcDni,
                        'tipo' => $record->adm_vcTipo,
                        'dependencia' => $record->dependencia->dep_vcNombre ?? 'N/A',
                        'estado' => $record->estado->est_vcNombre ?? 'N/A',
                        'condicion' => $record->condicion->con_vcNombre ?? 'N/A',
                        'categoria' => $record->categoria->cat_vcNombre ?? 'N/A',
                        'nacimiento' => $record->adm_dNacimiento,
                        'celular' => $record->adm_vcCelular,                       
                        'codigo' => $record->adm_vcCodigo,
                        'email_personal' => $record->adm_vcEmailPersonal,
                        'email_unmsm' => $record->adm_vcEmailUNMSM,

                    ]);
                })
                ->form([
                    Grid::make(2)->schema([
                    \Filament\Forms\Components\TextInput::make('tipo')
                        ->label('TIPO')
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('codigo')
                        ->label('Código')
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('dni')
                        ->label('DNI')
                        ->disabled(),

                    \Filament\Forms\Components\TextInput::make('nombre')
                        ->label('APELLIDOS Y NOMBRES')
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('dependencia')
                        ->label('DEPENDENCIA')
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('estado')
                        ->label('ESTADO')
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('condicion')
                        ->label('CONDICIÓN')                        
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('categoria')
                        ->label('CATEGORÍA')                        
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('nacimiento')
                        ->label('FECHA DE NACIMIENTO')                        
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('celular')
                        ->label('CELULAR')                        
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('email_personal')
                        ->label('EMAIL PERSONAL')                        
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('email_unmsm')
                        ->label('EMAIL UNMSM')                        
                        ->disabled(),
                ]),

                ]),
                Tables\Actions\Action::make('ver_asignaciones')
                    ->label('Ver Asignaciones')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('info')
                    ->modalHeading('Asignaciones del Administrativo')
                    ->modalWidth('3xl')
                    ->badge(fn (Administrativo $record) => ($c = $record->asignaciones->count()) ? $c : null)
                    ->modalContent(function (Administrativo $record) {
                        $asignaciones = $record->asignaciones->sortByDesc(fn($a) => optional($a->procesoFecha)->profec_dFecha);
                        return view('filament.administrativo-asignaciones', [
                            'record' => $record,
                            'asignaciones' => $asignaciones,
                        ]);
                    })
                    ->modalSubmitAction(false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdministrativos::route('/'),
            'create' => Pages\CreateAdministrativo::route('/create'),
            'edit' => Pages\EditAdministrativo::route('/{record}/edit'),
        ];
    }
}
