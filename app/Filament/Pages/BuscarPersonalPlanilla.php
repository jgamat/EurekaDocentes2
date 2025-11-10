<?php

namespace App\Filament\Pages;

use App\Models\Proceso;
use App\Models\ProcesoFecha;
use App\Support\CurrentContext;
use App\Support\Traits\UsesGlobalContext;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class BuscarPersonalPlanilla extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.buscar-personal-planilla';

       use HasPageShield;

    // Filtros / Estado de búsqueda (se alimentan desde contexto global)
    public ?int $proceso_id = null;
    public ?int $proceso_fecha_id = null;
    public string $tipo = 'docente'; // docente | administrativo | tercero_cas | alumno
    public string $q = '';

    // Resultados
    public array $resultados = [];

    public function mount(): void
    {
        // Usar contexto global cargado y válido
        $ctx = app(CurrentContext::class);
        $ctx->ensureLoaded();
        $ctx->ensureValid();
        $this->proceso_id = $ctx->procesoId();
        $this->proceso_fecha_id = $ctx->fechaId();
    }

    // Listener externo disparado por el switcher global
    protected $listeners = ['context-changed' => 'onGlobalContextChanged'];

    public function onGlobalContextChanged(): void
    {
        $ctx = app(CurrentContext::class);
        $this->proceso_id = $ctx->procesoId();
        $this->proceso_fecha_id = $ctx->fechaId();
        // Reiniciar resultados y término de búsqueda para evitar inconsistencias
        $this->q = '';
        $this->resultados = [];
    }

    public function buscar(): void
    {
        $this->validate([
            'proceso_id' => 'required|integer',
            'proceso_fecha_id' => 'required|integer',
            'tipo' => 'required|string|in:docente,administrativo,tercero_cas,alumno',
            'q' => 'nullable|string',
        ]);

        $term = trim($this->q ?? '');
        $procesoId = (int) $this->proceso_id;
        $fechaId = (int) $this->proceso_fecha_id;

        if ($this->tipo === 'docente') {
            $rows = DB::table('planillaDocente as pd')
                ->join('planilla as p', 'p.pla_id', '=', 'pd.pla_id')
                ->join('docente as d', 'd.doc_vcCodigo', '=', 'pd.doc_vcCodigo')
                ->join('procesodocente as prd', function ($j) use ($fechaId) {
                    $j->on('prd.doc_vcCodigo', '=', 'd.doc_vcCodigo')
                      ->where('prd.profec_iCodigo', '=', $fechaId)
                      ->where('prd.prodoc_iAsignacion', '=', 1);
                })
                ->join('locales as l', 'l.loc_iCodigo', '=', 'prd.loc_iCodigo')
                ->join('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
                ->join('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'prd.expadm_iCodigo')
                ->join('experienciaadmisionMaestro as em', 'em.expadmma_iCodigo', '=', 'ea.expadmma_iCodigo')
                ->where('p.pro_iCodigo', $procesoId)
                ->where('p.profec_iCodigo', $fechaId)
                ->where('p.pla_bActivo', 1)
                ->when($term !== '', function ($q) use ($term) {
                    $like = '%' . str_replace(' ', '%', $term) . '%';
                    $q->where(function ($w) use ($like) {
                        $w->where('d.doc_vcCodigo', 'like', $like)
                          ->orWhere('d.doc_vcDni', 'like', $like)
                          ->orWhere(DB::raw("CONCAT(d.doc_vcPaterno,' ',d.doc_vcMaterno,' ',d.doc_vcNombre)"), 'like', $like);
                    });
                })
                ->orderBy('p.pla_iNumero')
                ->selectRaw("d.doc_vcCodigo as codigo, d.doc_vcDni as dni, CONCAT(d.doc_vcPaterno,' ',d.doc_vcMaterno,' ',d.doc_vcNombre) as nombres, lm.locma_vcNombre as local, em.expadmma_vcNombre as cargo, prd.prodoc_dtFechaAsignacion as fecha_asignacion, p.pla_iNumero as numero_planilla")
                ->get();
        } elseif ($this->tipo === 'administrativo' || $this->tipo === 'tercero_cas') {
            $rows = DB::table('planillaAdministrativo as pa')
                ->join('planilla as p', 'p.pla_id', '=', 'pa.pla_id')
                ->join('administrativo as a', 'a.adm_vcDni', '=', 'pa.adm_vcDni')
                ->join('procesoadministrativo as pra', function ($j) use ($fechaId) {
                    $j->on('pra.adm_vcDni', '=', 'a.adm_vcDni')
                      ->where('pra.profec_iCodigo', '=', $fechaId)
                      ->where('pra.proadm_iAsignacion', '=', 1);
                })
                ->join('locales as l', 'l.loc_iCodigo', '=', 'pra.loc_iCodigo')
                ->join('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
                ->join('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'pra.expadm_iCodigo')
                ->join('experienciaadmisionMaestro as em', 'em.expadmma_iCodigo', '=', 'ea.expadmma_iCodigo')
                ->where('p.pro_iCodigo', $procesoId)
                ->where('p.profec_iCodigo', $fechaId)
                ->where('p.pla_bActivo', 1)
                ->when($term !== '', function ($q) use ($term) {
                    $like = '%' . str_replace(' ', '%', $term) . '%';
                    $q->where(function ($w) use ($like) {
                        $w->where('a.adm_vcCodigo', 'like', $like)
                          ->orWhere('a.adm_vcDni', 'like', $like)
                          ->orWhere('a.adm_vcNombres', 'like', $like);
                    });
                })
                ->orderBy('p.pla_iNumero')
                ->selectRaw("a.adm_vcCodigo as codigo, a.adm_vcDni as dni, a.adm_vcNombres as nombres, lm.locma_vcNombre as local, em.expadmma_vcNombre as cargo, pra.proadm_dtFechaAsignacion as fecha_asignacion, p.pla_iNumero as numero_planilla")
                ->get();
        } else { // alumno
            $rows = DB::table('planillaAlumno as pl')
                ->join('planilla as p', 'p.pla_id', '=', 'pl.pla_id')
                ->join('alumno as al', 'al.alu_vcCodigo', '=', 'pl.alu_vcCodigo')
                ->join('procesoalumno as pral', function ($j) use ($fechaId) {
                    $j->on('pral.alu_vcCodigo', '=', 'al.alu_vcCodigo')
                      ->where('pral.profec_iCodigo', '=', $fechaId)
                      ->where('pral.proalu_iAsignacion', '=', 1);
                })
                ->join('locales as l', 'l.loc_iCodigo', '=', 'pral.loc_iCodigo')
                ->join('localMaestro as lm', 'lm.locma_iCodigo', '=', 'l.locma_iCodigo')
                ->join('experienciaadmision as ea', 'ea.expadm_iCodigo', '=', 'pral.expadm_iCodigo')
                ->join('experienciaadmisionMaestro as em', 'em.expadmma_iCodigo', '=', 'ea.expadmma_iCodigo')
                ->where('p.pro_iCodigo', $procesoId)
                ->where('p.profec_iCodigo', $fechaId)
                ->where('p.pla_bActivo', 1)
                ->when($term !== '', function ($q) use ($term) {
                    $like = '%' . str_replace(' ', '%', $term) . '%';
                    $q->where(function ($w) use ($like) {
                        $w->where('al.alu_vcCodigo', 'like', $like)
                          ->orWhere('al.alu_vcDni', 'like', $like)
                          ->orWhere(DB::raw("CONCAT(al.alu_vcPaterno,' ',al.alu_vcMaterno,' ',al.alu_vcNombre)"), 'like', $like);
                    });
                })
                ->orderBy('p.pla_iNumero')
                ->selectRaw("al.alu_vcCodigo as codigo, al.alu_vcDni as dni, CONCAT(al.alu_vcPaterno,' ',al.alu_vcMaterno,' ',al.alu_vcNombre) as nombres, lm.locma_vcNombre as local, em.expadmma_vcNombre as cargo, pral.proalu_dtFechaAsignacion as fecha_asignacion, p.pla_iNumero as numero_planilla")
                ->get();
        }

        $this->resultados = $rows->map(function ($r) {
            return [
                'codigo' => $r->codigo,
                'dni' => $r->dni,
                'nombres' => $r->nombres,
                'local' => $r->local,
                'cargo' => $r->cargo,
                'fecha_asignacion' => $r->fecha_asignacion,
                'numero_planilla' => $r->numero_planilla,
            ];
        })->toArray();
    }

    // Ya no se exponen selects locales; proceso/fecha provienen del contexto global
}
