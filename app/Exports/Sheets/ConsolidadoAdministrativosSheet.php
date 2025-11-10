<?php

namespace App\Exports\Sheets;

use App\Models\ProcesoAdministrativo;
use Illuminate\Support\Facades\DB; // (opcional) por consistencia con otros sheets

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
            // Ajuste: profec_iCodigo se filtra en planilla, no en planillaAdministrativo
                        ->leftJoin('planillaAdministrativo as plaadm', function($j){
                                $j->on('plaadm.adm_vcDni','=','procesoadministrativo.adm_vcDni')
                                    ->whereExists(function($q){
                                            $q->selectRaw(1)
                                                ->from('planilla as plx')
                                                ->whereColumn('plx.pla_id','plaadm.pla_id')
                                                ->where('plx.pla_bActivo',1)
                                                ->where('plx.profec_iCodigo',$this->procesoFechaId);
                                    });
                        })
            ->leftJoin('planilla as pl', function($j){
                $j->on('pl.pla_id','=','plaadm.pla_id')
                  ->where('pl.pla_bActivo',1)
                  ->where('pl.profec_iCodigo','=',$this->procesoFechaId);
            })
            ->where('procesoadministrativo.profec_iCodigo', $this->procesoFechaId)
            ->where('procesoadministrativo.proadm_iAsignacion', 1)
            ->select('procesoadministrativo.*','pl.pla_iNumero')
            // Solo existe campo nombres completo
            ->orderBy('administrativo.adm_vcNombres');
    // Filtro de activo + fecha ya aplicado en join
        $rows = $query->get();
        $out=[];
        foreach($rows as $r){
            $adm = $r->administrativo;
            // Mostrar código sólo si el registro es de tipo Administrativo (tipo_iCodigo = 2)
            $esAdministrativo = ((int)($adm?->tipo_iCodigo ?? 0)) === 2;
            $codigo = $esAdministrativo ? ($adm?->adm_vcCodigo ?? '') : '';
            $out[] = [
                'tipo' => $adm?->tipo?->tipo_vcNombre,
                'codigo' => $codigo,
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
