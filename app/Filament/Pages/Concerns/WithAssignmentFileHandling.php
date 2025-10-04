<?php

namespace App\Filament\Pages\Concerns;

use Maatwebsite\Excel\Facades\Excel;

/**
 * Trait reutilizable para páginas de importación (docentes / administrativos).
 * Encapsula lectura de archivos, resolución de metadatos y utilidades CSV.
 *
 * Requisitos de la clase consumidora:
 *  - Propiedad $file (mixed) con el estado del FileUpload (string|array|TemporaryUploadedFile|null)
 */
trait WithAssignmentFileHandling
{
    protected function escapeCsv(string $value): string
    {
        $needsQuotes = str_contains($value, ',') || str_contains($value, '"');
        $value = str_replace('"', '""', $value);
        return $needsQuotes ? '"' . $value . '"' : $value;
    }

    protected function readSpreadsheet(string $absPath): array
    {
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        if (in_array($ext,['xlsx','xls'])) {
            try {
                \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \App\Support\Import\DocentesStringValueBinder());
                $sheets = Excel::toArray(null, $absPath);
                $sheet = $sheets[0] ?? [];
                if (!$sheet) return [];
                $headers = [];$rows=[];
                // Detectar patrón de export con título + blank + headers reales.
                if (count($sheet) >= 3) {
                    $firstRow = array_map(fn($v)=> trim((string)$v), $sheet[0]);
                    $secondRow = array_map(fn($v)=> trim((string)$v), $sheet[1]);
                    $thirdRow = array_map(fn($v)=> trim((string)$v), $sheet[2]);
                    $firstRowJoined = strtolower(implode(' ', $firstRow));
                    $thirdHasCodigoCargo = in_array('codigo_cargo', array_map(fn($h)=> strtolower($h), $thirdRow), true);
                    $secondEmpty = count(array_filter($secondRow, fn($v)=> $v!=='')) === 0;
                    $looksLikeTitle = str_contains($firstRowJoined,'cargos utilizados') || str_contains($firstRowJoined,'historial de montos');
                    if ($looksLikeTitle && $secondEmpty && $thirdHasCodigoCargo) {
                        // Usar tercera fila como headers.
                        $headers = array_map(fn($h)=> strtolower(trim((string)$h)), $thirdRow);
                        // Data empieza en fila 4 (index 3)
                        for ($i=3; $i<count($sheet); $i++) {
                            $row = $sheet[$i];
                            if (count(array_filter($row, fn($v)=> $v!==null && $v!==''))===0) continue;
                            $assoc=[]; foreach ($row as $k=>$v){ $assoc[$headers[$k] ?? 'col'.$k] = $v; }
                            $rows[]=$assoc;
                        }
                        return $rows;
                    }
                }
                foreach ($sheet as $i=>$row) {
                    if ($i===0){ $headers = array_map(fn($h)=> strtolower(trim((string)$h)), $row); continue; }
                    if (count(array_filter($row, fn($v)=> $v!==null && $v!==''))===0) continue;
                    $assoc=[]; foreach ($row as $k=>$v){ $assoc[$headers[$k] ?? 'col'.$k] = $v; }
                    $rows[]=$assoc;
                }
                return $rows;
            } catch (\Throwable $e) { return []; }
        }
        if (in_array($ext,['csv','txt'])) {
            $lines = @file($absPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            if (!$lines) return [];
            $headers=[]; $rows=[];
            foreach($lines as $i=>$line){
                $parts = str_getcsv($line);
                if ($i===0){ $headers = array_map(fn($h)=> strtolower(trim($h)), $parts); continue; }
                $assoc=[]; foreach($parts as $k=>$v){ $assoc[$headers[$k] ?? 'col'.$k] = $v; }
                $rows[]=$assoc;
            }
            return $rows;
        }
        return [];
    }

    protected function resolveFileMeta(): ?array
    {
        if (!$this->file) return null;
        if (is_string($this->file)) {
            $rel = $this->file;
            return [ 'original' => basename($rel), 'stored' => $rel, 'abs' => storage_path('app/'.$rel) ];
        }
        if (is_array($this->file)) {
            $rel = $this->file['path'] ?? ($this->file['storedPath'] ?? null);
            $original = $this->file['name'] ?? ($rel ? basename($rel) : null);
            if ($rel) {
                return [ 'original' => $original, 'stored' => $rel, 'abs' => storage_path('app/'.$rel) ];
            }
            // Búsqueda profunda (alineada al comportamiento previo en página docentes)
            $path = $this->searchFirstPathRecursive($this->file);
            if ($path) {
                return ['original'=>null,'stored'=>null,'abs'=>$path];
            }
            return null;
        }
        if ($this->file instanceof \Livewire\TemporaryUploadedFile) {
            $original = $this->file->getClientOriginalName();
            $rel = method_exists($this->file,'getFilename') ? ('livewire-tmp/'.$this->file->getFilename()) : null;
            return [ 'original' => $original, 'stored' => $rel, 'abs' => $this->file->getRealPath() ];
        }
        return null;
    }

    protected function searchFirstPathRecursive($data, int $depth = 0)
    {
        if ($depth > 6) return null; // límite de profundidad para evitar loops
        if (is_string($data)) {
            if (@is_file($data) || preg_match('/php\w+\.tmp$/i', $data) || str_contains($data, 'imports/')) {
                return $data;
            }
            return null;
        }
        if (is_array($data)) {
            foreach ($data as $v) {
                $p = $this->searchFirstPathRecursive($v, $depth+1);
                if ($p) return $p;
            }
        } elseif (is_object($data)) {
            if (method_exists($data,'getRealPath')) {
                try { $rp = $data->getRealPath(); } catch (\Throwable $e) { $rp = null; }
                if ($rp && (@is_file($rp) || preg_match('/php\w+\.tmp$/i', $rp))) return $rp;
            }
        }
        return null;
    }
}
