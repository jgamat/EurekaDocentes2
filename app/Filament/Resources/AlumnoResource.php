<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AlumnoResource\Pages;
use App\Filament\Resources\AlumnoResource\RelationManagers;
use App\Models\Alumno;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Grid;
use App\Models\Alumno as AlumnoModel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;



class AlumnoResource extends Resource
{
 
    protected static ?string $model = Alumno::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Alumnos';
     protected static ?string $pluralModelLabel = 'Consulta de Alumnos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('alu_vcDni')
                        ->label('DNI')
                        ->required()
                        ->maxLength(15)
                        ->length(8)
                        ->numeric()
                        ->unique(table: 'alumno', column: 'alu_vcDni', ignoreRecord: true)
                        ->validationMessages([
                            'unique' => 'El DNI ya está registrado.',
                            'length' => 'El DNI debe tener exactamente 8 dígitos.',
                            'numeric' => 'El DNI solo debe contener números.',
                        ]),
                    Forms\Components\TextInput::make('alu_vcCodigo')
                        ->label('Código')
                        ->required()
                        ->maxLength(20)
                        ->unique(table: 'alumno', column: 'alu_vcCodigo', ignoreRecord: true),
                    Forms\Components\TextInput::make('alu_vcPaterno')
                        ->label('Apellido Paterno')
                        ->required(),
                    Forms\Components\TextInput::make('alu_vcMaterno')
                        ->label('Apellido Materno')
                        ->required(),
                    Forms\Components\TextInput::make('alu_vcNombre')
                        ->label('Nombres')
                        ->required(),
                    Forms\Components\Select::make('tipo_iCodigo')
                        ->label('Tipo')
                        ->options(function(){
                            $permitidos = config('alumnos.tipos_permitidos_creacion');
                            return \App\Models\Tipo::whereIn('tipo_iCodigo',$permitidos)
                                ->orderBy('tipo_vcNombre')
                                ->pluck('tipo_vcNombre','tipo_iCodigo');
                        })
                        ->default(fn()=> config('alumnos.tipo_default_creacion'))
                        ->required()
                        ->searchable()
                        ->validationMessages([
                            'required' => 'El campo Tipo de Alumno es obligatorio.',
                        ]),
                        
                    Forms\Components\TextInput::make('alu_vcEmail')
                        ->label('Email')
                        ->email()
                        ->maxLength(120),
                    Forms\Components\TextInput::make('alu_vcEmailPer')
                        ->label('Email Personal')
                        ->email()
                        ->maxLength(120),
                ])
            ]);
    }

    // Visibilidad y acceso se gestionan por Filament Shield (Roles/Permisos) mediante HasPageShield

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                Alumno::query()
                    ->select([
                        'alu_id',
                        'alu_vcCodigo',
                        'alu_vcDni',
                        'alu_vcPaterno',
                        'alu_vcMaterno',
                        'alu_vcNombre',
                        'fac_vcNombre',
                        'esc_vcNombre',
                        'alu_vcCelular',
                        'alu_vcEmail',
                        'alu_vcEmailPer',
                        'alu_iAnioIngreso',
                    ])
                    ->withCount('asignaciones')
            )
            ->columns([
                TextColumn::make('alu_vcCodigo')
                    ->label('CÓDIGO')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('alu_vcDni')
                    ->label('DNI')
                    ->sortable()
                    ->searchable(),
                                TextColumn::make('nombre_completo')
                    ->label('APELLIDOS Y NOMBRES')
                    ->getStateUsing(fn ($record) => $record->nombre_completo)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                                                $search = trim($search);
                                                return $query->where(function ($q) use ($search) {
                                                        // Búsqueda diferenciada para acelerar consultas numéricas
                                                        if (ctype_digit($search)) {
                                                                $q->where('alu_vcCodigo', 'like', "%{$search}%")
                                                                    ->orWhere('alu_vcDni', 'like', "%{$search}%");
                                                        } else {
                                                                $q->where('alu_vcPaterno', 'like', "%{$search}%")
                                                                    ->orWhere('alu_vcMaterno', 'like', "%{$search}%")
                                                                    ->orWhere('alu_vcNombre', 'like', "%{$search}%");
                                                        }
                                                });
                    }),
                TextColumn::make('fac_vcNombre')
                    ->label('FACULTAD')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make()
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Detalles del Alumno')
                    ->modalButton('Cerrar')
                    ->modalWidth('2xl')
                    ->mountUsing(function ($form, $record) {
                        return $form->fill([
                            'codigo' => $record->alu_vcCodigo,
                            'dni' => $record->alu_vcDni,
                            'nombre' => $record->nombre_completo,
                            'facultad' => $record->fac_vcNombre,
                            'escuela' => $record->esc_vcNombre,
                            'celular' => $record->alu_vcCelular,
                            'email' => $record->alu_vcEmail,
                            'email_per' => $record->alu_vcEmailPer,
                            'anio_ingreso' => $record->alu_iAnioIngreso,
                        ]);
                    })
                    ->form([
                        Grid::make(2)->schema([
                            \Filament\Forms\Components\TextInput::make('codigo')->label('CÓDIGO')->disabled(),
                            \Filament\Forms\Components\TextInput::make('dni')->label('DNI')->disabled(),
                            \Filament\Forms\Components\TextInput::make('nombre')->label('APELLIDOS Y NOMBRES')->disabled(),
                            \Filament\Forms\Components\TextInput::make('facultad')->label('FACULTAD')->disabled(),
                            \Filament\Forms\Components\TextInput::make('escuela')->label('ESCUELA')->disabled(),
                            \Filament\Forms\Components\TextInput::make('celular')->label('CELULAR')->disabled(),
                            \Filament\Forms\Components\TextInput::make('email')->label('EMAIL')->disabled(),
                            \Filament\Forms\Components\TextInput::make('email_per')->label('EMAIL PERSONAL')->disabled(),
                            \Filament\Forms\Components\TextInput::make('anio_ingreso')->label('AÑO INGRESO')->disabled(),
                        ])
                    ]),
                Tables\Actions\Action::make('ver_asignaciones')
                    ->label('Ver Asignaciones')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('info')
                    ->modalHeading('Asignaciones del Alumno')
                    ->modalWidth('3xl')
                    // Usamos asignaciones_count precalculado para evitar N+1
                    ->badge(fn (Alumno $record) => $record->asignaciones_count ? $record->asignaciones_count : null)
                    ->modalContent(function (Alumno $record) {
                        $asignaciones = $record->asignaciones()->with([
                            'procesoFecha.proceso',
                            'local.localesMaestro',
                            'experienciaAdmision.maestro',
                            'usuario'
                        ])->get()->sortByDesc(fn($a) => optional($a->procesoFecha)->profec_dFecha);
                        return view('filament.alumno-asignaciones', [
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
            'index' => Pages\ListAlumnos::route('/'),
            'create' => Pages\CreateAlumno::route('/create'),
            'edit' => Pages\EditAlumno::route('/{record}/edit'),
        ];
    }
}
