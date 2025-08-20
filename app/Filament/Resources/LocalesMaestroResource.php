<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocalesMaestroResource\Pages;
use App\Filament\Resources\LocalesMaestroResource\RelationManagers;
use App\Models\LocalesMaestro;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;

class LocalesMaestroResource extends Resource
{
    protected static ?string $model = LocalesMaestro::class; 

    protected static ?string $pluralModelLabel = 'Maestro de Locales';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
     protected static ?string $navigationGroup = 'Maestros';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Maestro de Locales')
                ->schema([
                    TextInput::make('locma_vcNombre')
                        ->label('Nombre del Local')
                        ->required()
                        ->maxLength(255),
                    
                     
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('locma_iCodigo')
                    ->label('CÓDIGO LOCAL')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('locma_vcNombre')
                    ->label('NOMBRE DEL LOCAL')
                    ->searchable()
                    ->sortable(),
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
            'index' => Pages\ListLocalesMaestros::route('/'),
            'create' => Pages\CreateLocalesMaestro::route('/create'),
            'edit' => Pages\EditLocalesMaestro::route('/{record}/edit'),
        ];
    }
}
