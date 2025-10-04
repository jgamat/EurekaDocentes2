<?php

namespace App\Exports\Sheets;

use App\Models\ProcesoAlumno;

class ConsolidadoAlumnosSheet extends BaseConsolidadoSheet
{
    protected function titleFull(): string
    {
        return 'REPORTE DE CONSOLIDADO DE PLANILLAS DE ALUMNOS';
    }

    public function title(): string
    {
        return 'Alumnos';
    }

    protected function queryRows(): array
    {
        $query = ProcesoAlumno::query()
            ->with(['alumno.tipo','experienciaAdmision.maestro','local.localesMaestro','procesoFecha'])
            // Join a la tabla alumno para ordenar
            ->leftJoin('alumno', 'alumno.alu_vcCodigo', '=', 'procesoalumno.alu_vcCodigo')
            ->leftJoin('planillaAlumno as plaalu', 'plaalu.alu_vcCodigo', '=', 'procesoalumno.alu_vcCodigo')
            ->leftJoin('planilla as pl', function($j){
                $j->on('pl.pla_id','=','plaalu.pla_id');
            })
            ->where('procesoalumno.profec_iCodigo', $this->procesoFechaId)
            ->where('procesoalumno.proalu_iAsignacion', 1)
            ->select('procesoalumno.*','pl.pla_iNumero')
            // Orden: Apellido paterno, materno, nombres
            ->orderBy('alumno.alu_vcPaterno')
            ->orderBy('alumno.alu_vcMaterno')
            ->orderBy('alumno.alu_vcNombre');
                $query->where('pl.pla_bActivo',1);
        $rows = $query->get();
        $out=[];
        foreach($rows as $r){
            $alu = $r->alumno;
            $out[] = [
                'tipo' => $alu?->tipo?->tipo_vcNombre,
                'codigo' => $alu?->alu_vcCodigo,
                'dni' => $alu?->alu_vcDni,
                'nombres' => $alu?->getNombreCompletoAttribute(),
                'local' => $r->local?->localesMaestro?->locma_vcNombre ?? '',
                'cargo' => $r->experienciaAdmision?->maestro?->expadmma_vcNombre,
                'monto' => $r->experienciaAdmision?->expadm_fMonto ?? 0,
                'fecha_asignacion' => $r->procesoFecha?->profec_dFecha,
                'credencial' => $r->proalu_iCodigo,
                'numero_planilla' => $r->pla_iNumero,
            ];
        }
        return $out;
    }
}
