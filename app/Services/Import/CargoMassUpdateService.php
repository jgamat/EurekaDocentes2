<?php

namespace App\Services\Import;

use App\DTO\Import\CargoMontoUpdateRow;
use App\Models\ExperienciaAdmision;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CargoMassUpdateService
{
    /**
     * Parse rows coming from readSpreadsheet() of WithAssignmentFileHandling.
     * Expects header keys lowercased. We accept headings from export: nro,codigo_cargo,nombre_cargo,fecha_seleccionada,expadm_fmonto
     * We will gracefully skip unknown columns.
     */
    public function parse(array $rawRows): Collection
    {
        $rows = collect();
        if (empty($rawRows)) return $rows;
        // A partir de la corrección en readSpreadsheet() ya deberíamos recibir encabezados reales
        // (nro, codigo_cargo, nombre_cargo, fecha_seleccionada, expadm_fmonto). Fallback: si faltan, intentar mapear heurísticamente.
        if (!empty($rawRows) && !array_key_exists('codigo_cargo', $rawRows[0])) {
            // Intentar detectar columnas por posiciones si están con nombres genéricos colX
            $first = $rawRows[0];
            $keys = array_keys($first);
            if (count($keys) >= 5) {
                // Renombrar si parece que son genéricos
                $renamed = [];
                foreach ($rawRows as $r) {
                    $vals = array_values($r);
                    $assoc = [
                        'nro' => $vals[0] ?? null,
                        'codigo_cargo' => $vals[1] ?? null,
                        'nombre_cargo' => $vals[2] ?? null,
                        'fecha_seleccionada' => $vals[3] ?? null,
                        'expadm_fmonto' => $vals[4] ?? null,
                    ];
                    // Adjuntar extras si los hubiera
                    for ($i=5;$i<count($vals);$i++){ $assoc['col'.$i] = $vals[$i]; }
                    $renamed[] = $assoc;
                }
                $rawRows = $renamed;
            }
        }

        // Collect all cargo codes to fetch existing records.
        $codigoList = [];
        foreach ($rawRows as $i => $assoc) {
            // Skip rows that clearly don't have a cargo code
            $codigoRaw = $assoc['codigo_cargo'] ?? $assoc['codigo'] ?? null;
            $hasValues = collect($assoc)->filter(fn($v)=> $v!==null && $v!=='')->isNotEmpty();
            if (!$hasValues) continue;
            if ($codigoRaw !== null) {
                $codigoList[] = $codigoRaw;
            }
        }
        $codigoList = collect($codigoList)->filter()->map(fn($c)=> (int) filter_var($c, FILTER_SANITIZE_NUMBER_INT))->unique()->values();
        $cargos = ExperienciaAdmision::with('maestro')->whereIn('expadm_iCodigo',$codigoList)->get()->keyBy('expadm_iCodigo');

        $seen = [];
        foreach ($rawRows as $idx => $assoc) {
            $rowNumber = $idx + 2; // approximate (since original export had 2 rows before headings); purely informative
            $dto = new CargoMontoUpdateRow($rowNumber);
            $hasValues = collect($assoc)->filter(fn($v)=> $v!==null && $v!=='')->isNotEmpty();
            if (!$hasValues) continue; // ignore fully empty

            $codigoRaw = $assoc['codigo_cargo'] ?? $assoc['codigo'] ?? null;
            if ($codigoRaw === null || $codigoRaw === '') {
                $dto->errors[] = 'Código de cargo vacío';
            } else {
                if (!preg_match('/^\d+$/', (string)$codigoRaw)) {
                    $dto->errors[] = 'Código de cargo no numérico';
                } else {
                    $dto->codigoCargo = (int)$codigoRaw;
                }
            }

            $nombreExcel = (string)($assoc['nombre_cargo'] ?? '');
            $dto->nombreExcel = $nombreExcel !== '' ? $nombreExcel : null;

            $montoNuevoRaw = $assoc['expadm_fmonto'] ?? $assoc['monto'] ?? null;
            if ($montoNuevoRaw === null || $montoNuevoRaw === '') {
                $dto->errors[] = 'Monto nuevo vacío';
            } else {
                $clean = str_replace([',',' '],'',(string)$montoNuevoRaw);
                if (!is_numeric($clean)) {
                    $dto->errors[] = 'Monto no numérico';
                } else {
                    $monto = (float)$clean;
                    if ($monto < 0) {
                        $dto->errors[] = 'Monto negativo';
                    } elseif ($monto > 10000000) {
                        $dto->warnings[] = 'Monto inusualmente alto';
                    }
                    $dto->montoNuevo = $monto;
                }
            }

            if ($dto->codigoCargo !== null) {
                if (isset($seen[$dto->codigoCargo])) {
                    $dto->estado = 'duplicado';
                    $dto->warnings[] = 'Fila duplicada para código, se ignorará (se aplicará la última)';
                }
                $seen[$dto->codigoCargo] = true;
                $cargo = $cargos->get($dto->codigoCargo);
                if (!$cargo) {
                    $dto->errors[] = 'Cargo inexistente';
                } else {
                    $dto->nombreBD = $cargo->maestro?->expadmma_vcNombre;
                    $dto->montoActual = $cargo->expadm_fMonto !== null ? (float)$cargo->expadm_fMonto : null;
                    // Determinar cambio:
                    // Caso 1: ambos no null y diferencia insignificante => sin_cambio
                    // Caso 2: montoActual null y montoNuevo no-null => ok_cambiar
                    // Caso 3: montoActual no-null y montoNuevo null (si se permitiera) => ok_cambiar
                    if ($dto->montoNuevo !== null && $dto->montoActual !== null) {
                        if (abs($dto->montoNuevo - $dto->montoActual) < 0.00001) {
                            $dto->estado = 'sin_cambio';
                        }
                    } elseif ($dto->montoNuevo !== null && $dto->montoActual === null) {
                        $dto->estado = 'ok_cambiar';
                    } elseif ($dto->montoNuevo === null && $dto->montoActual !== null) {
                        // No marcamos error; permitiría potencialmente limpiar. Si no se desea, se podría marcar warning.
                        $dto->warnings[] = 'Monto nuevo vacío (no se aplicará cambio)';
                        $dto->estado = 'sin_cambio';
                    }
                    if ($dto->nombreExcel && $dto->nombreBD) {
                        $ratio = similar_text(mb_strtoupper($dto->nombreExcel), mb_strtoupper($dto->nombreBD), $percent);
                        // percent variable holds percentage similarity
                        if (($percent ?? 100) < 50) {
                            $dto->warnings[] = 'Nombre en archivo difiere del registrado';
                        }
                    }
                }
            }

            if (!empty($dto->errors)) {
                $dto->estado = 'error';
                $dto->valid = false;
            } else {
                if ($dto->estado === 'pending') {
                    // Llegamos aquí cuando no se estableció antes: significa que uno de los montos es null o ambos diferentes.
                    if ($dto->montoNuevo !== null && $dto->montoActual === null) {
                        $dto->estado = 'ok_cambiar';
                    } elseif ($dto->montoNuevo !== null && $dto->montoActual !== null && abs($dto->montoNuevo - $dto->montoActual) >= 0.00001) {
                        $dto->estado = 'ok_cambiar';
                    } else {
                        $dto->estado = 'sin_cambio';
                    }
                }
                $dto->valid = $dto->estado === 'ok_cambiar' || $dto->estado === 'sin_cambio';
            }

            $rows->push($dto);
        }

        // Keep only the LAST occurrence for each duplicated code (others flagged already). We'll not remove them; UI can show duplicados.
        return $rows;
    }

    /**
     * Apply updates for rows flagged ok_cambiar. Returns array summary.
     */
    public function apply(Collection $dtos, ?int $userId = null, bool $auditLog = true, ?string $archivoOriginal = null): array
    {
        $toUpdate = $dtos->filter(fn(CargoMontoUpdateRow $d)=> $d->estado === 'ok_cambiar');
        $updated = 0; $skipped = 0; $errors = 0; $log = [];
        if ($toUpdate->isEmpty()) {
            return ['updated'=>0,'skipped'=>$dtos->count(),'errors'=>0];
        }
        DB::transaction(function() use ($toUpdate, &$updated, &$skipped, &$errors, &$log, $userId, $auditLog, $archivoOriginal) {
            foreach ($toUpdate as $dto) {
                $cargo = ExperienciaAdmision::find($dto->codigoCargo);
                if (!$cargo) { $errors++; continue; }
                $old = $cargo->expadm_fMonto;
                $cargo->expadm_fMonto = $dto->montoNuevo;
                $cargo->save();
                $updated++;
                if ($auditLog) {
                    $log[] = [
                        'cargo_id' => $cargo->expadm_iCodigo,
                        'old' => $old,
                        'new' => $dto->montoNuevo,
                        'user_id' => $userId,
                        'ts' => now()->toDateTimeString(),
                        'archivo_original' => $archivoOriginal,
                    ];
                    if (class_exists(\App\Models\CargoMontoHistorial::class)) {
                        \App\Models\CargoMontoHistorial::create([
                            'expadm_iCodigo' => $cargo->expadm_iCodigo,
                            'monto_anterior' => $old,
                            'monto_nuevo' => $dto->montoNuevo,
                            'user_id' => $userId,
                            'archivo_original' => $archivoOriginal,
                            'fuente' => 'import_excel',
                            'aplicado_en' => now(),
                        ]);
                    }
                }
            }
        });
        if (!empty($log)) {
            foreach ($log as $entry) {
                \Log::info('[CargoMassUpdate] Cambio de monto', $entry);
            }
        }
        $skipped = $dtos->count() - $updated - $errors;
        return ['updated'=>$updated,'skipped'=>$skipped,'errors'=>$errors];
    }
}
