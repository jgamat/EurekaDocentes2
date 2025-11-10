<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Livewire\WithFileUploads;
use Filament\Notifications\Notification;
use App\Filament\Pages\Concerns\WithAssignmentFileHandling;
use App\Services\Import\CargoMassUpdateService;
use App\DTO\Import\CargoMontoUpdateRow;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class ImportarCargosActualizar extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.pages.importar-cargos-actualizar';

    use Forms\Concerns\InteractsWithForms;
    use WithFileUploads;
    use WithAssignmentFileHandling;
       use HasPageShield; 
    
    /**
     * Página para actualización masiva de montos de cargos a partir del Excel exportado desde ConsultarCargos.
     */
    public $file = null; // file upload state
    public array $preview = [];
    public array $stats = [
        'total' => 0,
        'cambiar' => 0,
        'sin_cambio' => 0,
        'errores' => 0,
        'duplicados' => 0,
    ];
    public bool $onlyValidate = false;
    public bool $showOnlyChanges = false;
    public ?string $originalFilename = null;
    
    protected static ?string $navigationGroup = 'Administración de Locales';
    protected static ?string $title = 'Actualizar Montos Cargos';
    
    public function mount(): void
    {
        $this->form->fill();
    }
    
    protected function getFormSchema(): array
    {
        return [
            Forms\Components\FileUpload::make('file')
                ->label('Archivo Excel de Cargos (exportado)')
                ->directory('imports/temp')
                ->preserveFilenames()
                ->acceptedFileTypes([
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-excel',
                    'text/csv'
                ])
                ->maxSize(5120)
                ->required(),
            Forms\Components\Toggle::make('onlyValidate')
                ->label('Sólo validar (no aplicar)')
                ->default(false),
            Forms\Components\Toggle::make('showOnlyChanges')
                ->label('Mostrar sólo filas con cambio')
                ->default(false),
        ];
    }
    
    protected function getFormModel(): \Illuminate\Database\Eloquent\Model|string|null
    {
        return static::class;
    }
    
    public function parseFile(CargoMassUpdateService $service): void
    {
        // Obtener meta del archivo
        $meta = $this->resolveFileMeta();
        if (!$meta || empty($meta['abs'])) {
            Notification::make()->danger()->title('Seleccione un archivo válido')->send();
            return;
        }
        $this->originalFilename = $meta['original'] ?? basename($meta['abs']);
        $rows = $this->readSpreadsheet($meta['abs']);
        if (empty($rows)) {
            Notification::make()->danger()->title('Archivo vacío o no legible')->send();
            return;
        }
        $dtos = $service->parse($rows);
        $this->preview = $dtos->map(fn(CargoMontoUpdateRow $d)=> [
            'row' => $d->rowNumber,
            'codigo' => $d->codigoCargo,
            'nombre_excel' => $d->nombreExcel,
            'nombre_bd' => $d->nombreBD,
            'monto_actual' => $d->montoActual,
            'monto_nuevo' => $d->montoNuevo,
            'estado' => $d->estado,
            'errores' => $d->errors,
            'warnings' => $d->warnings,
            'valid' => $d->valid,
        ])->toArray();
        $this->recomputeStats();
        Notification::make()->success()->title('Procesado')->body('Vista previa generada')->send();
    }
    
    protected function recomputeStats(): void
    {
        $col = collect($this->preview);
        $this->stats['total'] = $col->count();
        $this->stats['cambiar'] = $col->where('estado','ok_cambiar')->count();
        $this->stats['sin_cambio'] = $col->where('estado','sin_cambio')->count();
        $this->stats['errores'] = $col->where('estado','error')->count();
        $this->stats['duplicados'] = $col->where('estado','duplicado')->count();
    }
    
    public function apply(CargoMassUpdateService $service): void
    {
        if (empty($this->preview)) {
            Notification::make()->danger()->title('Primero procese un archivo')->send();
            return;
        }
        if ($this->onlyValidate) {
            Notification::make()->danger()->title('Modo sólo validar activo')->body('Desactive para aplicar cambios')->send();
            return;
        }
        $dtos = collect($this->preview)->map(function($r){
            $dto = new CargoMontoUpdateRow($r['row']);
            $dto->codigoCargo = $r['codigo'];
            $dto->nombreExcel = $r['nombre_excel'];
            $dto->nombreBD = $r['nombre_bd'];
            $dto->montoActual = $r['monto_actual'];
            $dto->montoNuevo = $r['monto_nuevo'];
            $dto->estado = $r['estado'];
            $dto->errors = $r['errores'];
            $dto->warnings = $r['warnings'];
            $dto->valid = $r['valid'];
            return $dto;
        });
        $res = $service->apply($dtos, auth()->id(), true, $this->originalFilename);
        Notification::make()->success()->title('Actualización completada')
            ->body("Actualizados: {$res['updated']} | Saltados: {$res['skipped']} | Errores: {$res['errors']}")
            ->send();
        // Refrescar montos actuales en preview para reflejar cambios
        foreach ($this->preview as &$r) {
            if ($r['estado']==='ok_cambiar') {
                $r['monto_actual'] = $r['monto_nuevo'];
                $r['estado'] = 'sin_cambio';
                $r['valid'] = true;
            }
        }
        unset($r);
        $this->recomputeStats();
    }
    
    public function downloadErrores(): ?\Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (empty($this->preview)) return null;
        $errores = collect($this->preview)->filter(fn($r)=> $r['estado']==='error');
        if ($errores->isEmpty()) return null;
        $csv = implode(',', ['fila','codigo','nombre_excel','nombre_bd','monto_actual','monto_nuevo','errores','warnings'])."\n";
        foreach ($errores as $r) {
            $csv .= implode(',', [
                $r['row'],
                $r['codigo'],
                $this->escapeCsv((string)$r['nombre_excel']),
                $this->escapeCsv((string)$r['nombre_bd']),
                $r['monto_actual'],
                $r['monto_nuevo'],
                $this->escapeCsv(implode('|',$r['errores'])),
                $this->escapeCsv(implode('|',$r['warnings'])),
            ])."\n";
        }
        $filename = 'errores_actualizacion_cargos_'.now()->format('Ymd_His').'.csv';
        return response()->streamDownload(function() use ($csv){ echo $csv; }, $filename, ['Content-Type'=>'text/csv']);
    }
}
