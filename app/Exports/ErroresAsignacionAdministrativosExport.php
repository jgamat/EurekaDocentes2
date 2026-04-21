<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ErroresAsignacionAdministrativosExport implements FromCollection, WithHeadings
{
    public function __construct(private Collection $rows) {}

    public function headings(): array
    {
        return ['fila', 'codigo', 'dni', 'cargo', 'local', 'fecha', 'monto', 'errores', 'warnings'];
    }

    public function collection(): Collection
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
}
