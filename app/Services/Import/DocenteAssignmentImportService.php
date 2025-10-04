<?php

namespace App\Services\Import;

use App\DTO\Import\DocenteAssignmentRow;
use App\Models\Docente;
use App\Models\ProcesoFecha;
use App\Models\ProcesoDocente;
use App\Models\ExperienciaAdmision;
use App\Models\ExperienciaAdmisionMaestro;
use App\Models\LocalesMaestro;
use App\Models\Locales;
use App\Models\LocalCargo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\Import\Concerns\SharedAssignmentImport;

class DocenteAssignmentImportService
{
    use SharedAssignmentImport;
    /** @var array<string,bool> */
    protected array $seenKeys = [];

    public function parse(array $rawRows, bool $stopOnFirstError = false): Collection
    {
        $results = collect();

        // Cache catálogos para reducir queries
        $catalogoDocentes = Docente::query()->select('doc_vcCodigo','doc_vcDni','doc_vcNombre','doc_vcPaterno','doc_vcMaterno')->get();
        $docentesPorCodigo = $catalogoDocentes->keyBy(fn($d)=> strtoupper($d->doc_vcCodigo));
        $docentesPorDni = $catalogoDocentes->keyBy(fn($d)=> $d->doc_vcDni);

        $catalogoFechas = ProcesoFecha::query()->select('profec_iCodigo','profec_dFecha')->get();
        $fechasPorDate = $catalogoFechas->keyBy(fn($f)=> Carbon::parse($f->profec_dFecha)->format('Y-m-d'));

        // Cargos: separar catálogo de maestros y de instancias por fecha
        $catalogoCargosInst = ExperienciaAdmision::query()
            ->select('expadm_iCodigo','expadmma_iCodigo','profec_iCodigo')
            ->with(['maestro:expadmma_iCodigo,expadmma_vcNombre'])
            ->get();
        $catalogoCargosMaestro = ExperienciaAdmisionMaestro::query()
            ->select('expadmma_iCodigo','expadmma_vcNombre')
            ->get();

        $normKey = function(string $s): string {
            $s = mb_strtoupper($s);
            $s = strtr($s, ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U']);
            $s = preg_replace('/\s+/',' ',trim($s));
            return $s;
        };

        $maestroPorNombre = $catalogoCargosMaestro
            ->mapWithKeys(fn($m)=> [ $normKey($m->expadmma_vcNombre) => $m ]);

        // Índice de instancias existentes: maestroId|procesoFechaId => expadm_iCodigo
        $instanciaCargoIndex = $catalogoCargosInst->mapWithKeys(
            fn($ci)=> [ ($ci->expadmma_iCodigo.'|'.$ci->profec_iCodigo) => $ci->expadm_iCodigo ]
        );

    // Catálogo de locales maestro y mapeo a instancias 'locales' por fecha
    $catalogoLocalesMaestro = LocalesMaestro::query()->select('locma_iCodigo','locma_vcNombre')->get();
    $normLocal = fn(string $s)=> preg_replace('/\s+/',' ',trim(mb_strtoupper(strtr($s,['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U']))));
    $localMaestroMatch = $catalogoLocalesMaestro->mapWithKeys(fn($l)=> [ $normLocal($l->locma_vcNombre) => $l ]);

    // Pre-cargar instancias existentes en 'locales' (locma_iCodigo, profec_iCodigo)
    $instanciasLocales = Locales::query()->select('loc_iCodigo','locma_iCodigo','profec_iCodigo')->get();
    $instanciaIndex = $instanciasLocales->mapWithKeys(fn($r)=> [ $r->locma_iCodigo.'|'.$r->profec_iCodigo => $r->loc_iCodigo ]);

        foreach ($rawRows as $idx => $row) {
            // Mapear cabeceras a claves canónicas (soporta alias y orden variable)
            if (is_array($row)) {
                $row = $this->canonicalizeRow($row);
            }
            $dto = new DocenteAssignmentRow(rowNumber: $idx + 2); // +2 por encabezado fila1
            $dto->codigo = $this->norm($row['codigo'] ?? null);
            $dto->dni = $this->norm($row['dni'] ?? null);
            // Formato oficial usa sólo 'nombres'; si vienen componentes separados se combinan opcionalmente.
            if (!empty($row['paterno']) || !empty($row['materno'])) {
                $parts=[]; if(!empty($row['paterno'])) $parts[] = trim($row['paterno']); if(!empty($row['materno'])) $parts[] = trim($row['materno']); if(!empty($row['nombres'])) $parts[] = trim($row['nombres']);
                $dto->nombres = trim(implode(' ', $parts));
            } else {
                $dto->nombres = trim($row['nombres'] ?? '');
            }
            $dto->cargoNombre = $this->norm($row['cargo'] ?? null);
            $dto->localNombre = $this->norm($row['local'] ?? null);
            $dto->fechaOriginal = $row['fecha'] ?? null;

            // Fecha
            $dto->fechaISO = $this->parseFecha($dto->fechaOriginal);
            if (!$dto->fechaISO) {
                $dto->errors[] = 'Fecha inválida';
            }

            // ProcesoFecha + activo
            if ($dto->fechaISO) {
                $pf = $fechasPorDate[$dto->fechaISO] ?? null;
                if (!$pf) {
                    $dto->errors[] = 'Fecha no corresponde a ProcesoFecha';
                } else {
                    if (property_exists($pf,'profec_iActivo') && (int)$pf->profec_iActivo !== 1) {
                        $dto->errors[] = 'ProcesoFecha no activo';
                    }
                    $dto->procesoFechaId = $pf->profec_iCodigo;
                }
            }
            if ($dto->fechaISO && !$dto->procesoFechaId) {
                // seguridad adicional si por algún flujo anterior no se marcó error
                if (!in_array('Fecha no corresponde a ProcesoFecha', $dto->errors, true)) {
                    $dto->errors[] = 'Fecha no corresponde a ProcesoFecha';
                }
            }

            // Validación DNI formato
            if ($dto->dni) {
                $len = strlen($dto->dni);
                $min = config('import.dni_min_length',8); $max = config('import.dni_max_length',9);
                if (!ctype_digit($dto->dni) || $len < $min || $len > $max) {
                    $dto->errors[] = 'DNI inválido';
                }
            }

            // Docente resolución
            $doc = null;
            if ($dto->codigo && isset($docentesPorCodigo[$dto->codigo])) {
                $doc = $docentesPorCodigo[$dto->codigo];
            } elseif ($dto->dni && isset($docentesPorDni[$dto->dni])) {
                $doc = $docentesPorDni[$dto->dni];
            }
            if (!$doc) {
                $dto->errors[] = 'Docente no encontrado';
            } else {
                // Usamos el código (doc_vcCodigo) como PK lógico para procesos de asignación
                $dto->docentePk = $doc->doc_vcCodigo;
                // Validar nombre parcial (orden: Paterno Materno Nombres)
                $nombreCompleto = trim($doc->doc_vcPaterno.' '.$doc->doc_vcMaterno.' '.$doc->doc_vcNombre);
                $nombreCompletoUp = mb_strtoupper($nombreCompleto);
                if ($dto->nombres && $this->nombresDiscrepan($nombreCompleto, $dto->nombres)) {
                    $dto->warnings[] = 'Nombre no coincide';
                }
            }

            // Cargo (maestro + instancia)
            if ($dto->cargoNombre) {
                $cargoKey = $normKey($dto->cargoNombre);
                $maestroCargo = $maestroPorNombre[$cargoKey] ?? null;
                if (!$maestroCargo) {
                    $dto->errors[] = 'Cargo no existe en ExperienciaAdmisionMaestro';
                } else {
                    // Si ya conocemos procesoFechaId podemos validar instancia específica
                    if ($dto->procesoFechaId) {
                        $idx = $maestroCargo->expadmma_iCodigo.'|'.$dto->procesoFechaId;
                        if (isset($instanciaCargoIndex[$idx])) {
                            $dto->cargoId = $instanciaCargoIndex[$idx];
                        } else {
                            $dto->warnings[] = 'Instancia cargo para fecha será creada';
                        }
                    } else {
                        // Aún sin fecha válida; diferimos la resolución
                        $dto->warnings[] = 'Cargo pendiente de fecha';
                    }
                }
            } else {
                $dto->errors[] = 'Cargo vacío';
            }

            // Local (maestro + instancia) refinado
            if ($dto->localNombre) {
                $locKey = $normLocal($dto->localNombre);
                $localMaestro = $localMaestroMatch[$locKey] ?? null;
                if (!$localMaestro) {
                    $dto->errors[] = 'Local no existe en LocalesMaestro';
                } else {
                    if ($dto->procesoFechaId) {
                        $idx = $localMaestro->locma_iCodigo.'|'.$dto->procesoFechaId;
                        if (isset($instanciaIndex[$idx])) {
                            $dto->localId = $instanciaIndex[$idx];
                        } else {
                            $dto->warnings[] = 'Instancia local para fecha será creada';
                            $dto->localId = $localMaestro->locma_iCodigo; // temporal retención maestro
                        }
                    } else {
                        $dto->warnings[] = 'Local pendiente de fecha';
                        $dto->localId = $localMaestro->locma_iCodigo;
                    }
                }
            } else {
                $dto->errors[] = 'Local vacío';
            }

            // Duplicado interno (codigo+fecha)
            if ($dto->codigo && $dto->fechaISO) {
                $key = $dto->codigo.'|'.$dto->fechaISO;
                if (isset($this->seenKeys[$key])) {
                    $dto->errors[] = 'Duplicado en archivo';
                } else {
                    $this->seenKeys[$key] = true;
                }
            }

            // Asignación activa / pendiente existente
            if ($dto->codigo && $dto->procesoFechaId) {
                $exists = ProcesoDocente::where('profec_iCodigo', $dto->procesoFechaId)
                    ->where('doc_vcCodigo', $dto->codigo)
                    ->where('prodoc_iAsignacion', 1)
                    ->exists();
                if ($exists) {
                    $dto->errors[] = 'Ya asignado en fecha';
                } else {
                    $pending = ProcesoDocente::where('profec_iCodigo', $dto->procesoFechaId)
                        ->where('doc_vcCodigo', $dto->codigo)
                        ->where('prodoc_iAsignacion', 0)
                        ->exists();
                    if ($pending) {
                        $dto->warnings[] = 'Reactivará asignación previa';
                    }
                }
            }

            $dto->valid = empty($dto->errors);
            $results->push($dto);

            if ($stopOnFirstError && !$dto->valid) {
                break;
            }
        }

        return $results;
    }

    public function import(Collection $rows, bool $allowPartial = true, ?string $originalFilename = null): array
    {
        $imported = 0; $skipped = 0; $errors = [];
        $localCargoAdjust = [];// key: loc|cargo => ['increment'=>n, 'new'=>bool]
        DB::transaction(function () use ($rows, $allowPartial, &$imported, &$skipped, &$errors, &$localCargoAdjust) {
            foreach ($rows as $dto) {
                if (!$dto instanceof DocenteAssignmentRow) continue;
                if (!$dto->valid) { $skipped++; if(!$allowPartial){ $errors[]='Fila '.$dto->rowNumber.' inválida'; } continue; }

                // Revalidación defensiva duplicado
                $dup = ProcesoDocente::where('profec_iCodigo', $dto->procesoFechaId)
                    ->where('doc_vcCodigo', $dto->codigo)
                    ->where('prodoc_iAsignacion', 1)
                    ->first();
                if ($dup) { $skipped++; continue; }

                // Guard clause: si no hay procesoFechaId no continuamos (no se puede crear contexto)
                if (!$dto->procesoFechaId) { $skipped++; continue; }

                // Asegurar maestro + instancia locales:
                // Casos:
                //  - localId vacío pero tenemos nombre => crear maestro + instancia
                //  - localId es realmente un locma_iCodigo (no existe en locales) => crear/obtener instancia
                if ($dto->localNombre) {
                    if (!$dto->localId) {
                        // No se resolvió en parse: crear maestro + instancia
                        $maestro = LocalesMaestro::firstOrCreate(['locma_vcNombre' => $dto->localNombre]);
                        $inst = Locales::firstOrCreate([
                            'locma_iCodigo' => $maestro->locma_iCodigo,
                            'profec_iCodigo' => $dto->procesoFechaId,
                        ]);
                        $dto->localId = $inst->loc_iCodigo;
                    } elseif (!Locales::where('loc_iCodigo', $dto->localId)->exists()) {
                        // Es un maestro ID: convertir a instancia
                        $maestroId = $dto->localId;
                        $inst = Locales::firstOrCreate([
                            'locma_iCodigo' => $maestroId,
                            'profec_iCodigo' => $dto->procesoFechaId,
                        ]);
                        $dto->localId = $inst->loc_iCodigo;
                    }
                }

                // Crear Cargo si no existía (maestro + instancia) - requiere procesoFechaId
                if ($dto->procesoFechaId && !$dto->cargoId && $dto->cargoNombre) {
                    $maestro = ExperienciaAdmisionMaestro::firstOrCreate(['expadmma_vcNombre' => $dto->cargoNombre]);
                    $instancia = ExperienciaAdmision::firstOrCreate(
                        [
                            'expadmma_iCodigo' => $maestro->expadmma_iCodigo,
                            'profec_iCodigo' => $dto->procesoFechaId,
                        ],
                        [
                            'expadm_fMonto' => 0,
                        ]
                    );
                    $dto->cargoId = $instancia->expadm_iCodigo;
                }

                // Asegurar LocalCargo y actualizar ocupados
                if ($dto->localId && $dto->cargoId) {
                    $keyLC = $dto->localId.'|'.$dto->cargoId;
                    $localCargo = LocalCargo::where('loc_iCodigo', $dto->localId)
                        ->where('expadm_iCodigo', $dto->cargoId)
                        ->lockForUpdate()
                        ->first();
                    if (!$localCargo) {
                       // $vac = config('import.default_local_cargo_vacantes', 9999);
                        $localCargo = LocalCargo::create([
                            'loc_iCodigo' => $dto->localId,
                            'expadm_iCodigo' => $dto->cargoId,
                            'loccar_iVacante' => 0, // se ajustará luego
                            'loccar_iOcupado' => 0,
                        ]);
                        $localCargoAdjust[$keyLC] = ['increment'=>1,'model'=>$localCargo,'new'=>true];
                    } else {
                        // Cupo se evaluará tras consolidar increments
                        if (!isset($localCargoAdjust[$keyLC])) $localCargoAdjust[$keyLC] = ['increment'=>0,'model'=>$localCargo,'new'=>false];
                        $localCargoAdjust[$keyLC]['increment']++;
                    }
                }

                // Reactivación: si existe registro inactivo (prodoc_iAsignacion=0) se actualiza en vez de crear
                $pending = ProcesoDocente::where('profec_iCodigo', $dto->procesoFechaId)
                    ->where('doc_vcCodigo', $dto->codigo)
                    ->where('prodoc_iAsignacion', 0)
                    ->lockForUpdate()
                    ->first();
                if ($pending) {
                    $pending->expadm_iCodigo = $dto->cargoId;
                    $pending->loc_iCodigo = $dto->localId;
                    $pending->prodoc_iAsignacion = 1;
                    $pending->prodoc_dtFechaAsignacion = now();
                    
                    $pending->user_id = auth()->id();
                    $pending->save();
                    $imported++;
                } else {
                    ProcesoDocente::create([
                        'profec_iCodigo' => $dto->procesoFechaId,
                        'doc_vcCodigo' => $dto->codigo,
                        'expadm_iCodigo' => $dto->cargoId,
                        'loc_iCodigo' => $dto->localId,
                        'prodoc_iAsignacion' => 1,
                        'prodoc_dtFechaAsignacion' => now(),
                        'user_id' => auth()->id(),
                    ]);
                    $imported++;
                }
            }

            // Nuevo flujo: primero actualizar Ocupado, luego expandir Vacante si queda corta
            foreach ($localCargoAdjust as $k => $info) {
                /** @var LocalCargo $lc */
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
        // Auditoría simple (si existe tabla import_job_logs luego se insertará via modelo)
        if (class_exists(\App\Models\ImportJobLog::class)) {
            \App\Models\ImportJobLog::create([
                'user_id' => auth()->id(),
                'filename_original' => $originalFilename,
                'total_filas' => $rows->count(),
                'importadas' => $imported,
                'omitidas' => $skipped,
                'errores' => $errors,
            ]);
        }
        return compact('imported','skipped','errors');
    }

    public function generateErrorReport(Collection $rows): array
    {
        $out = [];
        foreach ($rows as $dto) {
            /** @var DocenteAssignmentRow $dto */
            if ($dto->valid) continue;
            $out[] = [
                'fila' => $dto->rowNumber,
                'codigo' => $dto->codigo,
                'dni' => $dto->dni,
                'cargo' => $dto->cargoNombre,
                'local' => $dto->localNombre,
                'fecha' => $dto->fechaISO,
                'errores' => implode('|', $dto->errors),
                'warnings' => implode('|', $dto->warnings),
            ];
        }
        return $out;
    }

    // parseFecha y norm ahora provienen de SharedAssignmentImport trait
}
