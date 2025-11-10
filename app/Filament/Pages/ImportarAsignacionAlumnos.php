<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Livewire\WithFileUploads;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PlantillaAsignacionAlumnosExport;
use App\Exports\ErroresAsignacionAlumnosExport;
use App\Services\Import\AlumnoAssignmentImportService;
use App\Filament\Pages\Concerns\WithAssignmentFileHandling;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class ImportarAsignacionAlumnos extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;
    use WithFileUploads;
    use WithAssignmentFileHandling;
       use HasPageShield; 

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Asignaciones';
    protected static ?string $title = 'Importar Asignación Alumnos';
    protected static string $view = 'filament.pages.importar-asignacion-alumnos';

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
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
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

    protected function getFormModel(): \Illuminate\Database\Eloquent\Model|string|null
    {
        return static::class;
    }

    public function parseFile(AlumnoAssignmentImportService $service): void
    {
        if (!$this->file) {
            try {
                $state = $this->form->getState();
                if (isset($state['file']) && $state['file']) {
                    $this->file = $state['file'];
                }
            } catch (\Throwable $e) {
                \Log::debug('[ImportarAsignacionAlumnos] No se pudo obtener state del form', ['ex'=>$e->getMessage()]);
            }
        }
        $meta = $this->resolveFileMeta();
        if (!$meta || !($meta['abs'] ?? null)) {
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
        $this->preview = $rows->map(fn($dto) => [
            'row' => $dto->rowNumber,
            'codigo' => $dto->codigo,
            'dni' => $dto->dni,
            'paterno' => $dto->paterno,
            'materno' => $dto->materno,
            'nombres' => $dto->nombres,
            'cargo' => $dto->cargoNombre,
            'local' => $dto->localNombre,
            'fecha' => $dto->fechaISO,
            'errores' => $dto->errors,
            'warnings' => $dto->warnings,
            'valid' => $dto->valid,
            'cargo_id' => $dto->cargoId,
            'local_id' => $dto->localId,
            'proceso_fecha_id' => $dto->procesoFechaId,
            'reactivate' => $dto->willReactivate,
        ])->toArray();

        Notification::make()->success()->title('Procesado')->body('Se generó la vista previa.')->send();
    }

    public function import(AlumnoAssignmentImportService $service): void
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
            $dto = new \App\DTO\Import\AlumnoAssignmentRow($arr['row']);
            $dto->codigo = $arr['codigo'];
            $dto->dni = $arr['dni'];
            $dto->paterno = $arr['paterno'];
            $dto->materno = $arr['materno'];
            $dto->nombres = $arr['nombres'];
            $dto->cargoNombre = $arr['cargo'];
            $dto->localNombre = $arr['local'];
            $dto->fechaISO = $arr['fecha'];
            $dto->errors = $arr['errores'];
            $dto->warnings = $arr['warnings'];
            $dto->valid = $arr['valid'];
            $dto->cargoId = $arr['cargo_id'] ?? null;
            $dto->localId = $arr['local_id'] ?? null;
            $dto->procesoFechaId = $arr['proceso_fecha_id'] ?? null;
            $dto->willReactivate = $arr['reactivate'] ?? false;
            return $dto;
        });

        $allValid = $collection->every(fn($d) => $d->valid);
        if (!$allValid && !$this->allowPartial) {
            Notification::make()->danger()->title('Existen errores')->body('Corrija el archivo o active la importación parcial.')->send();
            return;
        }
        $original = $meta['original'] ?? null;
        $storedPath = $meta['stored'] ?? null;
        $historicalPath = null;
        if ($original || ($meta['abs'] ?? false)) {
            $ext = pathinfo($original, PATHINFO_EXTENSION);
            $safeOriginal = pathinfo($original, PATHINFO_FILENAME);
            if (!$original) {
                $tmpExt = pathinfo($meta['abs'], PATHINFO_EXTENSION);
                if (!$ext && $tmpExt) { $ext = $tmpExt; }
                if (!$safeOriginal) { $safeOriginal = 'import_alumnos'; }
            }
            $timestamped = now()->format('Ymd_His').'_'.$safeOriginal.'.'.$ext;
            $destRel = 'imports/history/'.$timestamped;
            $destAbs = storage_path('app/'.$destRel);
            @mkdir(dirname($destAbs), 0777, true);
            $src = null;
            if ($storedPath) {
                $candidate = storage_path('app/'.$storedPath);
                if (is_file($candidate)) $src = $candidate;
            }
            if (!$src && ($meta['abs'] ?? null) && is_file($meta['abs'])) { $src = $meta['abs']; }
            if ($src && @copy($src, $destAbs)) { $historicalPath = $destRel; }
        }
        $res = $service->import($collection, $this->allowPartial, $original);
        if ($historicalPath && class_exists(\App\Models\ImportJobLog::class)) {
            \App\Models\ImportJobLog::latest('id')->where('filename_original', $original)->first()?->update(['file_path' => $historicalPath]);
        }
        Notification::make()->success()->title('Importación completada')->body("Filas importadas: {$res['imported']} | Omitidas: {$res['skipped']}")->send();
    }

    public function downloadErrores(): ?\Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (empty($this->preview)) return null;
        $csv = implode(',', ['fila','codigo','dni','paterno','materno','nombres','cargo','local','fecha','errores','warnings']) . "\n";
        foreach ($this->preview as $r) {
            if ($r['valid']) continue;
            $csv .= implode(',', [
                $r['row'],
                $r['codigo'],
                $r['dni'],
                $this->escapeCsv($r['paterno']),
                $this->escapeCsv($r['materno']),
                $this->escapeCsv($r['nombres']),
                $this->escapeCsv($r['cargo']),
                $this->escapeCsv($r['local']),
                $r['fecha'],
                $this->escapeCsv(implode('|', $r['errores'])),
                $this->escapeCsv(implode('|', $r['warnings'])),
            ]) . "\n";
        }
        $filename = 'errores_import_alumnos_' . now()->format('Ymd_His') . '.csv';
        return response()->streamDownload(function () use ($csv) { echo $csv; }, $filename, [ 'Content-Type' => 'text/csv' ]);
    }

    public function downloadErroresXlsx(): ?\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        if (empty($this->preview)) return null;
        $errores = collect($this->preview)->filter(fn($r)=> !$r['valid']);
        if ($errores->isEmpty()) { return null; }
        return Excel::download(new ErroresAsignacionAlumnosExport($errores), 'errores_import_alumnos.xlsx');
    }

    public function downloadPlantilla(): ?\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return Excel::download(new PlantillaAsignacionAlumnosExport(), 'plantilla_asignacion_alumnos.xlsx');
    }
}

