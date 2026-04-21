<?php

namespace App\Filament\Pages;

use App\Exports\ConsolidadoPlanillasExport;
use App\Models\Proceso;
use App\Models\ProcesoFecha;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Facades\Excel;

class ReporteConsolidadoPlanillas extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Planillas';

    protected string $view = 'filament.pages.reporte-consolidado-planillas';

    public ?int $proceso_id = null;

    public ?int $proceso_fecha_id = null;

    // Ajuste: la firma debe coincidir con BasePage::getFormModel(): Model|string|null
    // No necesitamos un modelo Eloquent; trabajamos con propiedades públicas, así que retornamos null.
    protected function getFormModel(): Model|string|null
    {
        return null;
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('proceso_id')
                ->label('Proceso abierto')
                ->options(fn () => Proceso::where('pro_iAbierto', 1)->orderBy('pro_vcNombre')->pluck('pro_vcNombre', 'pro_iCodigo'))
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    $set('proceso_fecha_id', null);
                    $this->proceso_id = $state ? (int) $state : null;
                })
                ->required(),
            Select::make('proceso_fecha_id')
                ->label('Fecha activa')
                ->options(function (callable $get) {
                    $proc = $get('proceso_id');
                    if (! $proc) {
                        return [];
                    }

                    return ProcesoFecha::where('pro_iCodigo', $proc)
                        ->where('profec_iActivo', 1)
                        ->orderBy('profec_dFecha')
                        ->get()
                        ->mapWithKeys(fn ($f) => [$f->profec_iCodigo => $f->profec_dFecha]);
                })
                ->reactive()
                ->afterStateUpdated(function ($state) {
                    $this->proceso_fecha_id = $state ? (int) $state : null;
                })
                ->required()
                ->disabled(fn (callable $get) => ! $get('proceso_id')),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Exportar Excel')
                ->action(function () {
                    if (! $this->proceso_fecha_id) {
                        Notification::make()->title('Seleccione fecha')->danger()->send();

                        return;
                    }
                    $fecha = ProcesoFecha::find($this->proceso_fecha_id);
                    if (! $fecha) {
                        Notification::make()->title('Fecha inválida')->danger()->send();

                        return;
                    }
                    $fechaLabel = $fecha->profec_dFecha;
                    $now = now()->format('Y-m-d H:i:s');

                    return Excel::download(new ConsolidadoPlanillasExport($fecha->profec_iCodigo, $fechaLabel, $now), 'consolidado_planillas_'.$fecha->profec_iCodigo.'.xlsx');
                })
                ->disabled(fn () => empty($this->proceso_fecha_id)),
        ];
    }
}
