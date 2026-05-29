<?php

namespace App\Filament\Resources\Activities\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('log_name')
                    ->label('Tipo (Módulo)')
                    ->badge()
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'default' => 'Sistema',
                        'Access' => 'Accesos',
                        'Modelos' => 'Asignaciones',
                        'Reportes' => 'Reportes',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Access' => 'danger',
                        'Modelos' => 'success',
                        'Reportes' => 'warning',
                        default => 'primary',
                    }),
                TextColumn::make('event')
                    ->label('Acción Realizada')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'created' => 'Creado',
                        'updated' => 'Actualizado',
                        'deleted' => 'Eliminado',
                        'restored' => 'Restaurado',
                        'Login' => 'Inicio de Sesión',
                        'Failed' => 'Login Fallido',
                        'Logout' => 'Cierre de Sesión',
                        'asignardocente' => 'Asignar Docente',
                        'desasignardocente' => 'Desasignar Docente',
                        'asignaradministrativo' => 'Asignar Administrativo',
                        'desasignaradministrativo' => 'Desasignar Administrativo',
                        'asignaralumno' => 'Asignar Alumno',
                        'desasignaralumno' => 'Desasignar Alumno',
                        'registrar descargar de reportedeasignados' => 'Descargar Reporte Asignados',
                        default => ucfirst($state),
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created', 'asignardocente', 'asignaradministrativo', 'asignaralumno' => 'success',
                        'deleted', 'desasignardocente', 'desasignaradministrativo', 'desasignaralumno', 'Failed' => 'danger',
                        'updated', 'registrar descargar de reportedeasignados' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable(),
                TextColumn::make('causer.name')
                    ->label('Usuario (Actor)')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('properties.ip')
                    ->label('IP de Conexión')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('properties.user_agent')
                    ->label('Navegador')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Tipo de Log')
                    ->options([
                        'default' => 'Sistema',
                        'Access' => 'Accesos',
                        'Modelos' => 'Modelos',
                        'Reportes' => 'Reportes',
                    ]),
                SelectFilter::make('event')
                    ->label('Acciones')
                    ->options([
                        'created' => 'Creados',
                        'updated' => 'Actualizados',
                        'deleted' => 'Eliminados',
                        'Login' => 'Inicio de Sesiones',
                        'Failed' => 'Sesiones Fallidas',
                        'asignardocente' => 'Docentes Asignados',
                        'desasignardocente' => 'Docentes Desasignados',
                        'asignaradministrativo' => 'Administrativos Asignados',
                        'desasignaradministrativo' => 'Administrativos Desasignados',
                        'asignaralumno' => 'Alumnos Asignados',
                        'desasignaralumno' => 'Alumnos Desasignados',
                        'registrar descargar de reportedeasignados' => 'Descargas de Reportes Asignados',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
