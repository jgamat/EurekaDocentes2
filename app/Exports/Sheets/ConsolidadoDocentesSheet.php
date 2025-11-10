<?php

namespace App\Exports\Sheets;

use App\Models\ProcesoDocente;
use Illuminate\Support\Facades\DB;

class ConsolidadoDocentesSheet extends BaseConsolidadoSheet
{
    protected function titleFull(): string
    {
        return 'REPORTE DE CONSOLIDADO DE PLANILLAS DE DOCENTES';
    }

    public function title(): string
    {
        return 'Docentes';
    }

    protected function queryRows(): array
    {
        $query = ProcesoDocente::query()
            ->with(['docente.tipo','experienciaAdmision.maestro','local.localesMaestro','procesoFecha'])
            // Join a la tabla docente para poder ordenar por sus apellidos/nombres
            ->leftJoin('docente', 'docente.doc_vcCodigo', '=', 'procesodocente.doc_vcCodigo')
            // Ajuste: profec_iCodigo pertenece a planilla, no a planillaDocente; filtramos sólo en la tabla planilla
                        ->leftJoin('planillaDocente as pld', function($j){
                                $j->on('pld.doc_vcCodigo','=','procesodocente.doc_vcCodigo')
                                    // Limitar sólo a pivotes cuya planilla pertenezca a la fecha seleccionada para evitar duplicados multi-fecha
                                    ->whereExists(function($q){
                                            $q->selectRaw(1)
                                                ->from('planilla as plx')
                                                ->whereColumn('plx.pla_id','pld.pla_id')
                                                ->where('plx.pla_bActivo',1)
                                                ->where('plx.profec_iCodigo',$this->procesoFechaId);
                                    });
                        })
            ->leftJoin('planilla as pl', function($j){
                $j->on('pl.pla_id','=','pld.pla_id')
                  ->where('pl.pla_bActivo',1)
                  ->where('pl.profec_iCodigo','=',$this->procesoFechaId);
            })
            ->where('procesodocente.profec_iCodigo', $this->procesoFechaId)
            ->where('procesodocente.prodoc_iAsignacion', 1)
            ->select('procesodocente.*','pl.pla_iNumero')
            // Orden: Apellido paterno, materno, nombres
            ->orderBy('docente.doc_vcPaterno')
            ->orderBy('docente.doc_vcMaterno')
            ->orderBy('docente.doc_vcNombre');
    // (activo y fecha ya filtrados dentro del join)

        $rows = $query->get();
        $out=[];
        foreach($rows as $r){
            $doc = $r->docente;
            $out[] = [
                'tipo' => $doc?->tipo?->tipo_vcNombre, // corregido: usar nombre del tipo, no nombre planilla
                'codigo' => $doc?->doc_vcCodigo,
                'dni' => $doc?->doc_vcDni,
                'nombres' => trim(($doc?->doc_vcPaterno.' '.($doc?->doc_vcMaterno).' '.($doc?->doc_vcNombre))),
                'local' => $r->local?->localesMaestro?->locma_vcNombre ?? '',
                'cargo' => $r->experienciaAdmision?->maestro?->expadmma_vcNombre,
                'monto' => $r->experienciaAdmision?->expadm_fMonto ?? 0,
                'fecha_asignacion' => $r->procesoFecha?->profec_dFecha, // usar fecha del ProcesoFecha directamente
                'credencial' => $r->prodoc_iCodigo, // credencial corresponde al código del registro proceso
                'numero_planilla' => $r->pla_iNumero,
            ];
        }
        return $out;
    }
}
