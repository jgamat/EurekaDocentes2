<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocalesMaestroResource\Pages;
use App\Models\LocalesMaestro;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class LocalesMaestroResource extends Resource
{
    protected static ?string $model = LocalesMaestro::class;

    public static function form(Schema $form): Schema
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
            ->recordUrl(null)
            ->recordAction(null)
            ->columns([
                Tables\Columns\TextColumn::make('locma_iCodigo')
                    ->label('CÓDIGO LOCAL')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('locma_vcNombre')
                    ->label('NOMBRE DEL LOCAL')
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
            ])
            ->filters([
                //
            ])
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
            'index' => Pages\ListLocalesMaestros::route('/'),
            'create' => Pages\CreateLocalesMaestro::route('/create'),
            'edit' => Pages\EditLocalesMaestro::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Maestros';
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return 'heroicon-o-rectangle-stack';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Maestro de Locales';
    }
}
