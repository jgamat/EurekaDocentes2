<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExperienciaAdmisionMaestroResource\Pages;
use App\Filament\Resources\ExperienciaAdmisionMaestroResource\RelationManagers;
use App\Models\ExperienciaAdmisionMaestro;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

class ExperienciaAdmisionMaestroResource extends Resource
{
    protected static ?string $model = ExperienciaAdmisionMaestro::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationGroup = 'Maestros';
    protected static ?string $navigationLabel = 'Maestro de Cargos';
    protected static ?string $pluralLabel = 'Cargos';
    protected static ?string $modelLabel = 'Cargo';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('expadmma_vcNombre')
                    ->label('Nombre del Cargo')
                    ->required()
                    ->maxLength(150)
                    ->placeholder('Ingrese el nombre del cargo'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('expadmma_iCodigo')
                    ->label('Código')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('expadmma_vcNombre')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable()
                    ->wrap(),
            ])
            ->filters([
                //
            ])
            ->defaultSort('expadmma_iCodigo')
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
            'index' => Pages\ListExperienciaAdmisionMaestros::route('/'),
            'create' => Pages\CreateExperienciaAdmisionMaestro::route('/create'),
            'edit' => Pages\EditExperienciaAdmisionMaestro::route('/{record}/edit'),
        ];
    }
}
