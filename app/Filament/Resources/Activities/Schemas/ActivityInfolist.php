<?php

namespace App\Filament\Resources\Activities\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ActivityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalle del Evento')
                    ->schema([
                        TextEntry::make('log_name')->label('Módulo'),
                        TextEntry::make('event')->label('Acción')->badge(),
                        TextEntry::make('description')->label('Descripción')->columnSpanFull(),
                    ])->columns(2),
                Section::make('Información del Usuario (Actor)')
                    ->schema([
                        TextEntry::make('causer.name')->label('Nombre de Usuario'),
                        TextEntry::make('causer_type')->label('Tipo'),
                        TextEntry::make('causer_id')->label('ID de Registro'),
                    ])->columns(3),
                Section::make('Información de Conexión')
                    ->schema([
                        TextEntry::make('properties.ip')->label('Dirección IP')->default('N/A'),
                        TextEntry::make('properties.user_agent')->label('Navegador / Dispositivo')->default('N/A')->columnSpanFull(),
                    ])->columns(2),
                Section::make('Objeto Afectado')
                    ->schema([
                        TextEntry::make('subject_type')->label('Modelo'),
                        TextEntry::make('subject_id')->label('Registro (ID)'),
                        KeyValueEntry::make('attribute_changes.attributes')
                            ->label('Nuevos Valores')
                            ->columnSpanFull(),
                        KeyValueEntry::make('attribute_changes.old')
                            ->label('Valores Anteriores (Si aplica)')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
