<?php

namespace App\Filament\Pages;

use App\DTO\Import\AdministrativoAssignmentRow;
use App\Exports\ErroresAsignacionAdministrativosExport;
use App\Exports\PlantillaAsignacionAdministrativosExport;
use App\Filament\Pages\Concerns\WithAssignmentFileHandling;
use App\Models\ImportJobLog;
use App\Services\Import\AdministrativoAssignmentImportService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportarAsignacionAdministrativos extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;
    use HasPageShield;
    use WithAssignmentFileHandling;
    use WithFileUploads;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Asignaciones';

    protected static ?string $title = 'Importar Asignación Administrativos';

    protected string $view = 'filament.pages.importar-asignacion-administrativos';

    public $file = null;

    public array $preview = [];

    public bool $allowPartial = false;

    public bool $onlyValidate = false;

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\FileUpload::make('file')
                ->label('Archivo (CSV / XLSX)')
                ->directory('imports/temp')
                ->preserveFilenames()
                ->acceptedFileTypes([
                    'text/csv',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->maxSize(5120)
                ->required(),
            Forms\Components\Toggle::make('allowPartial')
                ->label('Permitir importar parcialmente')
                ->helperText('Si está desactivado sólo se permitirá la importación cuando no existan errores.')
                ->default(false),
            Forms\Components\Toggle::make('onlyValidate')
                ->label('Sólo validar (no importar)')
                ->default(false)
                ->helperText('Actívelo para revisar sin posibilidad de ejecutar la importación.'),
        ];
    }

    protected function getFormModel(): Model|string|null
    {
        return static::class;
    }

    public function parseFile(AdministrativoAssignmentImportService $service): void
    {
        if (! $this->file) {
            try {
                $state = $this->form->getState();
                if (isset($state['file']) && $state['file']) {
                    $this->file = $state['file'];
                }
            } catch (\Throwable $e) {
                Log::debug('[ImportarAsignacionAdministrativos] No se pudo obtener state del form', ['ex' => $e->getMessage()]);
            }
        }
        $meta = $this->resolveFileMeta();
        if (! $meta || ! ($meta['abs'] ?? null)) {
            Notification::make()->danger()->title('Seleccione un archivo válido')->send();

            return;
        }
        $abs = $meta['abs'];
        $rawRows = $this->readSpreadsheet($abs);
        if (empty($rawRows)) {
            Notification::make()->danger()->title('Archivo vacío o formato no soportado')->send();

            return;
        }
        $rows = $service->parse($rawRows);
        $this->preview = $rows->map(fn ($dto) => [
            'row' => $dto->rowNumber,
            'codigo' => $dto->codigo,
            'dni' => $dto->dni,
            'nombres' => $dto->nombres,
            'cargo' => $dto->cargoNombre,
            'local' => $dto->localNombre,
            'fecha' => $dto->fechaISO,
            'monto' => $dto->monto,
            'monto_estado' => $dto->montoEstado,
            'errores' => $dto->errors,
            'warnings' => $dto->warnings,
            'valid' => $dto->valid,
            'cargo_id' => $dto->cargoId,
            'local_id' => $dto->localId,
            'proceso_fecha_id' => $dto->procesoFechaId,
        ])->toArray();

        Notification::make()->success()->title('Procesado')->body('Se generó la vista previa.')->send();
    }

    public function import(AdministrativoAssignmentImportService $service): void
    {
        $meta = $this->resolveFileMeta();
        if (empty($this->preview)) {
            Notification::make()->danger()->title('Primero procese un archivo')->send();

            return;
        }
        if ($this->onlyValidate) {
            Notification::make()->danger()->title('Modo sólo validación activo')->body('Desactive "Sólo validar" para importar.')->send();

            return;
        }
        $collection = collect($this->preview)->map(function ($arr) {
            $dto = new AdministrativoAssignmentRow($arr['row']);
            $dto->codigo = $arr['codigo'];
            $dto->dni = $arr['dni'];
            $dto->nombres = $arr['nombres'];
            $dto->cargoNombre = $arr['cargo'];
            $dto->localNombre = $arr['local'];
            $dto->fechaISO = $arr['fecha'];
            $dto->monto = ($arr['monto'] ?? null) === null || $arr['monto'] === '' ? null : (float) $arr['monto'];
            $dto->montoEstado = $arr['monto_estado'] ?? null;
            $dto->errors = $arr['errores'];
            $dto->warnings = $arr['warnings'];
            $dto->valid = $arr['valid'];
            $dto->cargoId = $arr['cargo_id'] ?? null;
            $dto->localId = $arr['local_id'] ?? null;
            $dto->procesoFechaId = $arr['proceso_fecha_id'] ?? null;

            return $dto;
        });

        $allValid = $collection->every(fn ($d) => $d->valid);
        if (! $allValid && ! $this->allowPartial) {
            Notification::make()->danger()->title('Existen errores')->body('Corrija el archivo o active la importación parcial.')->send();

            return;
        }
        $original = $meta['original'] ?? null;
        $storedPath = $meta['stored'] ?? null;
        $historicalPath = null;
        if ($original || ($meta['abs'] ?? false)) {
            $ext = pathinfo($original, PATHINFO_EXTENSION);
            $safeOriginal = pathinfo($original, PATHINFO_FILENAME);
            if (! $original) {
                $tmpExt = pathinfo($meta['abs'], PATHINFO_EXTENSION);
                if (! $ext && $tmpExt) {
                    $ext = $tmpExt;
                }
                if (! $safeOriginal) {
                    $safeOriginal = 'import_administrativos';
                }
            }
            $timestamped = now()->format('Ymd_His').'_'.$safeOriginal.'.'.$ext;
            $destRel = 'imports/history/'.$timestamped;
            $destAbs = storage_path('app/'.$destRel);
            @mkdir(dirname($destAbs), 0777, true);
            $src = null;
            if ($storedPath) {
                $candidate = storage_path('app/'.$storedPath);
                if (is_file($candidate)) {
                    $src = $candidate;
                }
            }
            if (! $src && ($meta['abs'] ?? null) && is_file($meta['abs'])) {
                $src = $meta['abs'];
            }
            if ($src && @copy($src, $destAbs)) {
                $historicalPath = $destRel;
            }
        }
        $res = $service->import($collection, $this->allowPartial, $original);
        if ($historicalPath && class_exists(ImportJobLog::class)) {
            ImportJobLog::latest('id')->where('filename_original', $original)->first()?->update(['file_path' => $historicalPath]);
        }
        Notification::make()->success()->title('Importación completada')->body("Filas importadas: {$res['imported']} | Omitidas: {$res['skipped']}")->send();
    }

    public function downloadErrores(): ?StreamedResponse
    {
        if (empty($this->preview)) {
            return null;
        }
        $csv = implode(',', ['fila', 'codigo', 'dni', 'cargo', 'local', 'fecha', 'monto', 'errores', 'warnings'])."\n";
        foreach ($this->preview as $r) {
            if ($r['valid']) {
                continue;
            }
            $csv .= implode(',', [
                $r['row'],
                $r['codigo'],
                $r['dni'],
                $this->escapeCsv($r['cargo']),
                $this->escapeCsv($r['local']),
                $r['fecha'],
                $r['monto'] ?? '',
                $this->escapeCsv(implode('|', $r['errores'])),
                $this->escapeCsv(implode('|', $r['warnings'])),
            ])."\n";
        }
        $filename = 'errores_import_administrativos_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function downloadErroresXlsx(): ?BinaryFileResponse
    {
        if (empty($this->preview)) {
            return null;
        }
        $errores = collect($this->preview)->filter(fn ($r) => ! $r['valid']);
        if ($errores->isEmpty()) {
            return null;
        }

        return Excel::download(new ErroresAsignacionAdministrativosExport($errores), 'errores_import_administrativos.xlsx');
    }

    public function downloadPlantilla(): ?BinaryFileResponse
    {
        return Excel::download(new PlantillaAsignacionAdministrativosExport, 'plantilla_asignacion_administrativos.xlsx');
    }

    // File handling methods now provided by WithAssignmentFileHandling trait
}
