<?php

namespace App\Exports;

use App\Models\ProcesoFecha;
use App\Exports\Sheets\ConsolidadoDocentesSheet;
use App\Exports\Sheets\ConsolidadoAdministrativosSheet;
use App\Exports\Sheets\ConsolidadoAlumnosSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ConsolidadoPlanillasExport implements WithMultipleSheets
{
    public function __construct(
        protected int $procesoFechaId,
        protected string $fechaLabel,
        protected string $generatedAt
    ) {}

    public function sheets(): array
    {
        return [
            new ConsolidadoDocentesSheet($this->procesoFechaId, $this->fechaLabel, $this->generatedAt),
            new ConsolidadoAdministrativosSheet($this->procesoFechaId, $this->fechaLabel, $this->generatedAt),
            new ConsolidadoAlumnosSheet($this->procesoFechaId, $this->fechaLabel, $this->generatedAt),
        ];
    }
}
