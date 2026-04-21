<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ErroresAsignacionDocentesExport implements FromCollection, WithHeadings
{
    protected Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function collection()
    {
        return $this->rows->map(function ($r) {
            return [
                'fila' => $r['row'],
                'codigo' => $r['codigo'],
                'dni' => $r['dni'],
                'cargo' => $r['cargo'],
                'local' => $r['local'],
                'fecha' => $r['fecha'],
                'monto' => $r['monto'] ?? null,
                'errores' => implode('|', $r['errores']),
                'warnings' => implode('|', $r['warnings']),
            ];
        });
    }

    public function headings(): array
    {
        return ['fila', 'codigo', 'dni', 'cargo', 'local', 'fecha', 'monto', 'errores', 'warnings'];
    }
}
