<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExperienciaAdmisionMaestroResource\Pages;
use App\Models\ExperienciaAdmisionMaestro;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class ExperienciaAdmisionMaestroResource extends Resource
{
    protected static ?string $model = ExperienciaAdmisionMaestro::class;

    protected static ?string $navigationLabel = 'Maestro de Cargos';

    protected static ?string $pluralLabel = 'Cargos';

    public static function form(Schema $form): Schema
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
            ->recordUrl(null)
            ->recordAction(null)
            ->columns([
                TextColumn::make('expadmma_iCodigo')
                    ->label('Código')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('expadmma_vcNombre')
                    ->label('Nombre')
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
                    ->sortable()
                    ->searchable()
                    ->wrap(),
            ])
            ->filters([
                //
            ])
            ->defaultSort('expadmma_iCodigo')
            ->actions([
                EditAction::make(),
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
            'index' => Pages\ListExperienciaAdmisionMaestros::route('/'),
            'create' => Pages\CreateExperienciaAdmisionMaestro::route('/create'),
            'edit' => Pages\EditExperienciaAdmisionMaestro::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Maestros';
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return 'heroicon-o-briefcase';
    }

    public static function getModelLabel(): string
    {
        return 'Cargo';
    }
}
