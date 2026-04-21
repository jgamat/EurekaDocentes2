<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdministrativoResource\Pages;
use App\Models\Administrativo;
use App\Models\Categoria;
use App\Models\Condicion;
use App\Models\Dependencia;
use App\Models\Estado;
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
use Illuminate\Support\Facades\Storage;

class AdministrativoResource extends Resource
{
    protected static ?string $model = Administrativo::class;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('adm_vcDni')
                        ->label('DNI')
                        ->required()
                        ->maxLength(15)
                        ->length(8)
                        ->tel()
                        ->inputMode('numeric')
                        ->dehydrateStateUsing(fn ($state) => preg_replace('/\D+/', '', (string) $state))
                        ->rule('regex:/^\d{8}$/')
                        ->unique(table: 'administrativo', column: 'adm_vcDni', ignoreRecord: true)
                        ->validationAttribute('DNI')
                        ->helperText('Debe ser único. Ingrese 8 dígitos.')
                        ->validationMessages([
                            'unique' => 'El DNI ya está registrado.',
                            'length' => 'El DNI debe tener exactamente 8 dígitos.',
                            'regex' => 'El DNI solo debe contener 8 dígitos numéricos.',
                        ]),
                    TextInput::make('adm_vcCodigo')
                        ->label('Código')
                        ->maxLength(30),
                    TextInput::make('adm_vcNombres')
                        ->label('Apellidos y Nombres')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('dep_iCodigo')
                        ->label('Dependencia')
                        ->options(fn () => Dependencia::orderBy('dep_vcNombre')->pluck('dep_vcNombre', 'dep_iCodigo'))
                        ->searchable(),
                    Forms\Components\Select::make('cat_iCodigo')
                        ->label('Categoría')
                        ->options(fn () => Categoria::orderBy('cat_vcNombre')->pluck('cat_vcNombre', 'cat_iCodigo'))
                        ->searchable(),
                    Forms\Components\Select::make('con_iCodigo')
                        ->label('Condición')
                        ->options(fn () => Condicion::orderBy('con_vcNombre')->pluck('con_vcNombre', 'con_iCodigo'))
                        ->searchable(),
                    Forms\Components\Select::make('est_iCodigo')
                        ->label('Estado')
                        ->options(fn () => Estado::orderBy('est_vcNombre')->pluck('est_vcNombre', 'est_iCodigo'))
                        ->searchable(),
                    Forms\Components\Select::make('tipo_iCodigo')
                        ->label('Tipo')
                        ->options(function () {
                            $permitidos = config('administrativos.tipos_permitidos_creacion');

                            return Tipo::whereIn('tipo_iCodigo', $permitidos)
                                ->orderBy('tipo_vcNombre')
                                ->pluck('tipo_vcNombre', 'tipo_iCodigo');
                        })
                        ->searchable()
                        ->required()
                        ->validationMessages([
                            'required' => 'El campo Tipo de Administrativo es obligatorio.',
                        ])
                        ->helperText(function () {

                            return 'Seleccione correctamente el tipo de administrativo para evitar errores con la planilla.';
                        }),
                    TextInput::make('adm_vcCelular')
                        ->label('Celular')
                        ->maxLength(30),
                    TextInput::make('adm_vcTelefono')
                        ->label('Teléfono')
                        ->maxLength(30),
                    Forms\Components\DatePicker::make('adm_dNacimiento')
                        ->label('Nacimiento')
                        ->native(false),
                    TextInput::make('adm_vcEmailPersonal')
                        ->label('Email Personal')
                        ->email(),
                    TextInput::make('adm_vcEmailUNMSM')
                        ->label('Email UNMSM')
                        ->email(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->recordAction(null)
            ->query(Administrativo::with([
                'dependencia',
                'asignaciones.procesoFecha.proceso',
                'asignaciones.local.localesMaestro',
                'asignaciones.experienciaAdmision.maestro',
            ]))
            ->columns([
                TextColumn::make('adm_vcDni')
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
                    ->searchable()
                    ->sortable(),

                TextColumn::make('adm_vcNombres')
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
                EditAction::make()
                    ->label('Editar')
                    ->modalHeading('Editar Administrativo')
                    ->mutateFormDataUsing(function (array $data): array {
                        return $data;
                    })
                    ->successNotificationTitle('Administrativo actualizado')
                    ->form([
                        Grid::make(2)->schema([
                            TextInput::make('adm_vcDni')->label('DNI')->disabled(),
                            TextInput::make('adm_vcCodigo')->label('Código')->maxLength(30),
                            TextInput::make('adm_vcNombres')->label('Apellidos y Nombres')->required(),
                            Forms\Components\Select::make('dep_iCodigo')->label('Dependencia')->options(fn () => Dependencia::orderBy('dep_vcNombre')->pluck('dep_vcNombre', 'dep_iCodigo'))->searchable(),
                            Forms\Components\Select::make('cat_iCodigo')->label('Categoría')->options(fn () => Categoria::orderBy('cat_vcNombre')->pluck('cat_vcNombre', 'cat_iCodigo'))->searchable(),
                            Forms\Components\Select::make('con_iCodigo')->label('Condición')->options(fn () => Condicion::orderBy('con_vcNombre')->pluck('con_vcNombre', 'con_iCodigo'))->searchable(),
                            Forms\Components\Select::make('est_iCodigo')->label('Estado')->options(fn () => Estado::orderBy('est_vcNombre')->pluck('est_vcNombre', 'est_iCodigo'))->searchable(),
                            Forms\Components\Select::make('tipo_iCodigo')
                                ->label('Tipo')
                                ->options(fn () => Tipo::orderBy('tipo_vcNombre')->pluck('tipo_vcNombre', 'tipo_iCodigo'))
                                ->searchable()
                                ->required()
                                ->validationMessages([
                                    'required' => 'El campo Tipo de Administrativo es obligatorio.',
                                ]),
                            Forms\Components\DatePicker::make('adm_dNacimiento')->label('Nacimiento')->native(false),
                            TextInput::make('adm_vcCelular')->label('Celular')->maxLength(30),
                            TextInput::make('adm_vcTelefono')->label('Teléfono')->maxLength(30),
                            TextInput::make('adm_vcEmailPersonal')->label('Email Personal')->email(),
                            TextInput::make('adm_vcEmailUNMSM')->label('Email UNMSM')->email(),
                        ]),
                    ]),
                ViewAction::make()
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Detalles del Administrativo')
                    ->modalButton('Cerrar')
                    ->modalWidth('2xl')
                    ->modalContent(function (Administrativo $record) {
                        $possibleExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                        $foundPath = null;
                        foreach ($possibleExtensions as $ext) {
                            $candidate = "fotos/{$record->adm_vcDni}.{$ext}";
                            if (Storage::disk('public')->exists($candidate)) {
                                $foundPath = $candidate;
                                break;
                            }
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
                            TextInput::make('tipo')
                                ->label('TIPO')
                                ->disabled(),
                            TextInput::make('codigo')
                                ->label('Código')
                                ->disabled(),
                            TextInput::make('dni')
                                ->label('DNI')
                                ->disabled(),

                            TextInput::make('nombre')
                                ->label('APELLIDOS Y NOMBRES')
                                ->disabled(),
                            TextInput::make('dependencia')
                                ->label('DEPENDENCIA')
                                ->disabled(),
                            TextInput::make('estado')
                                ->label('ESTADO')
                                ->disabled(),
                            TextInput::make('condicion')
                                ->label('CONDICIÓN')
                                ->disabled(),
                            TextInput::make('categoria')
                                ->label('CATEGORÍA')
                                ->disabled(),
                            TextInput::make('nacimiento')
                                ->label('FECHA DE NACIMIENTO')
                                ->disabled(),
                            TextInput::make('celular')
                                ->label('CELULAR')
                                ->disabled(),
                            TextInput::make('email_personal')
                                ->label('EMAIL PERSONAL')
                                ->disabled(),
                            TextInput::make('email_unmsm')
                                ->label('EMAIL UNMSM')
                                ->disabled(),
                        ]),

                    ]),
                Action::make('ver_asignaciones')
                    ->label('Ver Asignaciones')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('info')
                    ->modalHeading('Asignaciones del Administrativo')
                    ->modalWidth('3xl')
                    ->badge(fn (Administrativo $record) => ($c = $record->asignaciones->count()) ? $c : null)
                    ->modalContent(function (Administrativo $record) {
                        $asignaciones = $record->asignaciones->sortByDesc(fn ($a) => optional($a->procesoFecha)->profec_dFecha);

                        return view('filament.administrativo-asignaciones', [
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
            'index' => Pages\ListAdministrativos::route('/'),
            'create' => Pages\CreateAdministrativo::route('/create'),
            'edit' => Pages\EditAdministrativo::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Administrativos';
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return 'heroicon-o-rectangle-stack';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Consulta de Administrativos';
    }
}
