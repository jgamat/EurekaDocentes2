<?php

namespace App\Services\Import;

use App\DTO\Import\AlumnoAssignmentRow;
use App\Models\Alumno;
use App\Models\ProcesoAlumno;
use App\Models\ProcesoFecha;
use App\Models\Locales;
use App\Models\LocalesMaestro;
use App\Models\ExperienciaAdmision;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\LocalCargo;
use App\Services\Import\Concerns\SharedAssignmentImport;

class AlumnoAssignmentImportService
{
    use SharedAssignmentImport; // reutiliza normalizaciones de fecha/nombres de local y cargo

    /**
     * Parse raw rows (array de arrays) y devuelve Collection<AlumnoAssignmentRow>
     */
    public function parse(array $rawRows): Collection
    {
        $out = collect();
        // Precargar fechas de proceso para evitar consultas repetidas; se asume columna real 'profec_dFecha'
        $fechasProceso = \App\Models\ProcesoFecha::query()
            ->select('profec_iCodigo','profec_dFecha')
            ->get()
            ->mapWithKeys(function($f){
                try { $key = \Carbon\Carbon::parse($f->profec_dFecha)->format('Y-m-d'); } catch (\Throwable $e) { $key = null; }
                return $key ? [$key => $f] : [];
            });
    // Catálogo de locales maestro e instancias (homologado con docentes)
    $catalogoLocalesMaestro = LocalesMaestro::query()->select('locma_iCodigo','locma_vcNombre')->get();
    $normLocal = fn(string $s)=> preg_replace('/\s+/',' ',trim(mb_strtoupper(strtr($s,['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U']))));
    $localMaestroMatch = $catalogoLocalesMaestro->mapWithKeys(fn($l)=> [ $normLocal($l->locma_vcNombre) => $l ]);
    $instanciasLocales = Locales::query()->select('loc_iCodigo','locma_iCodigo','profec_iCodigo')->get();
    $instanciaIndex = $instanciasLocales->mapWithKeys(fn($r)=> [ $r->locma_iCodigo.'|'.$r->profec_iCodigo => $r->loc_iCodigo ]);

    foreach ($rawRows as $idx => $row) {
            if (!is_array($row)) continue;
            $row = $this->canonicalizeRow($row);
            $dto = new AlumnoAssignmentRow($idx + 2); // considerar encabezado en archivo estándar
            $dto->codigo = $this->norm($row['codigo'] ?? null);
            $dto->dni = $this->norm($row['dni'] ?? null);
            $dto->paterno = $this->norm($row['paterno'] ?? null); // opcional
            $dto->materno = $this->norm($row['materno'] ?? null); // opcional
            $dto->nombres = $this->norm($row['nombres'] ?? null); // puede contener nombre completo
            $dto->cargoNombre = $this->normalizeCargoName(isset($row['cargo']) ? (string)$row['cargo'] : null);
            $dto->localNombre = $this->normalizeLocalName(isset($row['local']) ? (string)$row['local'] : null);
            $dto->fechaISO = $this->parseFecha($row['fecha'] ?? null);

            // Validaciones básicas
            if (!$dto->codigo) {
                $dto->addError('Código obligatorio');
            }
            if (!$dto->fechaISO) {
                $dto->addError('Fecha inválida');
            }
            if (!$dto->cargoNombre) {
                $dto->addError('Cargo vacío');
            }
            if (!$dto->localNombre) {
                $dto->addError('Local vacío');
            }

            // Lookup alumno por código
            $alumno = null;
            if ($dto->codigo) {
                $alumno = Alumno::where('alu_vcCodigo', $dto->codigo)->first();
                if (!$alumno) {
                    $dto->addError('Alumno no encontrado');
                } else {
                    // Validar nombres completos si se proporcionan todas las partes
                    // Si hay apellidos separados, validar con ellos; de lo contrario comparar nombres completos vs concatenado registro
                    $base = trim($alumno->alu_vcPaterno.' '.$alumno->alu_vcMaterno.' '.$alumno->alu_vcNombre);
                    if ($dto->paterno || $dto->materno) {
                        $importConcat = trim(($dto->paterno ?? '').' '.($dto->materno ?? '').' '.($dto->nombres ?? ''));
                    } else {
                        $importConcat = $dto->nombres ?? '';
                    }
                    if ($importConcat && $this->nombresDiscrepan($base, $importConcat)) {
                        $dto->addWarning('Nombre no coincide con registro');
                    }
                }
            }

            // cargo/local
            $cargo = null;
            if ($dto->cargoNombre) {
                // Buscar por nombre en el maestro, luego localizar instancia para la misma fecha (si la fecha/proceso está identificada después).
                $cargoMaestro = \App\Models\ExperienciaAdmisionMaestro::whereRaw('UPPER(expadmma_vcNombre)=?', [strtoupper($dto->cargoNombre)])->first();
                if ($cargoMaestro && $dto->fechaISO) {
                    if (!$dto->procesoFechaId) {
                        $pf = $fechasProceso[$dto->fechaISO] ?? null;
                        if ($pf) { $dto->procesoFechaId = $pf->profec_iCodigo; }
                    }
                    if ($dto->procesoFechaId) {
                        $cargo = ExperienciaAdmision::where('expadmma_iCodigo', $cargoMaestro->expadmma_iCodigo)
                            ->where('profec_iCodigo', $dto->procesoFechaId)
                            ->first();
                    }
                }
                if (!$cargo && $cargoMaestro) {
                    // fallback: tomar cualquier instancia si no se pudo mapear por fecha (no bloquea pero advierte)
                    $cargo = ExperienciaAdmision::where('expadmma_iCodigo', $cargoMaestro->expadmma_iCodigo)->first();
                }
                if (!$cargoMaestro) { $dto->addError('Cargo maestro no encontrado'); }
                elseif (!$cargo) { $dto->addWarning('Cargo sin instancia para la fecha'); }
                if ($cargo) { $dto->cargoId = $cargo->expadm_iCodigo; }
            }

            // Local (maestro + instancia) homologado y corregido: no colocar locma_iCodigo en localId.
            if ($dto->localNombre) {
                $locKey = $normLocal($dto->localNombre);
                $localMaestro = $localMaestroMatch[$locKey] ?? null;
                if (!$localMaestro) {
                    $dto->addError('Local no existe en LocalesMaestro');
                } else {
                    $dto->localMaestroId = $localMaestro->locma_iCodigo;
                    if ($dto->procesoFechaId) {
                        $idxKey = $localMaestro->locma_iCodigo.'|'.$dto->procesoFechaId;
                        if (isset($instanciaIndex[$idxKey])) {
                            $dto->localId = $instanciaIndex[$idxKey];
                        } else {
                            $dto->addWarning('Instancia local para fecha será creada');
                            // localId queda null y se creará durante import()
                        }
                    } else {
                        $dto->addWarning('Local pendiente de fecha');
                    }
                }
            }

            // proceso fecha activo para la fecha dada
            if ($dto->fechaISO) {
                $procFecha = $fechasProceso[$dto->fechaISO] ?? null;
                if (!$procFecha) { $dto->addError('Fecha no corresponde a proceso activo'); }
                else { $dto->procesoFechaId = $procFecha->profec_iCodigo; }
            }

            // Homologar a docentes/administrativos: sólo evaluamos existencia por (alumno + procesoFecha)
            if ($dto->codigo && $dto->procesoFechaId) {
                $exists = ProcesoAlumno::where('profec_iCodigo', $dto->procesoFechaId)
                    ->where('alu_vcCodigo', $dto->codigo)
                    ->where('proalu_iAsignacion', 1)
                    ->exists();
                if ($exists) {
                    $dto->addError('Ya asignado en fecha');
                } else {
                    $pending = ProcesoAlumno::where('profec_iCodigo', $dto->procesoFechaId)
                        ->where('alu_vcCodigo', $dto->codigo)
                        ->where('proalu_iAsignacion', 0)
                        ->exists();
                    if ($pending) {
                        $dto->addWarning('Reactivará asignación previa');
                        $dto->willReactivate = true;
                    }
                }
            }

            $out->push($dto);
        }
        return $out;
    }

    /**
     * Ejecuta la importación.
     */
    public function import(Collection $dtos, bool $allowPartial, ?string $originalFilename = null): array
    {
        $imported = 0; $skipped = 0; $localCargoAdjust = [];
        DB::transaction(function () use ($dtos, &$imported, &$skipped, &$localCargoAdjust) {
            foreach ($dtos as $dto) {
                if (!$dto instanceof AlumnoAssignmentRow) { $skipped++; continue; }
                if (!$dto->valid) { $skipped++; continue; }
                if (!$dto->procesoFechaId) { $skipped++; continue; }
                // Asegurar instancia de local: crear usando localMaestroId si localId aún null
                if ($dto->localNombre && $dto->procesoFechaId) {
                    if (!$dto->localId) {
                        // Crear maestro si falta
                        $maestro = $dto->localMaestroId
                            ? LocalesMaestro::firstOrCreate(['locma_iCodigo' => $dto->localMaestroId], ['locma_vcNombre' => $dto->localNombre])
                            : LocalesMaestro::firstOrCreate(['locma_vcNombre' => $dto->localNombre]);
                        $inst = Locales::firstOrCreate([
                            'locma_iCodigo' => $maestro->locma_iCodigo,
                            'profec_iCodigo' => $dto->procesoFechaId,
                        ]);
                        $dto->localId = $inst->loc_iCodigo;
                    } elseif (!Locales::where('loc_iCodigo', $dto->localId)->exists()) {
                        // Valor legado erróneo (probablemente locma_iCodigo) => recrear instancia
                        $maestroId = $dto->localMaestroId ?? $dto->localId;
                        $inst = Locales::firstOrCreate([
                            'locma_iCodigo' => $maestroId,
                            'profec_iCodigo' => $dto->procesoFechaId,
                        ]);
                        $dto->localId = $inst->loc_iCodigo;
                    }
                }

                // Crear instancia de cargo si no existe (homologado a docentes/administrativos)
                if ($dto->procesoFechaId && !$dto->cargoId && $dto->cargoNombre) {
                    $maestroCargo = \App\Models\ExperienciaAdmisionMaestro::firstOrCreate(['expadmma_vcNombre' => $dto->cargoNombre]);
                    $instCargo = ExperienciaAdmision::firstOrCreate([
                        'expadmma_iCodigo' => $maestroCargo->expadmma_iCodigo,
                        'profec_iCodigo' => $dto->procesoFechaId,
                    ], [ 'expadm_fMonto' => 0 ]);
                    $dto->cargoId = $instCargo->expadm_iCodigo;
                }

                // Preparar LocalCargo para conteo
                if ($dto->localId && $dto->cargoId) {
                    $keyLC = $dto->localId.'|'.$dto->cargoId;
                    $localCargo = LocalCargo::where('loc_iCodigo', $dto->localId)
                        ->where('expadm_iCodigo', $dto->cargoId)
                        ->lockForUpdate()
                        ->first();
                    if (!$localCargo) {
                        $localCargo = LocalCargo::create([
                            'loc_iCodigo' => $dto->localId,
                            'expadm_iCodigo' => $dto->cargoId,
                            'loccar_iVacante' => 0,
                            'loccar_iOcupado' => 0,
                        ]);
                        $localCargoAdjust[$keyLC] = ['increment'=>0,'model'=>$localCargo,'new'=>true];
                    }
                    if (!isset($localCargoAdjust[$keyLC])) {
                        $localCargoAdjust[$keyLC] = ['increment'=>0,'model'=>$localCargo,'new'=>false];
                    }
                }

                // Reactivar si corresponde (homologado a docentes/administrativos): buscar por alumno+proceso inactivo
                $reactivado = false;
                if ($dto->willReactivate && $dto->cargoId && $dto->localId) {
                    $row = ProcesoAlumno::where('profec_iCodigo', $dto->procesoFechaId)
                        ->where('alu_vcCodigo', $dto->codigo)
                        ->where('proalu_iAsignacion', 0)
                        ->lockForUpdate()
                        ->first();
                    if ($row) {
                        $row->update([
                            'loc_iCodigo' => $dto->localId,
                            'expadm_iCodigo' => $dto->cargoId,
                            'proalu_iAsignacion' => 1,                            
                            'proalu_dtFechaAsignacion' => now(),
                            'user_id' => auth()->id(),
                        ]);
                        $reactivado = true;
                        $imported++;
                    }
                }

                if (!$reactivado) {
                    // Si no tenemos cargo o local no podemos crear un registro consistente
                    if (!$dto->cargoId || !$dto->localId) { $skipped++; continue; }
                    // Antes de crear, protección extra: si existe activo no crear; si existe inactivo y no fue marcado, reactivar.
                    $activoExiste = ProcesoAlumno::where('profec_iCodigo', $dto->procesoFechaId)
                        ->where('alu_vcCodigo', $dto->codigo)
                        ->where('proalu_iAsignacion', 1)
                        ->exists();
                    if ($activoExiste) {
                        $skipped++;
                    } else {
                        $inactivo = ProcesoAlumno::where('profec_iCodigo', $dto->procesoFechaId)
                            ->where('alu_vcCodigo', $dto->codigo)
                            ->where('proalu_iAsignacion', 0)
                            ->lockForUpdate()
                            ->first();
                        if ($inactivo) {
                            $inactivo->update([
                                'loc_iCodigo' => $dto->localId,
                                'expadm_iCodigo' => $dto->cargoId,
                                'proalu_iAsignacion' => 1,
                                'proalu_dtFechaAsignacion' => now(),
                                'user_id' => auth()->id(),
                            ]);
                            $imported++;
                        } else {
                            ProcesoAlumno::create([
                                'alu_vcCodigo' => $dto->codigo,
                                'profec_iCodigo' => $dto->procesoFechaId,
                                'loc_iCodigo' => $dto->localId,
                                'expadm_iCodigo' => $dto->cargoId,
                                'proalu_dtFechaAsignacion' => now(),
                                'user_id' => auth()->id(),
                                'proalu_iAsignacion' => 1,
                            ]);
                            $imported++;
                        }
                    }
                }

                // Contabilizar incremento ocupacional (nuevo o reactivado)
                if ($dto->localId && $dto->cargoId) {
                    $keyLC = $dto->localId.'|'.$dto->cargoId;
                    if (isset($localCargoAdjust[$keyLC])) {
                        $localCargoAdjust[$keyLC]['increment']++;
                    }
                }
            }

            // Aplicar flujo de ajuste: primero ocupados, luego ampliar vacantes si necesario
            foreach ($localCargoAdjust as $info) {
                $lc = $info['model'];
                $increment = (int)($info['increment'] ?? 0);
                if ($info['new']) {
                    $lc->loccar_iOcupado = $increment;
                    if ($lc->loccar_iVacante < $lc->loccar_iOcupado) {
                        $lc->loccar_iVacante = $lc->loccar_iOcupado;
                    }
                    $lc->save();
                } else {
                    if ($increment > 0) {
                        $lc->loccar_iOcupado += $increment;
                    }
                    $diff = $lc->loccar_iVacante - $lc->loccar_iOcupado;
                    if ($diff < 0) {
                        $lc->loccar_iVacante += abs($diff);
                    }
                    $lc->save();
                }
            }
        });
        return [ 'imported' => $imported, 'skipped' => $skipped ];
    }
}
