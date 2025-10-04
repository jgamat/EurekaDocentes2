<?php

namespace App\Services\Import;

use App\DTO\Import\AdministrativoAssignmentRow;
use Illuminate\Support\Collection;
use Carbon\Carbon;

// Reutilizamos muchos de los mismos modelos usados para docentes suponiendo que
// la estructura de cargos/locales y procesoFecha aplica también a administrativos.
use App\Models\ProcesoFecha;
use App\Models\ExperienciaAdmision;
use App\Models\ExperienciaAdmisionMaestro;
use App\Models\LocalesMaestro;
use App\Models\Locales;
use App\Models\LocalCargo; // Si la relación cargo-local es compartida
use Illuminate\Support\Facades\DB;

// Modelos específicos administrativos (ajustar según existencia real)
use App\Models\Administrativo;
use App\Models\ProcesoAdministrativo; // Suponiendo tabla similar a ProcesoDocente
use App\Services\Import\Concerns\SharedAssignmentImport;

class AdministrativoAssignmentImportService
{
    use SharedAssignmentImport;
    /** @var array<string,bool> */
    protected array $seenKeys = [];

    public function parse(array $rawRows, bool $stopOnFirstError = false): Collection
    {
        $results = collect();

    // Nota: La tabla 'administrativo' sólo expone 'adm_vcNombres' (nombre completo) según reporte.
    // Anteriormente se intentaba seleccionar columnas separadas (adm_vcNombre, adm_vcPaterno, adm_vcMaterno) que NO existen.
    // Adaptamos la lógica para trabajar únicamente con el nombre completo.
    $catalogoAdministrativos = Administrativo::query()->select('adm_vcCodigo','adm_vcDni','adm_vcNombres')->get();
        $porCodigo = $catalogoAdministrativos->keyBy(fn($a)=> strtoupper($a->adm_vcCodigo));
        $porDni = $catalogoAdministrativos->keyBy(fn($a)=> $a->adm_vcDni);

        $catalogoFechas = ProcesoFecha::query()->select('profec_iCodigo','profec_dFecha','profec_iActivo')->get();
        $fechasPorDate = $catalogoFechas->keyBy(fn($f)=> Carbon::parse($f->profec_dFecha)->format('Y-m-d'));

        $catalogoCargosInst = ExperienciaAdmision::query()
            ->select('expadm_iCodigo','expadmma_iCodigo','profec_iCodigo')
            ->with(['maestro:expadmma_iCodigo,expadmma_vcNombre'])
            ->get();
        $catalogoCargosMaestro = ExperienciaAdmisionMaestro::query()->select('expadmma_iCodigo','expadmma_vcNombre')->get();

        $normKey = function(string $s): string {
            $s = mb_strtoupper($s);
            $s = strtr($s, ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U']);
            $s = preg_replace('/\s+/',' ',trim($s));
            return $s;
        };

        $maestroPorNombre = $catalogoCargosMaestro
            ->mapWithKeys(fn($m)=> [ $normKey($m->expadmma_vcNombre) => $m ]);

        $catalogoLocalesMaestro = LocalesMaestro::query()->select('locma_iCodigo','locma_vcNombre')->get();
        $normLocal = fn(string $s)=> preg_replace('/\s+/',' ',trim(mb_strtoupper(strtr($s,['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U']))));
        $localMaestroMatch = $catalogoLocalesMaestro->mapWithKeys(fn($l)=> [ $normLocal($l->locma_vcNombre) => $l ]);

        $instanciasLocales = Locales::query()->select('loc_iCodigo','locma_iCodigo','profec_iCodigo')->get();
        $instanciaIndex = $instanciasLocales->mapWithKeys(fn($r)=> [ $r->locma_iCodigo.'|'.$r->profec_iCodigo => $r->loc_iCodigo ]);

        // Índice instancias cargo
        $instanciaCargoIndex = $catalogoCargosInst->mapWithKeys(
            fn($ci)=> [ ($ci->expadmma_iCodigo.'|'.$ci->profec_iCodigo) => $ci->expadm_iCodigo ]
        );

        foreach ($rawRows as $idx => $row) {
            if (is_array($row)) { $row = $this->canonicalizeRow($row); }
            $dto = new AdministrativoAssignmentRow(rowNumber: $idx + 2);
            $dto->codigo = $this->norm($row['codigo'] ?? null); // opcional
            $dto->dni = $this->norm($row['dni'] ?? null); // obligatorio
            // Formato oficial sólo provee 'nombres'; si llegan apellidos separados se combinan.
            if (!empty($row['paterno']) || !empty($row['materno'])) {
                $parts=[]; if(!empty($row['paterno'])) $parts[] = trim($row['paterno']); if(!empty($row['materno'])) $parts[] = trim($row['materno']); if(!empty($row['nombres'])) $parts[] = trim($row['nombres']);
                $dto->nombres = trim(implode(' ', $parts));
            } else {
                $dto->nombres = trim($row['nombres'] ?? '');
            }
            $dto->cargoNombre = $this->norm($row['cargo'] ?? null);
            $dto->localNombre = $this->norm($row['local'] ?? null);
            $dto->fechaOriginal = $row['fecha'] ?? null;

            $dto->fechaISO = $this->parseFecha($dto->fechaOriginal);
            if (!$dto->fechaISO) { $dto->errors[] = 'Fecha inválida'; }
            if ($dto->fechaISO) {
                $pf = $fechasPorDate[$dto->fechaISO] ?? null;
                if (!$pf) { $dto->errors[] = 'Fecha no corresponde a ProcesoFecha'; }
                else {
                    if (property_exists($pf,'profec_iActivo') && (int)$pf->profec_iActivo !== 1) {
                        $dto->errors[] = 'ProcesoFecha no activo';
                    }
                    $dto->procesoFechaId = $pf->profec_iCodigo;
                }
            }
            if ($dto->fechaISO && !$dto->procesoFechaId && !in_array('Fecha no corresponde a ProcesoFecha', $dto->errors, true)) {
                $dto->errors[] = 'Fecha no corresponde a ProcesoFecha';
            }

            if ($dto->dni) {
                $len = strlen($dto->dni); $min = config('import.dni_min_length',8); $max = config('import.dni_max_length',9);
                if (!ctype_digit($dto->dni) || $len < $min || $len > $max) { $dto->errors[] = 'DNI inválido'; }
            }

            // DNI es obligatorio para identificar el administrativo.
            $adm = null;
            if ($dto->dni && isset($porDni[$dto->dni])) { $adm = $porDni[$dto->dni]; }
            elseif ($dto->codigo && isset($porCodigo[$dto->codigo])) { $adm = $porCodigo[$dto->codigo]; }

            if (!$dto->dni) {
                $dto->errors[] = 'DNI obligatorio';
            }

            if (!$adm && $dto->dni) { $dto->errors[] = 'Administrativo no encontrado'; }
            elseif ($adm) {
                $dto->administrativoPk = $adm->adm_vcCodigo ?? $adm->adm_vcDni; // fallback
                $nombreCompleto = trim($adm->adm_vcNombres);
                $nombreCompletoUp = mb_strtoupper($nombreCompleto);
                if ($dto->nombres && $this->nombresDiscrepan($nombreCompleto, $dto->nombres)) {
                    $dto->warnings[] = 'Nombre no coincide';
                }
                if ($dto->codigo && isset($porCodigo[$dto->codigo]) && $adm !== $porCodigo[$dto->codigo]) {
                    $dto->warnings[] = 'Código no corresponde al DNI';
                }
            }

            if ($dto->cargoNombre) {
                $cargoKey = $normKey($dto->cargoNombre);
                $maestroCargo = $maestroPorNombre[$cargoKey] ?? null;
                if (!$maestroCargo) { $dto->errors[] = 'Cargo no existe en ExperienciaAdmisionMaestro'; }
                else {
                    if ($dto->procesoFechaId) {
                        $idxKey = $maestroCargo->expadmma_iCodigo.'|'.$dto->procesoFechaId;
                        if (isset($instanciaCargoIndex[$idxKey])) { $dto->cargoId = $instanciaCargoIndex[$idxKey]; }
                        else { $dto->warnings[] = 'Instancia cargo para fecha será creada'; }
                    } else { $dto->warnings[] = 'Cargo pendiente de fecha'; }
                }
            } else { $dto->errors[] = 'Cargo vacío'; }

            if ($dto->localNombre) {
                $locKey = $normLocal($dto->localNombre);
                $localMaestro = $localMaestroMatch[$locKey] ?? null;
                if (!$localMaestro) { $dto->errors[] = 'Local no existe en LocalesMaestro'; }
                else {
                    if ($dto->procesoFechaId) {
                        $idxL = $localMaestro->locma_iCodigo.'|'.$dto->procesoFechaId;
                        if (isset($instanciaIndex[$idxL])) { $dto->localId = $instanciaIndex[$idxL]; }
                        else { $dto->warnings[] = 'Instancia local para fecha será creada'; $dto->localId = $localMaestro->locma_iCodigo; }
                    } else { $dto->warnings[] = 'Local pendiente de fecha'; $dto->localId = $localMaestro->locma_iCodigo; }
                }
            } else { $dto->errors[] = 'Local vacío'; }

            // Duplicados en el archivo se controlan por DNI+fecha (código es opcional)
            if ($dto->dni && $dto->fechaISO) {
                $key = $dto->dni.'|'.$dto->fechaISO;
                if (isset($this->seenKeys[$key])) { $dto->errors[] = 'Duplicado en archivo'; }
                else { $this->seenKeys[$key] = true; }
            }

            // Verificación de asignación previa por DNI
            if ($dto->dni && $dto->procesoFechaId) {
                $exists = ProcesoAdministrativo::where('profec_iCodigo', $dto->procesoFechaId)
                    ->where('adm_vcDni', $dto->dni)
                    ->where('proadm_iAsignacion', 1)
                    ->exists();
                if ($exists) { $dto->errors[] = 'Ya asignado en fecha'; }
                else {
                    // Detectar si hay un registro pendiente para informar que se reactivará
                    $pendingExists = ProcesoAdministrativo::where('profec_iCodigo', $dto->procesoFechaId)
                        ->where('adm_vcDni', $dto->dni)
                        ->where('proadm_iAsignacion', 0)
                        ->exists();
                    if ($pendingExists) { $dto->warnings[] = 'Reactivará asignación previa'; }
                }
            }

            $dto->valid = empty($dto->errors);
            $results->push($dto);
            if ($stopOnFirstError && !$dto->valid) { break; }
        }

        return $results;
    }

    public function import(Collection $rows, bool $allowPartial = true, ?string $originalFilename = null): array
    {
        $imported = 0; $skipped = 0; $errors = []; $localCargoAdjust = [];
        DB::transaction(function () use ($rows, $allowPartial, &$imported, &$skipped, &$errors, &$localCargoAdjust) {
            foreach ($rows as $dto) {
                if (!$dto instanceof AdministrativoAssignmentRow) continue;
                if (!$dto->valid) { $skipped++; if(!$allowPartial){ $errors[]='Fila '.$dto->rowNumber.' inválida'; } continue; }
                if (!$dto->procesoFechaId) { $skipped++; continue; }

                // Si ya existe activo, saltar (ya controlado en parse, doble control defensivo)
                $dup = ProcesoAdministrativo::where('profec_iCodigo', $dto->procesoFechaId)
                    ->where('adm_vcDni', $dto->dni)
                    ->where('proadm_iAsignacion', 1)
                    ->first();
                if ($dup) { $skipped++; continue; }

                // Reutilizar registro pendiente (proadm_iAsignacion = 0) si existe para evitar violar PK compuesta (dni+fecha)
                $pendiente = ProcesoAdministrativo::where('profec_iCodigo', $dto->procesoFechaId)
                    ->where('adm_vcDni', $dto->dni)
                    ->where('proadm_iAsignacion', 0)
                    ->lockForUpdate()
                    ->first();

                if ($dto->localNombre) {
                    if (!$dto->localId) {
                        $maestro = LocalesMaestro::firstOrCreate(['locma_vcNombre' => $dto->localNombre]);
                        $inst = Locales::firstOrCreate([
                            'locma_iCodigo' => $maestro->locma_iCodigo,
                            'profec_iCodigo' => $dto->procesoFechaId,
                        ]);
                        $dto->localId = $inst->loc_iCodigo;
                    } elseif (!Locales::where('loc_iCodigo', $dto->localId)->exists()) {
                        $maestroId = $dto->localId;
                        $inst = Locales::firstOrCreate([
                            'locma_iCodigo' => $maestroId,
                            'profec_iCodigo' => $dto->procesoFechaId,
                        ]);
                        $dto->localId = $inst->loc_iCodigo;
                    }
                }

                if ($dto->procesoFechaId && !$dto->cargoId && $dto->cargoNombre) {
                    $maestro = ExperienciaAdmisionMaestro::firstOrCreate(['expadmma_vcNombre' => $dto->cargoNombre]);
                    $instancia = ExperienciaAdmision::firstOrCreate([
                        'expadmma_iCodigo' => $maestro->expadmma_iCodigo,
                        'profec_iCodigo' => $dto->procesoFechaId,
                    ], [ 'expadm_fMonto' => 0 ]);
                    $dto->cargoId = $instancia->expadm_iCodigo;
                }

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
                        $localCargoAdjust[$keyLC] = ['increment'=>1,'model'=>$localCargo,'new'=>true];
                    } else {
                        if (!isset($localCargoAdjust[$keyLC])) $localCargoAdjust[$keyLC] = ['increment'=>0,'model'=>$localCargo,'new'=>false];
                        $localCargoAdjust[$keyLC]['increment']++;
                    }
                }

                if ($pendiente) {
                    // Actualizar el registro existente para reactivarlo
                    $pendiente->update([
                        'expadm_iCodigo' => $dto->cargoId,
                        'loc_iCodigo' => $dto->localId,
                        'proadm_iAsignacion' => 1,
                        'proadm_dtFechaAsignacion' => now(),
                        'user_id' => auth()->id(),
                    ]);
                    $imported++;
                    // Contabilizar incremento de ocupados igual que una nueva asignación
                } else {
                    ProcesoAdministrativo::create([
                        'profec_iCodigo' => $dto->procesoFechaId,
                        'adm_vcDni' => $dto->dni,
                        'expadm_iCodigo' => $dto->cargoId,
                        'loc_iCodigo' => $dto->localId,
                        'proadm_iAsignacion' => 1,
                        'proadm_dtFechaAsignacion' => now(),
                        'user_id' => auth()->id(),
                    ]);
                    $imported++;
                }
            }

            // Nuevo flujo: primero establecer/actualizar Ocupado, luego equilibrar Vacante si queda por debajo.
            foreach ($localCargoAdjust as $info) {
                $lc = $info['model'];
                $increment = (int)($info['increment'] ?? 0);
                if ($info['new']) {
                    // Recurso recién creado: ambos iguales al total asignado detectado
                    $lc->loccar_iOcupado = $increment;
                    if ($lc->loccar_iVacante < $lc->loccar_iOcupado) {
                        $lc->loccar_iVacante = $lc->loccar_iOcupado; // igualar inicialmente
                    }
                    $lc->save();
                } else {
                    if ($increment > 0) {
                        // Actualizar ocupados sumando el incremento
                        $lc->loccar_iOcupado += $increment;
                    }
                    // Balance: si vacante - ocupado < 0, ampliar vacante por el excedente
                    $diff = $lc->loccar_iVacante - $lc->loccar_iOcupado;
                    if ($diff < 0) {
                        $lc->loccar_iVacante += abs($diff); // ampliar sólo lo necesario
                    }
                    $lc->save();
                }
            }
        });

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

    // parseFecha y norm ahora provienen del trait SharedAssignmentImport
}
