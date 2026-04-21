<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AlumnoResource\Pages;
use App\Models\Alumno;
use App\Models\Tipo;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class AlumnoResource extends Resource
{
    protected static ?string $model = Alumno::class;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    TextInput::make('alu_vcDni')
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
                    TextInput::make('alu_vcCodigo')
                        ->label('Código')
                        ->required()
                        ->maxLength(20)
                        ->unique(table: 'alumno', column: 'alu_vcCodigo', ignoreRecord: true),
                    TextInput::make('alu_vcPaterno')
                        ->label('Apellido Paterno')
                        ->required(),
                    TextInput::make('alu_vcMaterno')
                        ->label('Apellido Materno')
                        ->required(),
                    TextInput::make('alu_vcNombre')
                        ->label('Nombres')
                        ->required(),
                    Forms\Components\Select::make('tipo_iCodigo')
                        ->label('Tipo')
                        ->options(function () {
                            $permitidos = config('alumnos.tipos_permitidos_creacion');

                            return Tipo::whereIn('tipo_iCodigo', $permitidos)
                                ->orderBy('tipo_vcNombre')
                                ->pluck('tipo_vcNombre', 'tipo_iCodigo');
                        })
                        ->default(fn () => config('alumnos.tipo_default_creacion'))
                        ->required()
                        ->searchable()
                        ->validationMessages([
                            'required' => 'El campo Tipo de Alumno es obligatorio.',
                        ]),

                    TextInput::make('alu_vcEmail')
                        ->label('Email')
                        ->email()
                        ->maxLength(120),
                    TextInput::make('alu_vcEmailPer')
                        ->label('Email Personal')
                        ->email()
                        ->maxLength(120),
                ]),
            ]);
    }

    // Visibilidad y acceso se gestionan por Filament Shield (Roles/Permisos) mediante HasPageShield

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->recordAction(null)
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
                    ->extraCellAttributes([
                        'class' => 'copy-text-cell',
                        'style' => 'user-select:text;-webkit-user-select:text;pointer-events:auto;',
                    ])
                    ->extraAttributes([
                        'class' => 'cursor-text select-text copy-text-cell',
                        'style' => 'user-select:text;-webkit-user-select:text;',
                    ])
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->copyMessageDuration(1200)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('alu_vcDni')
                    ->label('DNI')
                    ->extraCellAttributes([
                        'class' => 'copy-text-cell',
                        'style' => 'user-select:text;-webkit-user-select:text;pointer-events:auto;',
                    ])
                    ->extraAttributes([
                        'class' => 'cursor-text select-text copy-text-cell',
                        'style' => 'user-select:text;-webkit-user-select:text;',
                    ])
                    ->copyable()
                    ->copyMessage('DNI copiado')
                    ->copyMessageDuration(1200)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('nombre_completo')
                    ->label('APELLIDOS Y NOMBRES')
                    ->extraCellAttributes([
                        'class' => 'copy-text-cell',
                        'style' => 'user-select:text;-webkit-user-select:text;pointer-events:auto;',
                    ])
                    ->extraAttributes([
                        'class' => 'cursor-text select-text copy-text-cell',
                        'style' => 'user-select:text;-webkit-user-select:text;',
                    ])
                    ->copyable()
                    ->copyMessage('Nombre copiado')
                    ->copyMessageDuration(1200)
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
                EditAction::make(),
                ViewAction::make()
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
                            TextInput::make('codigo')->label('CÓDIGO')->disabled(),
                            TextInput::make('dni')->label('DNI')->disabled(),
                            TextInput::make('nombre')->label('APELLIDOS Y NOMBRES')->disabled(),
                            TextInput::make('facultad')->label('FACULTAD')->disabled(),
                            TextInput::make('escuela')->label('ESCUELA')->disabled(),
                            TextInput::make('celular')->label('CELULAR')->disabled(),
                            TextInput::make('email')->label('EMAIL')->disabled(),
                            TextInput::make('email_per')->label('EMAIL PERSONAL')->disabled(),
                            TextInput::make('anio_ingreso')->label('AÑO INGRESO')->disabled(),
                        ]),
                    ]),
                Action::make('ver_asignaciones')
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
                            'usuario',
                        ])->get()->sortByDesc(fn ($a) => optional($a->procesoFecha)->profec_dFecha);

                        return view('filament.alumno-asignaciones', [
                            'record' => $record,
                            'asignaciones' => $asignaciones,
                        ]);
                    })
                    ->modalSubmitAction(false),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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

    public static function getNavigationGroup(): ?string
    {
        return 'Alumnos';
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return 'heroicon-o-rectangle-stack';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Consulta de Alumnos';
    }
}
