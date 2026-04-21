<?php

namespace App\Http\Controllers;

use App\Models\Planilla;
use App\Models\Proceso;
use App\Models\ProcesoFecha;
use App\Models\Tipo;
use App\Services\PlanillaPdfGenerator;
use Illuminate\Support\Facades\DB;

class PlanillaPrintController extends Controller
{
    public function reimprimir(int $plaId)
    {
        $pla = Planilla::find($plaId);
        if (! $pla) {
            abort(404, 'Planilla no encontrada');
        }

        $tipo = Tipo::find($pla->tipo_iCodigo);
        $tipoNombreLower = strtolower($tipo?->tipo_vcNombre ?? '');
        $fechaId = (int) $pla->profec_iCodigo;

        if (str_contains($tipoNombreLower, 'docente')) {
            $q = DB::table('planillaDocente as pd')
                ->join('docente as d', 'd.doc_vcCodigo', '=', 'pd.doc_vcCodigo')
                ->join('procesodocente as prd', function ($j) use ($fechaId) {
                    $j->on('prd.doc_vcCodigo', '=', 'd.doc_vcCodigo')
                        ->where('prd.profec_iCodigo', '=', $fechaId);
                })
                ->join('locales as l', 'l.loc_iCodigo', '=', 'prd.loc_iCodigo')
                ->join('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
                ->join('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'prd.expadm_iCodigo')
                ->join('experienciaadmisionMaestro as em', 'em.expadmma_iCodigo', '=', 'ea.expadmma_iCodigo')
                ->where('pd.pla_id', $plaId)
                ->orderBy('pd.pladoc_iOrden')
                ->selectRaw("pd.pladoc_iOrden as orden, prd.prodoc_iCodigo as cred_numero, d.doc_vcCodigo as codigo, d.doc_vcDni as dni, CONCAT(d.doc_vcPaterno, ' ', d.doc_vcMaterno, ' ', d.doc_vcNombre) as nombres, lm.locma_vcNombre as local_nombre, em.expadmma_vcNombre as cargo_nombre, COALESCE(ea.expadm_fMonto,0) as monto");
        } elseif (str_contains($tipoNombreLower, 'admin') || str_contains($tipoNombreLower, 'tercero') || str_contains($tipoNombreLower, 'cas')) {
            $q = DB::table('planillaAdministrativo as pa')
                ->join('administrativo as a', 'a.adm_vcDni', '=', 'pa.adm_vcDni')
                ->join('procesoadministrativo as pra', function ($j) use ($fechaId) {
                    $j->on('pra.adm_vcDni', '=', 'a.adm_vcDni')
                        ->where('pra.profec_iCodigo', '=', $fechaId);
                })
                ->join('locales as l', 'l.loc_iCodigo', '=', 'pra.loc_iCodigo')
                ->join('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
                ->join('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'pra.expadm_iCodigo')
                ->join('experienciaadmisionMaestro as em', 'em.expadmma_iCodigo', '=', 'ea.expadmma_iCodigo')
                ->where('pa.pla_id', $plaId)
                ->orderBy('pa.plaadm_iOrden')
                ->selectRaw('pa.plaadm_iOrden as orden, pra.proadm_iCodigo as cred_numero, a.adm_vcCodigo as codigo, a.adm_vcDni as dni, a.adm_vcNombres as nombres, lm.locma_vcNombre as local_nombre, em.expadmma_vcNombre as cargo_nombre, COALESCE(ea.expadm_fMonto,0) as monto');
        } else {
            $q = DB::table('planillaAlumno as pl')
                ->join('alumno as al', 'al.alu_vcCodigo', '=', 'pl.alu_vcCodigo')
                ->join('procesoalumno as pral', function ($j) use ($fechaId) {
                    $j->on('pral.alu_vcCodigo', '=', 'al.alu_vcCodigo')
                        ->where('pral.profec_iCodigo', '=', $fechaId);
                })
                ->join('locales as l', 'l.loc_iCodigo', '=', 'pral.loc_iCodigo')
                ->join('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
                ->join('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'pral.expadm_iCodigo')
                ->join('experienciaadmisionMaestro as em', 'em.expadmma_iCodigo', '=', 'ea.expadmma_iCodigo')
                ->where('pl.pla_id', $plaId)
                ->orderBy('pl.plaalu_iOrden')
                ->selectRaw("pl.plaalu_iOrden as orden, pral.proalu_iCodigo as cred_numero, al.alu_vcCodigo as codigo, al.alu_vcDni as dni, CONCAT(al.alu_vcPaterno, ' ', al.alu_vcMaterno, ' ', al.alu_vcNombre) as nombres, lm.locma_vcNombre as local_nombre, em.expadmma_vcNombre as cargo_nombre, COALESCE(ea.expadm_fMonto,0) as monto");
        }

        $rows = $q->get();
        if ($rows->isEmpty()) {
            abort(404, 'No hay registros en la planilla');
        }

        $pageNo = (int) $pla->pla_iPaginaInicio;
        $pages = [];
        $localNombre = optional($rows->first())->local_nombre;

        // Detect flags early for summary logic
        $isTerceroCas = str_contains($tipoNombreLower, 'tercero') || str_contains($tipoNombreLower, 'cas');
        $isAlumno = str_contains($tipoNombreLower, 'alumno');
        $isAdministrativo = (str_contains($tipoNombreLower, 'admin') && ! $isTerceroCas && ! $isAlumno);

        // Agrupación por cargo para resumen (siempre) pero detalle depende del tipo
        $groups = [];
        foreach ($rows as $r) {
            $cargo = $r->cargo_nombre;
            $groups[$cargo][] = $r;
        }

        if ($isTerceroCas || $isAlumno) {
            // Tercero/CAS y Alumnos: por local, orden por nombres, sin página de resumen.
            $rowsPerPage = (int) config('planillas.rows_per_page_default', 15);
            $ordenLocal = 1;
            $chunks = $rows->sortBy('nombres')->values()->chunk($rowsPerPage);

            foreach ($chunks as $chunk) {
                $pages[] = [
                    'type' => 'detail',
                    'local_id' => null,
                    'local_nombre' => $localNombre,
                    'cargo_id' => null,
                    'cargo_nombre' => null,
                    'monto_cargo' => 0,
                    'planilla_numero' => (int) $pla->pla_iNumero,
                    'page_no' => $pageNo++,
                    'rows' => $chunk->map(function ($r) use (&$ordenLocal) {
                        return [
                            'orden' => $ordenLocal++,
                            'codigo' => $r->codigo,
                            'dni' => $r->dni,
                            'nombres' => $r->nombres,
                            'local_nombre' => $r->local_nombre,
                            'cargo_nombre' => $r->cargo_nombre,
                            'monto' => (float) $r->monto,
                            'cred_numero' => $r->cred_numero,
                        ];
                    })->toArray(),
                ];
            }
        } elseif ($isAdministrativo) {
            // NUEVA LÓGICA: páginas por local (lista global), permitiendo múltiples cargos en la misma página.
            // Se usa numeración global continua.
            $ordenGlobal = 1;
            $rowChunks = $rows->chunk(15); // mismo tamaño que docentes
            foreach ($rowChunks as $chunk) {
                $pages[] = [
                    'type' => 'detail',
                    'local_id' => null,
                    'local_nombre' => $localNombre,
                    'cargo_id' => null,
                    'cargo_nombre' => null, // no se muestra en cabecera para administrativos
                    'monto_cargo' => 0,
                    'planilla_numero' => (int) $pla->pla_iNumero,
                    'page_no' => $pageNo++,
                    'rows' => $chunk->map(function ($r) use (&$ordenGlobal) {
                        return [
                            'orden' => $ordenGlobal++,
                            'codigo' => $r->codigo,
                            'dni' => $r->dni,
                            'nombres' => $r->nombres,
                            'local_nombre' => $r->local_nombre,
                            'cargo_nombre' => $r->cargo_nombre,
                            'monto' => (float) $r->monto,
                            'cred_numero' => $r->cred_numero,
                        ];
                    })->toArray(),
                ];
            }
            // Marcar la última página de detalle para insertar línea de "Monto por local"
            if (! empty($pages)) {
                $totalLocal = $rows->sum(fn ($r) => (float) $r->monto);
                $pages[array_key_last($pages)]['is_last_detail'] = true;
                $pages[array_key_last($pages)]['total_local'] = $totalLocal;
            }
        } else {
            // LÓGICA EXISTENTE: páginas por cargo
            foreach ($groups as $cargoNombre => $items) {
                $montoCargo = isset($items[0]) ? (float) ($items[0]->monto ?? 0) : 0;
                $chunks = collect($items)->chunk(15);
                $ordenDentroCargo = 1; // reinicia numeración por cargo
                foreach ($chunks as $chunk) {
                    $pages[] = [
                        'type' => 'detail',
                        'local_id' => null,
                        'local_nombre' => $localNombre,
                        'cargo_id' => null,
                        'cargo_nombre' => $cargoNombre,
                        'monto_cargo' => $montoCargo,
                        'planilla_numero' => (int) $pla->pla_iNumero,
                        'page_no' => $pageNo++,
                        'rows' => $chunk->map(function ($r) use (&$ordenDentroCargo) {
                            return [
                                'orden' => $ordenDentroCargo++,
                                'codigo' => $r->codigo,
                                'dni' => $r->dni,
                                'nombres' => $r->nombres,
                                'local_nombre' => $r->local_nombre,
                                'cargo_nombre' => $r->cargo_nombre,
                                'monto' => (float) $r->monto,
                                'cred_numero' => $r->cred_numero,
                            ];
                        })->toArray(),
                    ];
                }
            }
        }

        // Append summary page para docentes y administrativos estándar (no tercero/cas ni alumnos)
        if (! $isTerceroCas && ! $isAlumno) {
            $resumen = [];
            $granTotal = 0.0;
            foreach ($groups as $cargoNombre => $items) {
                $cantidad = count($items);
                $monto = isset($items[0]) ? (float) ($items[0]->monto ?? 0) : 0;
                $subtotal = $cantidad * $monto;
                $granTotal += $subtotal;
                $resumen[] = [
                    'cargo_nombre' => $cargoNombre,
                    'cantidad' => $cantidad,
                    // Placeholder: si más adelante hay conteo real, reemplazar estos campos
                    'asistentes' => null,
                    'inasistentes' => null,
                    'monto' => $monto,
                    'subtotal' => $subtotal,
                ];
            }

            $pages[] = [
                'type' => 'summary',
                'local_id' => null,
                'local_nombre' => $localNombre,
                'planilla_numero' => (int) $pla->pla_iNumero,
                'page_no' => $pageNo++,
                'resumen' => $resumen,
                'gran_total' => $granTotal,
            ];
        }

        $proceso = Proceso::find($pla->pro_iCodigo);
        $fecha = ProcesoFecha::find($pla->profec_iCodigo);
        $tituloPlanilla = $tipo?->tipo_vcNombrePlanilla ?? 'PLANILLA';

        // Flags already computed above
        $isDocente = str_contains($tipoNombreLower, 'docente');

        $data = [
            'numero_planilla' => (int) $pla->pla_iNumero,
            'proceso_nombre' => $proceso?->pro_vcNombre,
            'fecha_proceso' => optional($fecha)->profec_dFecha,
            'impresion_fecha' => now(),
            'titulo_planilla' => $tituloPlanilla,
            'pages' => $pages,
            'total_pages' => count($pages),
            'es_docente' => $isDocente,
            'es_tercero_cas' => $isTerceroCas,
            'es_alumno' => $isAlumno,
            'es_admin' => $isAdministrativo,
            'profec_vcFimaDirector' => $fecha?->profec_vcFimaDirector,
            'profec_vcFimaJefe' => $fecha?->profec_vcFimaJefe,
        ];

        $tplDirA = public_path('storage/templates_planilla');
        $tplDirB = public_path('storage/templates_planillas');
        $tplDetalle = $this->findTemplatePdf('docentes', [$tplDirA, $tplDirB]);
        $tplResumen = $this->findTemplatePdf('resumen_doc', [$tplDirA, $tplDirB]);

        // Igualar comportamiento de impresión original: FPDI para docentes/administrativos estándar; Blade para terceros/cas/alumnos.
        if (($isDocente || $isAdministrativo) && ! $isTerceroCas && ! $isAlumno && ($tplDetalle || $tplResumen)) {
            $header = [
                'numero_planilla' => null,
                'proceso_nombre' => $proceso?->pro_vcNombre,
                'fecha_proceso' => optional($fecha)->profec_dFecha,
                'impresion_fecha' => now()->toDateTimeString(),
                'titulo_planilla' => $tituloPlanilla,
                'profec_vcFimaDirector' => $fecha?->profec_vcFimaDirector,
                'profec_vcFimaJefe' => $fecha?->profec_vcFimaJefe,
            ];

            $generator = new PlanillaPdfGenerator;
            $content = $generator->buildDocentesPdf($pages, $header, $tplDetalle, $tplResumen);
        } else {
            $detailBgUrl = $this->findTemplateImageUrl('docentes');
            $summaryBgUrl = $this->findTemplateImageUrl('resumen_doc');
            $data['bg_detail_url'] = $detailBgUrl;
            $data['bg_summary_url'] = $summaryBgUrl;
            $pdf = \PDF::loadView('pdf.planilla_docentes_compilado', $data)->setPaper('a4', 'landscape');
            $content = $pdf->output();
        }

        $downloadName = 'reimpresion_planilla_'.$pla->pla_iNumero.'_'.now()->format('Ymd_His').'.pdf';

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $downloadName, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function findTemplateImageUrl(string $baseName): ?string
    {
        $dirs = [
            public_path('storage/templates_planilla'),
            public_path('storage/templates_planillas'),
        ];
        $exts = ['png', 'jpg', 'jpeg'];
        foreach ($dirs as $dir) {
            foreach ($exts as $ext) {
                $path = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$baseName.'.'.$ext;
                if (is_file($path)) {
                    $rel = str_replace(public_path(), '', $path);

                    return asset(ltrim($rel, '/\\'));
                }
            }
        }

        return null;
    }

    private function findTemplatePdf(string $baseName, array $dirs): ?string
    {
        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $pattern = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$baseName.'*.pdf';
            $matches = glob($pattern);

            if (! empty($matches)) {
                sort($matches);

                return $matches[0];
            }
        }

        return null;
    }
}
