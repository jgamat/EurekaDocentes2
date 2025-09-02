<?php

namespace App\Http\Controllers;

use App\Models\Planilla;
use App\Models\Proceso;
use App\Models\ProcesoFecha;
use App\Models\Tipo;
use Illuminate\Support\Facades\DB;

class PlanillaPrintController extends Controller
{
    public function reimprimir(int $plaId)
    {
        $pla = Planilla::find($plaId);
        if (!$pla) {
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
        ->selectRaw("pa.plaadm_iOrden as orden, pra.proadm_iCodigo as cred_numero, a.adm_vcCodigo as codigo, a.adm_vcDni as dni, a.adm_vcNombres as nombres, lm.locma_vcNombre as local_nombre, em.expadmma_vcNombre as cargo_nombre, COALESCE(ea.expadm_fMonto,0) as monto");
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

        // Group rows by cargo preserving original order
        $groups = [];
        foreach ($rows as $r) {
            $cargo = $r->cargo_nombre;
            if (!array_key_exists($cargo, $groups)) {
                $groups[$cargo] = [];
            }
            $groups[$cargo][] = $r;
        }

        // Build detail pages per cargo (13 rows per page)
    foreach ($groups as $cargoNombre => $items) {
        $montoCargo = isset($items[0]) ? (float) ($items[0]->monto ?? 0) : 0;
        $chunks = collect($items)->chunk(13);
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
                // numeración por cargo (no global)
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

        // Append summary page for Docentes (not Tercero/CAS) only
        if (!$isTerceroCas && !$isAlumno) {
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

        $data = [
            'numero_planilla' => (int) $pla->pla_iNumero,
            'proceso_nombre' => $proceso?->pro_vcNombre,
            'fecha_proceso' => optional($fecha)->profec_dFecha,
            'impresion_fecha' => now(),
            'titulo_planilla' => $tituloPlanilla,
            'pages' => $pages,
            'total_pages' => count($pages),
            'es_tercero_cas' => $isTerceroCas,
            'es_alumno' => $isAlumno,
        ];

        // Para reimpresión, forzar uso de Blade compilado y fondos si existen
        $detailBgUrl = $this->findTemplateImageUrl('docentes');
        $summaryBgUrl = $this->findTemplateImageUrl('docentes_resumen') ?? null;
        $data['bg_detail_url'] = $detailBgUrl;
        $data['bg_summary_url'] = $summaryBgUrl;
        $pdf = \PDF::loadView('pdf.planilla_docentes_compilado', $data)->setPaper('a4', 'landscape');
    $content = $pdf->output();
        $downloadName = 'reimpresion_planilla_'.$pla->pla_iNumero.'_'.now()->format('Ymd_His').'.pdf';

        return response()->streamDownload(function () use ($content) { echo $content; }, $downloadName, [
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
        $exts = ['png','jpg','jpeg'];
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

    // No se requiere buscar templates PDF en reimpresión (se usa la vista Blade)
}
