<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocenteResource\Pages;
use App\Filament\Resources\DocenteResource\RelationManagers;
use App\Models\Docente;
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

class DocenteResource extends Resource
{
    protected static ?string $model = Docente::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Docentes';
      protected static ?string $pluralModelLabel = 'Consulta de Docentes';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('doc_vcDni')
                        ->label('DNI')
                        ->required()
                        ->minLength(8)
                        ->maxLength(9)
                        ->numeric()
                        ->unique(table: 'docente', column: 'doc_vcDni', ignoreRecord: true)
                        ->validationAttribute('DNI')
                        ->helperText('Debe ser único (8 a 9 dígitos)')
                        ->validationMessages([
                            'unique' => 'El DNI ya está registrado.',
                            'min_length' => 'El DNI debe tener al menos 8 dígitos.',
                            'max_length' => 'El DNI no debe exceder 9 dígitos.',
                            'numeric' => 'El DNI solo debe contener números.',
                        ])
                        ->disabled(fn($record)=> $record !== null),
                    Forms\Components\TextInput::make('doc_vcCodigo')
                        ->label('Código')
                        ->required()
                        ->minLength(6)
                        ->maxLength(7)
                        ->unique(table: 'docente', column: 'doc_vcCodigo', ignoreRecord: true)
                        ->validationAttribute('Código')
                        ->helperText('6 a 7 caracteres (único)')
                        ->validationMessages([
                            'unique' => 'El Código ya está registrado.',
                            'min_length' => 'El Código debe tener al menos 6 caracteres.',
                            'max_length' => 'El Código no debe exceder 7 caracteres.',
                        ]),
                    Forms\Components\TextInput::make('doc_vcPaterno')
                        ->label('Apellido Paterno')
                        ->required()
                        ->maxLength(60),
                    Forms\Components\TextInput::make('doc_vcMaterno')
                        ->label('Apellido Materno')
                        ->required()
                        ->maxLength(60),
                    Forms\Components\TextInput::make('doc_vcNombre')
                        ->label('Nombres')
                        ->required()
                        ->maxLength(120),
                    Forms\Components\Select::make('dep_iCodigo')
                        ->label('Dependencia')
                        ->options(fn()=> \App\Models\Dependencia::orderBy('dep_vcNombre')->pluck('dep_vcNombre','dep_iCodigo'))
                        ->searchable(),
                    Forms\Components\Select::make('cat_iCodigo')
                        ->label('Categoría')
                        ->options(fn()=> \App\Models\Categoria::orderBy('cat_vcNombre')->pluck('cat_vcNombre','cat_iCodigo'))
                        ->searchable(),
                    Forms\Components\Select::make('con_iCodigo')
                        ->label('Condición')
                        ->options(fn()=> \App\Models\Condicion::orderBy('con_vcNombre')->pluck('con_vcNombre','con_iCodigo'))
                        ->searchable(),
                    Forms\Components\Select::make('est_iCodigo')
                        ->label('Estado')
                        ->options(fn()=> \App\Models\Estado::orderBy('est_vcNombre')->pluck('est_vcNombre','est_iCodigo'))
                        ->searchable(),
                    Forms\Components\Select::make('tipo_iCodigo')
                        ->label('Tipo')
                        ->options(function(){
                            $permitidos = config('docentes.tipos_permitidos_creacion');
                            return \App\Models\Tipo::whereIn('tipo_iCodigo',$permitidos)
                                ->orderBy('tipo_vcNombre')
                                ->pluck('tipo_vcNombre','tipo_iCodigo');
                        })
                        ->default(fn()=> config('docentes.tipo_default_creacion'))
                        ->required()
                        ->searchable()
                        ->validationMessages([
                            'required' => 'El campo Tipo de Docente es obligatorio.',
                        ]),
                        
                    Forms\Components\DatePicker::make('doc_dNacimiento')
                        ->label('Fecha Nacimiento')
                        ->native(false),
                    Forms\Components\TextInput::make('doc_vcCelular')
                        ->label('Celular')
                        ->maxLength(20),
                    Forms\Components\TextInput::make('doc_vcEmail')
                        ->label('Email Personal')
                        ->email()
                        ->maxLength(120),
                    Forms\Components\TextInput::make('doc_vcEmailUNMSM')
                        ->label('Email UNMSM')
                        ->email()
                        ->maxLength(120),
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->query(Docente::with([
            'dependencia',
            'asignaciones.procesoFecha.proceso',
            'asignaciones.local.localesMaestro',
            'asignaciones.experienciaAdmision.maestro',
        ]))        
            ->columns([
            TextColumn::make('doc_vcCodigo')
                ->label('CÓDIGO')
                ->searchable()
                ->sortable(),

             TextColumn::make('doc_vcDni')
                ->label('DNI')
                ->searchable()
                ->sortable(),
            TextColumn::make('nombre_completo')
                 ->label('APELLIDOS Y NOMBRES')
                 ->getStateUsing(fn ($record) => "{$record->doc_vcPaterno} {$record->doc_vcMaterno} {$record->doc_vcNombre}")
                 ->searchable(query: function (Builder $query, string $search): Builder {
                 return $query->where(function ($q) use ($search) {
                     $q->where('doc_vcPaterno', 'like', "%{$search}%")
                     ->orWhere('doc_vcMaterno', 'like', "%{$search}%")
                     ->orWhere('doc_vcNombre', 'like', "%{$search}%");
                });
             }),

             TextColumn::make('dependencia.dep_vcNombre')
                 ->label('DEPENDENCIA')
                 ->searchable()
                 ->sortable(),

           
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make()
                ->label('Ver')
                ->icon('heroicon-o-eye')
                ->modalHeading('Detalles del Docente')
                ->modalButton('Cerrar')
               ->modalWidth('2xl')
                ->modalContent(function (Docente $record) {
                    $possibleExtensions = ['jpg','jpeg','png','webp'];
                    $foundPath = null;
                    foreach ($possibleExtensions as $ext) {
                        $candidate = "fotos/{$record->doc_vcDni}.{$ext}"; // ahora basado en DNI
                        if (Storage::disk('public')->exists($candidate)) { $foundPath = $candidate; break; }
                    }
                    return view('filament.docente-foto', [
                        'record' => $record,
                        'fotoPath' => $foundPath ? Storage::url($foundPath) : null,
                    ]);
                })
                ->mountUsing(function ($form, $record) {
                    return $form->fill([
                        'nombre' => $record->doc_vcPaterno . ' ' . $record->doc_vcMaterno . ' ' . $record->doc_vcNombre,
                        'codigo' => $record->doc_vcCodigo,
                        'dni' => $record->doc_vcDni,
                        'tipo' => $record->tipo->tipo_vcNombre ?? 'N/A',
                        'dependencia' => $record->dependencia->dep_vcNombre ?? 'N/A',
                        'estado' => $record->estado->est_vcNombre ?? 'N/A',
                        'condicion' => $record->condicion->con_vcNombre ?? 'N/A',
                        'categoria' => $record->categoria->cat_vcNombre ?? 'N/A',
                        'nacimiento' => $record->doc_dNacimiento,
                        'celular' => $record->doc_vcCelular,                      
                       
                        'email_personal' => $record->doc_vcEmail,
                        'email_unmsm' => $record->doc_vcEmailUNMSM,

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
                    ->modalHeading('Asignaciones del Docente')
                    ->modalWidth('3xl')
                    ->badge(fn (Docente $record) => ($c = $record->asignaciones->count()) ? $c : null)
                    ->modalContent(function (Docente $record) {
                        $asignaciones = $record->asignaciones->sortByDesc(fn($a) => optional($a->procesoFecha)->profec_dFecha);
                        return view('filament.docente-asignaciones', [
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
            'index' => Pages\ListDocentes::route('/'),
            'create' => Pages\CreateDocente::route('/create'),
            'edit' => Pages\EditDocente::route('/{record}/edit'),
        ];
    }
}
