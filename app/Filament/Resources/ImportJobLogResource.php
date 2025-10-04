<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImportJobLogResource\Pages;
use App\Models\ImportJobLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\Filter;

class ImportJobLogResource extends Resource
{
    protected static ?string $model = ImportJobLog::class;
    protected static ?string $navigationGroup = 'Asignaciones';
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Historial Imports';
    protected static ?string $pluralLabel = 'Historial Imports';

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable()->label('#'),
                TextColumn::make('created_at')->dateTime('Y-m-d H:i')->label('Fecha'),
                TextColumn::make('filename_original')->wrap()->label('Archivo'),
                TextColumn::make('user.name')->label('Usuario')->toggleable(),
                TextColumn::make('total_filas')->label('Total'),
                TextColumn::make('importadas')->label('Importadas')->color('success'),
                TextColumn::make('omitidas')->label('Omitidas')->color('danger'),
                TextColumn::make('errores_count')
                    ->label('Errores')
                    ->state(fn(ImportJobLog $r)=> is_array($r->errores) ? count($r->errores) : 0)
                    ->badge()
                    ->color(fn($state)=> $state>0 ? 'danger':'success'),
                IconColumn::make('file_path')
                    ->label('Archivo Guardado')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->state(fn(ImportJobLog $r)=> !empty($r->file_path)),
            ])
            ->filters([
                Filter::make('con_errores')
                    ->label('Con errores')
                    ->query(fn($q)=> $q->whereNotNull('errores')->where('errores','!=','[]')),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_errores')
                    ->label('Ver errores')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->modalHeading('Errores del import')
                    ->modalSubmitAction(false)
                    ->modalContent(function(ImportJobLog $record){
                        $errores = $record->errores ?? [];
                        if (empty($errores)) return 'Sin errores globales';
                        return view('filament.partials.import-log-errores', compact('errores'));
                    })
                    ->hidden(fn(ImportJobLog $r)=> empty($r->errores)),
                Tables\Actions\Action::make('descargar_archivo')
                    ->label('Descargar original')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function(ImportJobLog $record){
                        if(!$record->file_path) return;
                        $full = storage_path('app/'.$record->file_path);
                        if (!is_file($full)) return;
                        return response()->download($full, $record->filename_original ?? 'archivo_import.xlsx');
                    })
                    ->hidden(fn(ImportJobLog $r)=> empty($r->file_path)),
            ])
            ->defaultSort('id','desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImportJobLogs::route('/'),
        ];
    }
}
