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
                //
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
