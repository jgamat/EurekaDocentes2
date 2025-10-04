<?php

namespace App\Exports\Sheets;

use App\Models\ProcesoAdministrativo;

class ConsolidadoAdministrativosSheet extends BaseConsolidadoSheet
{
    protected function titleFull(): string
    {
        return 'REPORTE DE CONSOLIDADO DE PLANILLAS DE ADMINISTRATIVOS';
    }

    public function title(): string
    {
        return 'Administrativos';
    }

    protected function queryRows(): array
    {
        $query = ProcesoAdministrativo::query()
            ->with(['administrativo.tipo','experienciaAdmision.maestro','local.localesMaestro','procesoFecha'])
            // Join a la tabla administrativo para ordenar por nombres
            ->leftJoin('administrativo', 'administrativo.adm_vcDni', '=', 'procesoadministrativo.adm_vcDni')
            ->leftJoin('planillaAdministrativo as plaadm', 'plaadm.adm_vcDni', '=', 'procesoadministrativo.adm_vcDni')
            ->leftJoin('planilla as pl', function($j){
                $j->on('pl.pla_id','=','plaadm.pla_id');
            })
            ->where('procesoadministrativo.profec_iCodigo', $this->procesoFechaId)
            ->where('procesoadministrativo.proadm_iAsignacion', 1)
            ->select('procesoadministrativo.*','pl.pla_iNumero')
            // Solo existe campo nombres completo
            ->orderBy('administrativo.adm_vcNombres');
                $query->where('pl.pla_bActivo',1);
        $rows = $query->get();
        $out=[];
        foreach($rows as $r){
            $adm = $r->administrativo;
            $out[] = [
                'tipo' => $adm?->tipo?->tipo_vcNombre,
                'codigo' => $adm?->adm_vcDni,
                'dni' => $adm?->adm_vcDni,
                'nombres' => $adm?->adm_vcNombres,
                'local' => $r->local?->localesMaestro?->locma_vcNombre ?? '',
                'cargo' => $r->experienciaAdmision?->maestro?->expadmma_vcNombre,
                'monto' => $r->experienciaAdmision?->expadm_fMonto ?? 0,
                'fecha_asignacion' => $r->procesoFecha?->profec_dFecha,
                'credencial' => $r->proadm_iCodigo,
                'numero_planilla' => $r->pla_iNumero,
            ];
        }
        return $out;
    }
}
