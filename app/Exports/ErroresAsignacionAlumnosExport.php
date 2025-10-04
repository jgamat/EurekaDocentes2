<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;

class ErroresAsignacionAlumnosExport implements FromCollection, WithHeadings
{
    public function __construct(private Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows->map(function($r){
            return [
                'fila' => $r['row'] ?? null,
                'codigo' => $r['codigo'] ?? null,
                'dni' => $r['dni'] ?? null,
                'paterno' => $r['paterno'] ?? null,
                'materno' => $r['materno'] ?? null,
                'nombres' => $r['nombres'] ?? null,
                'cargo' => $r['cargo'] ?? null,
                'local' => $r['local'] ?? null,
                'fecha' => $r['fecha'] ?? null,
                'errores' => implode(' | ', $r['errores'] ?? []),
                'warnings' => implode(' | ', $r['warnings'] ?? []),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'fila','codigo','dni','paterno','materno','nombres','cargo','local','fecha','errores','warnings'
        ];
    }
}
