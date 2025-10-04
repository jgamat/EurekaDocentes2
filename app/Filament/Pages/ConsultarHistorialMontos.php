<?php

namespace App\Filament\Pages;

use App\Models\CargoMontoHistorial;
use App\Models\ExperienciaAdmision;
use App\Models\ExperienciaAdmisionMaestro;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\Layout; // placeholder in case of layout custom
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Tables\Filters\Components\DatePicker as DateFilterDatePicker;
use Filament\Tables\Filters\Components\Select as DateFilterSelect;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class ConsultarHistorialMontos extends Page implements HasTable
{
    use InteractsWithTable;
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Administración de Locales';
    protected static ?string $title = 'Historial de Montos';
    protected static string $view = 'filament.pages.consultar-historial-montos';

    public ?string $fDesde = null;
    public ?string $fHasta = null;
    public ?int $fUsuario = null;
    public ?int $fCargo = null; // instancia cargo id
    public ?int $fCargoMaestro = null; // maestro id
    public ?string $fArchivo = null;

    protected function getTableQuery(): Builder
    {
        $q = CargoMontoHistorial::query()->with(['cargo.maestro','usuario']);
        if ($this->fDesde) {
            $q->whereDate('aplicado_en','>=',$this->fDesde);
        }
        if ($this->fHasta) {
            $q->whereDate('aplicado_en','<=',$this->fHasta);
        }
        if ($this->fUsuario) {
            $q->where('user_id',$this->fUsuario);
        }
        if ($this->fCargo) {
            $q->where('expadm_iCodigo',$this->fCargo);
        }
        if ($this->fCargoMaestro) {
            $ids = ExperienciaAdmision::where('expadmma_iCodigo',$this->fCargoMaestro)->pluck('expadm_iCodigo');
            $q->whereIn('expadm_iCodigo',$ids);
        }
        if ($this->fArchivo) {
            $q->where('archivo_original','like','%'.$this->fArchivo.'%');
        }
        return $q->orderByDesc('aplicado_en');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn()=> $this->getTableQuery())
            ->columns([
                TextColumn::make('aplicado_en')
                    ->label('Aplicado')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('cargo.expadm_iCodigo')
                    ->label('Código Cargo')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('cargo.maestro.expadmma_vcNombre')
                    ->label('Nombre Cargo')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('monto_anterior')
                    ->label('Anterior')
                    ->numeric(2)
                    ->alignRight(),
                TextColumn::make('monto_nuevo')
                    ->label('Nuevo')
                    ->numeric(2)
                    ->alignRight(),
                TextColumn::make('diferencia')
                    ->label('Δ Dif')
                    ->state(fn($record)=> $record->diferencia)
                    ->formatStateUsing(fn($state)=> $state !== null ? number_format($state,2) : null)
                    ->color(fn($state)=> $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray'))
                    ->alignRight(),
                TextColumn::make('usuario.name')
                    ->label('Usuario')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('archivo_original')
                    ->label('Archivo')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('fuente')
                    ->label('Fuente')
                    ->badge()
                    ->color('primary'),
            ])
            ->defaultSort('aplicado_en','desc')
            ->filters([
                Filter::make('rango_fechas')
                    ->form([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function(Builder $query, array $data){
                        if($data['desde'] ?? null) {
                            $query->whereDate('aplicado_en','>=',$data['desde']);
                            $this->fDesde = $data['desde'];
                        }
                        if($data['hasta'] ?? null) {
                            $query->whereDate('aplicado_en','<=',$data['hasta']);
                            $this->fHasta = $data['hasta'];
                        }
                    }),
                SelectFilter::make('user_id')
                    ->label('Usuario')
                    ->options(fn()=> User::query()->orderBy('name')->pluck('name','id')),
                SelectFilter::make('expadm_iCodigo')
                    ->label('Cargo')
                    ->options(fn()=> ExperienciaAdmision::with('maestro')->orderBy('expadm_iCodigo')->limit(500)->get()->mapWithKeys(fn($c)=> [$c->expadm_iCodigo => ($c->expadm_iCodigo.' - '.($c->maestro?->expadmma_vcNombre ?? ''))]))
            ])
            ->paginated(true)
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25,50,100])
            ->headerActions([
                Action::make('exportar')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn()=> $this->exportar())
            ]);
    }

    protected function exportar()
    {
        $records = $this->getTableQuery()->limit(20000)->get();
        if($records->isEmpty()) {
            Notification::make()->title('Sin datos para exportar')->warning()->send();
            return null;
        }
        $rows = $records->map(function($r){
            return [
                'aplicado_en' => $r->aplicado_en?->format('Y-m-d H:i:s'),
                'codigo_cargo' => $r->expadm_iCodigo,
                'nombre_cargo' => $r->cargo?->maestro?->expadmma_vcNombre,
                'monto_anterior' => $r->monto_anterior,
                'monto_nuevo' => $r->monto_nuevo,
                'diferencia' => $r->diferencia,
                'usuario' => $r->usuario?->name,
                'archivo_original' => $r->archivo_original,
                'fuente' => $r->fuente,
                'created_at' => $r->created_at?->format('Y-m-d H:i:s'),
            ];
        });
        $filename = 'historial_montos_'.now()->format('Ymd_His').'.xlsx';
        return Excel::download(new \App\Exports\GenericSimpleArrayExport($rows, 'Historial de Montos'), $filename);
    }
}
