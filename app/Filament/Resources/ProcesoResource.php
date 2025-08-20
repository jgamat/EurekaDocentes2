<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProcesoResource\Pages;
use App\Filament\Resources\ProcesoResource\RelationManagers;
use App\Models\Proceso;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;

class ProcesoResource extends Resource
{
    protected static ?string $model = Proceso::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
        protected static ?string $navigationGroup = 'Procesos';
          protected static ?string $pluralModelLabel = 'Administración de Procesos';

    public static function form(Form $form): Form
    {
        return $form
        ->schema([
            Section::make('Información Principal del Proceso')
                ->schema([
                    TextInput::make('pro_vcNombre')
                        ->label('Nombre del Proceso')
                        ->required()
                        ->maxLength(255),
                     DatePicker::make('pro_dFechaInicio')
                        ->label('Fecha de Inicio')
                        ->required(),

                     DatePicker::make('pro_dFechaFin')
                        ->label('Fecha de Fin')
                        ->required(),
                     Toggle::make('pro_iAbierto')
                        ->label('Proceso Abierto')
                        ->required()
                        ->default(true),
                     
                ])->columns(2),

            Section::make('Fechas del Proceso')
                ->description('Añade las fechas para este proceso.')
                ->schema([
                    // --- ¡AQUÍ ESTÁ LA MAGIA! ---
                    Repeater::make('procesoFecha') // <-- El nombre DEBE COINCIDIR con el de la relación
                        ->relationship() // <-- Le dice a Filament que gestione la relación Eloquent
                        ->schema([
                           DatePicker::make('profec_dFecha')
                                ->label('Fecha del Examen')
                                ->placeholder('Selecciona una fecha')
                                ->required(),
                           Toggle::make('profec_iActivo')
                                ->label('Fecha Activa')
                                ->required()
                                ->default(true),
                      FileUpload::make('profec_vcUrlAnverso')
                          ->label('Anverso (JPG)')
                          ->image()
                          ->directory('credenciales/plantillas')
                          ->visibility('public')
                          ->acceptedFileTypes(['image/jpeg','image/jpg'])
                          ->maxSize(2048)
                          ->helperText('Formato JPG, máx 2MB. Mínimo 2480x3508 px (A4 @300dpi).')
                          ->rules(['dimensions:min_width=2480,min_height=3508']),
                      FileUpload::make('profec_vcUrlReverso')
                          ->label('Reverso (JPG)')
                          ->image()
                          ->directory('credenciales/plantillas')
                          ->visibility('public')
                          ->acceptedFileTypes(['image/jpeg','image/jpg'])
                          ->maxSize(2048)
                          ->helperText('Formato JPG, máx 2MB. Mínimo 2480x3508 px (A4 @300dpi).')
                          ->rules(['dimensions:min_width=2480,min_height=3508']),

                           
                        ])
                        ->addActionLabel('Añadir Fecha') // Personaliza el texto del botón
                        ->columns(2) // Organiza los campos de cada fecha en 3 columnas
                        ->collapsible() // Permite colapsar cada item de fecha
                        ->defaultItems(1) // Empieza con un item de fecha por defecto
                        ->reorderableWithButtons(), // Permite reordenar las fechas
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pro_iCodigo')
                    ->label('CÓDIGO')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('pro_vcNombre')
                    ->label('NOMBRE')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('pro_dFechaInicio')
                    ->label('FECHA INICIO')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pro_dFechaFin')
                    ->label('FECHA FIN')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('pro_iAbierto')
                    ->label('ACTIVO')                    
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListProcesos::route('/'),
            'create' => Pages\CreateProceso::route('/create'),
            'edit' => Pages\EditProceso::route('/{record}/edit'),
        ];
    }
}
